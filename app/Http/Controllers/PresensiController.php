<?php

namespace App\Http\Controllers;

/**
 * Status Presensi yang Standar:
 * 
 * Status Masuk:
 * - absen_masuk: Absen masuk tepat waktu
 * - terlambat: Terlambat absen masuk
 * - tidak_absen_masuk: Tidak absen masuk
 * - tidak_hadir: Tidak hadir
 * - izin: Izin
 * - sakit: Sakit
 * - cuti: Cuti
 * 
 * Status Pulang:
 * - absen_pulang: Absen pulang tepat waktu
 * - pulang_awal: Pulang sebelum waktu pulang
 * - tidak_absen_pulang: Tidak absen pulang
 * - tidak_hadir: Tidak hadir
 * - izin: Izin
 * - sakit: Sakit
 * - cuti: Cuti
 * 
 * Status Presensi (Final):
 * - hadir: Hadir (dihitung dari status masuk/pulang yang hadir)
 * - tidak_hadir: Tidak hadir
 * - sakit: Sakit
 * - izin: Izin
 * - cuti: Cuti
 */

use Illuminate\Http\Request;
use App\Models\Presensi;
use App\Models\MsPegawai;
use App\Models\Shift;
use App\Models\ShiftDetail;
use App\Models\UnitDetail;
use App\Models\PresensiJadwalDinas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Helpers\AdminUnitHelper;

class PresensiController extends Controller
{
    // Fungsi point-in-polygon sederhana
    private function isPointInPolygon($point, $polygon)
    {
        $x = $point[0];
        $y = $point[1];
        $inside = false;
        $n = count($polygon);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];
            $intersect = (($yi > $y) != ($yj > $y)) &&
                ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-10) + $xi);
            if ($intersect) $inside = !$inside;
        }
        return $inside;
    }

    /**
     * Cek apakah pegawai memiliki jadwal dinas pada tanggal tertentu
     */
    private function checkJadwalDinas($pegawaiId, $tanggal)
    {
        return PresensiJadwalDinas::getJadwalDinasForPegawai($pegawaiId, $tanggal);
    }

    public function store(Request $request)
    {
        $pegawai = $request->get('pegawai');
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $pegawai->load([
            'pegawai.shiftDetail.shift',
            'pegawai.unitDetailPresensi.unit',
            'pegawai'
        ]);

        $request->validate([
            'lokasi' => 'required|array|size:2',
        ]);
        $now = \Carbon\Carbon::now('Asia/Jakarta');
        $hari = strtolower($now->locale('id')->isoFormat('dddd'));

        // Cek apakah pegawai memiliki jadwal dinas hari ini
        $jadwalDinas = $this->checkJadwalDinas($pegawai->id, $now->toDateString());

        $shiftDetail = $pegawai->shiftDetail;
        if (!$shiftDetail) {
            return response()->json(['message' => 'Shift detail tidak ditemukan untuk pegawai ini'], 400);
        }

        $unitDetail = $pegawai->unitDetailPresensi;
        if (!$unitDetail) {
            return response()->json(['message' => 'Unit detail tidak ditemukan'], 400);
        }

        // Validasi lokasi (point-in-polygon)
        $polygon = $unitDetail->lokasi;
        // if (!$this->isPointInPolygon($request->lokasi, $polygon)) {
        //     return response()->json(['message' => 'Lokasi di luar area'], 400);
        // }

        // Cek apakah hari ini adalah hari libur
        $isHariLibur = \App\Models\HariLibur::isHariLibur($unitDetail->id, $now->toDateString());
        if ($isHariLibur) {
            return response()->json(['message' => 'Hari ini adalah hari libur'], 400);
        }

        // Validasi waktu presensi
        $masukKey = $hari . '_masuk';
        $pulangKey = $hari . '_pulang';
        $jamMasuk = $shiftDetail->$masukKey;
        $jamPulang = $shiftDetail->$pulangKey;
        $tolMasuk = $shiftDetail->toleransi_terlambat ?? 0;
        $tolPulang = $shiftDetail->toleransi_pulang ?? 0;

        if (!$jamMasuk && !$jamPulang) {
            return response()->json(['message' => 'Hari ini libur, tidak ada jam masuk/pulang'], 400);
        }

        // Cek apakah sudah ada presensi hari ini (format baru)
        $presensiHariIni = Presensi::where('no_ktp', $pegawai->no_ktp)
            ->whereDate('waktu_masuk', $now->toDateString())
            ->first();

        if ($presensiHariIni) {
            return $this->handlePresensiPulang($request, $presensiHariIni, $now, $shiftDetail, $jamPulang, $tolPulang, $jadwalDinas);
        } else {
            return $this->handlePresensiMasuk($request, $now, $shiftDetail, $jamMasuk, $tolMasuk, $pegawai, $jadwalDinas);
        }
    }

    private function handlePresensiMasuk(Request $request, $now, $shiftDetail, $jamMasuk, $tolMasuk, $pegawai, $jadwalDinas = null)
    {
        $statusMasuk = null;
        $keteranganMasuk = null;
        $waktuMasukUntukSimpan = $now;
        $jam12 = \Carbon\Carbon::createFromTime(12, 0, 0, 'Asia/Jakarta');

        $jamPulang = $shiftDetail->{$now->locale('id')->isoFormat('dddd') . '_pulang'} ?? null;

        // Jika presensi dilakukan setelah jam 12.00 tapi sebelum jam pulang
        if ($now->greaterThanOrEqualTo($jam12) && $jamPulang && $now->lessThan(\Carbon\Carbon::createFromFormat('H:i', $jamPulang, 'Asia/Jakarta'))) {
            // Anggap tidak absen masuk
            $statusMasuk = 'tidak_absen_masuk';
            $keteranganMasuk = 'Tidak absen masuk, sudah lewat jam 12:00';
            $waktuMasukUntukSimpan = $jam12;

            // Simpan presensi dengan status masuk gagal
            $presensi = Presensi::create([
                'no_ktp' => $pegawai->no_ktp,
                'shift_id' => $shiftDetail->shift_id,
                'shift_detail_id' => $shiftDetail->id,
                'waktu_masuk' => $waktuMasukUntukSimpan,
                'status_masuk' => $statusMasuk,
                'lokasi_masuk' => $request->lokasi,
                'keterangan_masuk' => $keteranganMasuk,
                'status_presensi' => 'tidak_absen_masuk',
            ]);

            // // Otomatis dianggap langsung absen pulang (pulang_awal)
            // $presensi->update([
            //     'waktu_pulang' => $now,
            //     'status_pulang' => 'pulang_awal',
            //     'lokasi_pulang' => $request->lokasi,
            //     'keterangan_pulang' => 'Absen pulang setelah jam 12 tanpa absen masuk',
            //     'status_presensi' => 'tidak_absen_masuk',
            // ]);

            return response()->json([
                'no_ktp' => $presensi->no_ktp,
                'shift_detail_id' => $presensi->shift_detail_id,
                'tanggal' => $presensi->waktu_masuk->format('Y-m-d'),
                'waktu_masuk' => $presensi->waktu_masuk->format('H:i:s'),
                'status_masuk' => $presensi->status_masuk,
                //'waktu_pulang' => $presensi->waktu_pulang->format('H:i:s'),
                //'status_pulang' => $presensi->status_pulang,
                'keterangan' => $presensi->keteranganMasuk,
            ]);
        }

        // logic presensi
        if ($pegawai->pegawai->profesi == 'driver' || $jadwalDinas) {
            $statusMasuk = 'absen_masuk';
            $keteranganMasuk = $jadwalDinas ? $jadwalDinas->keterangan : 'Otomatis absen masuk';
            $waktuMasukUntukSimpan = $now;
        } else {
            if ($jamMasuk) {
                try {
                    $waktuMasuk = \Carbon\Carbon::createFromFormat('H:i', $jamMasuk, 'Asia/Jakarta');
                    $batasMasuk = $waktuMasuk->copy()->addMinutes($tolMasuk);

                    if ($now->lessThan($waktuMasuk)) {
                        $statusMasuk = 'absen_masuk';
                        $keteranganMasuk = 'Masuk tepat waktu';
                    } elseif ($now->between($waktuMasuk, $batasMasuk)) {
                        $statusMasuk = 'absen_masuk';
                        $keteranganMasuk = 'Masuk tepat waktu';
                    } elseif ($now->greaterThan($batasMasuk) && $now->lessThan($jam12)) {
                        $statusMasuk = 'terlambat';
                        $keteranganMasuk = 'Terlambat';
                    }
                } catch (\Exception $e) {
                    return response()->json(['message' => 'Format jam masuk tidak valid'], 400);
                }
            }
            if (!$statusMasuk) {
                $statusMasuk = 'tidak_absen_masuk';
                $keteranganMasuk = 'Tidak absen masuk';
            }
        }

        $statusPresensi = $jadwalDinas ? 'dinas' : null;
        $keteranganDinas = null;

        if ($jadwalDinas) {
            $statusPresensi = 'dinas';
            $keteranganDinas = $jadwalDinas->keterangan;
        }
        // elseif (in_array($statusMasuk, ['absen_masuk', 'terlambat'])) {
        //     $statusPresensi = 'hadir';
        // }

        // Simpan presensi masuk normal
        $presensi = Presensi::create([
            'no_ktp' => $pegawai->no_ktp,
            'shift_id' => $shiftDetail->shift_id,
            'shift_detail_id' => $shiftDetail->id,
            'waktu_masuk' => $waktuMasukUntukSimpan,
            'status_masuk' => $statusMasuk,
            'lokasi_masuk' => $request->lokasi,
            'keterangan_masuk' => $keteranganDinas ? $keteranganDinas : $keteranganMasuk,
            'status_presensi' => $statusPresensi,
        ]);

        if ($pegawai->pegawai->profesi == 'driver') {
            $driverStatusPresensi = $jadwalDinas ? 'dinas' : 'hadir';
            $driverKeterangan = $jadwalDinas ? $jadwalDinas->keterangan : '';

            $presensi->update([
                'waktu_pulang' => $now,
                'status_pulang' => 'absen_pulang',
                'lokasi_pulang' => $request->lokasi,
                'keterangan_pulang' => $driverKeterangan,
                'status_presensi' => $driverStatusPresensi,
            ]);
        }

        if ($jadwalDinas) {
            $statusPresensiFinal = $jadwalDinas ? 'dinas' : 'hadir';
            $keteranganFinal = $jadwalDinas ? $jadwalDinas->keterangan : 'dinas';

            $presensi->update([
                'waktu_pulang' => $now,
                'status_pulang' => 'absen_pulang',
                'lokasi_pulang' => $request->lokasi,
                'keterangan_pulang' => $keteranganFinal,
                'status_presensi' => $statusPresensiFinal,
            ]);
        }



        return response()->json([
            'no_ktp' => $presensi->no_ktp,
            'shift_detail_id' => $presensi->shift_detail_id,
            'tanggal' => $presensi->waktu_masuk->format('Y-m-d'),
            'waktu' => $presensi->waktu_masuk->format('H:i:s'),
            'status' => $presensi->status_masuk,
            'keterangan' => $presensi->keterangan_masuk,
        ]);
    }


    private function handlePresensiPulang(Request $request, $presensi, $now, $shiftDetail, $jamPulang, $tolPulang, $jadwalDinas = null)
    {
        $statusPulang = null;
        $keteranganPulang = null;

        // Validasi waktu pulang
        if ($jamPulang) {
            try {
                $waktuPulang = \Carbon\Carbon::createFromFormat('H:i', $jamPulang, 'Asia/Jakarta');
                $batasAwalPulang = $waktuPulang->copy()->subMinutes($tolPulang);

                $batasSiang = Carbon::today()->setHour(12)->setMinute(0)->setSecond(0);

                if ($now->lessThan($batasSiang)) {
                    return response()->json(['message' => 'Belum waktunya absen pulang'], 400);
                }

                if ($now->lessThan($waktuPulang)) {
                    $statusPulang = 'pulang_awal';
                    $keteranganPulang = 'Pulang sebelum waktu pulang';
                } else {
                    $statusPulang = 'absen_pulang';
                    $keteranganPulang = 'Pulang tepat waktu';
                }
            } catch (\Exception $e) {
                return response()->json(['message' => 'Format jam pulang tidak valid'], 400);
            }
        }

        if (!$statusPulang) {
            $statusPulang = 'tidak_absen_pulang';
            $keteranganPulang = 'Tidak absen pulang';
        }

        $overtime = false;
        // if ($now->gt($waktuPulang) && $now->diffInMinutes($waktuPulang) > 60) {
        //     $overtime = true;
        // }
        $awalLembur = Carbon::createFromTime(18, 30);

        if ($now->gt($awalLembur)) {
            $overtime = true;
        }

        // Tentukan status presensi berdasarkan jadwal dinas
        $statusPresensi = $this->calculateFinalStatus($presensi->status_masuk, $statusPulang, $jadwalDinas);
        $keteranganPulangFinal = $keteranganPulang;

        if ($jadwalDinas) {
            $statusPresensi = 'dinas';
            $keteranganPulangFinal = $jadwalDinas->keterangan;
        }

        // Update presensi dengan data pulang
        $presensi->update([
            'waktu_pulang' => $now,
            'status_pulang' => $statusPulang,
            'lokasi_pulang' => $request->lokasi,
            'keterangan_pulang' => $keteranganPulangFinal,
            'status_presensi' => $statusPresensi,
            'overtime' => $overtime,
        ]);

        $shift_name = $shiftDetail->shift ? $shiftDetail->shift->name : null;
        return response()->json([
            'no_ktp' => $presensi->no_ktp,
            'shift_name' => $shift_name,
            'shift_detail_id' => $presensi->shift_detail_id,
            'tanggal' => $presensi->waktu_masuk->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d'),
            'waktu' => $presensi->waktu_pulang->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('H:i:s'),
            'status' => $presensi->status_pulang,
            'lokasi' => $presensi->lokasi_pulang,
            'keterangan' => $presensi->keterangan_pulang,
            'updated_at' => $presensi->updated_at->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s'),
            'created_at' => $presensi->created_at->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s'),
            'id' => $presensi->id,
        ]);
    }

    private function calculateFinalStatus($statusMasuk, $statusPulang, $jadwalDinas = null)
    {
        if ($jadwalDinas) {
            return 'dinas';
        }
        $specialOverrides = ['izin', 'sakit', 'cuti'];
        if (in_array($statusMasuk, $specialOverrides)) {
            return $statusMasuk;
        }

        if ($statusMasuk === 'absen_masuk' && $statusPulang === 'absen_pulang') {
            return 'hadir';
        }

        if ($statusMasuk === 'absen_masuk') {
            if ($statusPulang === 'pulang_awal') {
                return 'pulang_awal';
            }
            if ($statusPulang === 'tidak_absen_pulang' || $statusPulang === null) {
                return 'tidak_absen_pulang';
            }
        }

        if ($statusMasuk === 'terlambat' && $statusPulang === 'absen_pulang') {
            return 'terlambat';
        }

        if ($statusMasuk === 'terlambat' && $statusPulang === 'pulang_awal') {
            return 'terlambat';
        }

        if ($statusMasuk === 'tidak_absen_masuk' && $statusPulang === 'absen_pulang') {
            return 'tidak_absen_masuk';
        }
        if ($statusMasuk === 'tidak_absen_masuk' && $statusPulang === 'pulang_awal') {
            return 'tidak_absen_masuk';
        }
    }


    // Presensi hari ini (masuk & keluar)
    public function today(Request $request)
    {
        $pegawai = $request->get('pegawai');

        $pegawai->load([
            'pegawai.shiftDetail.shift',
            'pegawai.unitDetailPresensi.unit',
            'pegawai'
        ]);
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }
        $today = \Carbon\Carbon::now('Asia/Jakarta')->toDateString();

        $presensi = Presensi::where('no_ktp', $pegawai->no_ktp)
            ->whereDate('waktu_masuk', $today)
            ->first();

        // $statusPresensi = $presensi?->status_presensi ?? $presensi?->status_masuk ?? null;
        $status_final = null;
        if ($presensi->status_presensi == null && $presensi->status_masuk == 'terlambat') {
            $status_final = 'terlambat';
        } else if ($presensi->status_presensi == null && $presensi->status_masuk == 'tidak_absen_masuk') {
            $status_final = 'tidak_absen_masuk';
        } else {
            $status_final = $presensi->status_presensi;
        }


        return response()->json([
            'tanggal' => $today,
            'jam_masuk' => $presensi ? $presensi->waktu_masuk?->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('H:i:s') : null,
            'jam_keluar' => $presensi ? $presensi->waktu_pulang?->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('H:i:s') : null,
            'status_masuk' => $presensi ? $presensi->status_masuk : null,
            'status_keluar' => $presensi ? $presensi->status_pulang : null,
            'status_presensi' => $status_final,
            'lokasi_masuk' => $presensi ? $presensi->lokasi_masuk : null,
            'lokasi_keluar' => $presensi ? $presensi->lokasi_pulang : null,
        ]);
    }

    public function history(Request $request)
    {
        $pegawai = $request->get('pegawai');

        $pegawai->load([
            'pegawai.shiftDetail.shift',
            'pegawai.unitDetailPresensi.unit',
            'pegawai'
        ]);

        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $tanggal = $request->query('tanggal');
        if ($tanggal) {
            $presensi = Presensi::where('no_ktp', $pegawai->no_ktp)
                ->whereDate('waktu_masuk', $tanggal)
                ->first();

            $status_final = '-';

            // if ($presensi && $presensi->status_masuk !== null) {
            //     $statusPresensi = $presensi->status_presensi;
            // }
            $now = Carbon::now('Asia/Jakarta');

            if ($presensi) {

                if ($now->format('H:i') >= '17:00' && $presensi->status_pulang === null) {
                    $status_final = 'Tidak Presensi Pulang';
                } else if ($presensi->status_presensi === null && $presensi->status_masuk === 'terlambat') {
                    $status_final = 'Terlambat';
                } else if ($presensi->status_presensi === null && $presensi->status_masuk === 'tidak_absen_masuk') {
                    $status_final = 'Tidak Presensi Masuk';
                } else {
                    $status_raw = $presensi->status_presensi ?? $presensi->status_masuk;

                    $mapStatus = [
                        'absen_masuk' => 'Presensi Masuk',
                        'hadir' => 'Hadir',
                        'tidak_hadir' => 'Tidak Hadir',
                        'terlambat' => 'Terlambat',
                        'tidak_absen_masuk' => 'Tidak Presensi Masuk',
                    ];

                    $status_final = $mapStatus[$status_raw] ?? $status_raw;
                }
            }



            return response()->json([
                'hari' => \Carbon\Carbon::parse($tanggal)->locale('id')->isoFormat('dddd'),
                'tanggal' => $tanggal,
                'jam_masuk' => $presensi ? $presensi->waktu_masuk?->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('H:i:s') : null,
                'jam_keluar' => $presensi ? $presensi->waktu_pulang?->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('H:i:s') : null,
                'status_masuk' => $presensi ? $presensi->status_masuk : null,
                'status_keluar' => $presensi ? $presensi->status_pulang : null,
                'status_presensi' => $status_final,
            ]);
        }

        $from = $request->query('from', \Carbon\Carbon::now('Asia/Jakarta')->startOfMonth()->toDateString());
        $to = $request->query('to', \Carbon\Carbon::now('Asia/Jakarta')->toDateString());

        $presensi = Presensi::where('no_ktp', $pegawai->no_ktp)
            ->whereBetween('waktu_masuk', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('waktu_masuk')
            ->get();

        $history = [];
        foreach ($presensi as $p) {
            $statusPresensi = $p->status_masuk !== null ? $p->status_presensi : 'Tidak Hadir';

            $history[] = [
                'hari' => $p->waktu_masuk->locale('id')->isoFormat('dddd'),
                'tanggal' => $p->waktu_masuk->format('Y-m-d'),
                'jam_masuk' => $p->waktu_masuk?->format('H:i:s'),
                'jam_keluar' => $p->waktu_pulang?->format('H:i:s'),
                'status_masuk' => $p->status_masuk,
                'status_keluar' => $p->status_pulang,
                'status_presensi' => $statusPresensi,
            ];
        }

        return response()->json($history);
    }


    public function rekapPresensiByAdminUnit(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }


        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $tanggal = $request->query('tanggal', now('Asia/Jakarta')->toDateString());
        $pegawais = MsPegawai::whereHas('unitDetailPresensi', function ($q) use ($unitId) {
            $q->where('id_unit', $unitId);
        })->with('orang')->get();

        $result = [];
        foreach ($pegawais as $pegawai) {
            if (!$pegawai->orang) {
                continue;
            }

            $start = \Carbon\Carbon::parse($tanggal)->startOfDay();
            $end   = $start->copy()->endOfDay();

            // Ambil presensi untuk hari itu
            $presensis = Presensi::where('no_ktp', $pegawai->orang->no_ktp)
                ->whereBetween('waktu_masuk', [$start, $end])
                ->get();

            // Ambil pengajuan
            $izin = \App\Models\PengajuanIzin::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->count();

            $cuti = \App\Models\PengajuanCuti::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->count();

            $sakit = \App\Models\PengajuanSakit::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->count();

            // Hitung status lain seperti di rekap tahunan
            $total_hadir            = 0;
            $total_tidak_masuk      = 0;
            $total_dinas            = 0;
            $total_lembur           = 0;
            $total_terlambat        = 0;
            $total_pulang_awal      = 0;
            $total_tidak_absen_masuk = 0;
            $total_tidak_absen_pulang = 0;
            $total_belum_presensi   = 0;

            $tanggalStr = $start->format('Y-m-d');
            $presensiHari = $presensis->filter(function ($p) use ($tanggalStr) {
                return $p->waktu_masuk && \Carbon\Carbon::parse($p->waktu_masuk)->format('Y-m-d') === $tanggalStr;
            });

            $status = null;

            if ($presensiHari->count() && $presensiHari->where('status_presensi', 'dinas')->count()) {
                $status = 'dinas';
                $total_dinas++;
            } elseif ($presensiHari->count() && $presensiHari->where('overtime', true)->count()) {
                $status = 'lembur';
                $total_lembur++;
            } elseif ($presensiHari->count() && $presensiHari->where('status_masuk', 'terlambat')->count()) {
                $status = 'terlambat';
                $total_terlambat++;
            } elseif ($presensiHari->count() && $presensiHari->where('status_masuk', 'tidak_absen_masuk')->count()) {
                $status = 'tidak_absen_masuk';
                $total_tidak_absen_masuk++;
            } elseif ($presensiHari->count() && $presensiHari->where('status_pulang', 'pulang_awal')->count()) {
                $status = 'pulang_awal';
                $total_pulang_awal++;
            } elseif (
                $presensiHari->count() &&
                (
                    $presensiHari->where('status_pulang', 'tidak_absen_pulang')->count()
                    || $presensiHari->whereNull('status_pulang')->count()
                )
            ) {
                $status = 'tidak_absen_pulang';
                $total_tidak_absen_pulang++;
            } else {
                if (
                    $presensiHari->count()
                    && $presensiHari->where('status_masuk', 'absen_masuk')->count()
                    && $presensiHari->where('status_pulang', 'absen_pulang')->count()
                ) {
                    $status = 'hadir';
                    $total_hadir++;
                }
            }

            if (!$status) {
                if ($izin) {
                    $status = 'izin';
                } elseif ($cuti) {
                    $status = 'cuti';
                } elseif ($sakit) {
                    $status = 'sakit';
                } else {
                    $status = 'tidak_hadir';
                    $total_tidak_masuk++;
                }
            }

            $result[] = [
                'id' => $pegawai->id,
                'no_ktp' => $pegawai->orang->no_ktp,
                'nama' => $pegawai->orang->nama,
                'total_hadir' => $total_hadir,
                'total_tidak_masuk' => $total_tidak_masuk,
                'total_izin' => $izin,
                'total_cuti' => $cuti,
                'total_sakit' => $sakit,
                'total_dinas' => $total_dinas,
                'total_lembur' => $total_lembur,
                'total_terlambat' => $total_terlambat,
                'total_pulang_awal' => $total_pulang_awal,
                'total_tidak_absen_masuk' => $total_tidak_absen_masuk,
                'total_tidak_absen_pulang' => $total_tidak_absen_pulang,
                'total_belum_presensi' => $total_belum_presensi,
            ];
        }

        return response()->json($result);
    }


    /**
     * Grafik rekap bulanan (1 tahun berjalan) untuk pegawai login
     * Mengembalikan agregat per bulan menggunakan variabel:
     * hadir, izin, sakit, cuti, tidak_hadir, dinas, lembur, terlambat,
     * pulang_awal, tidak_absen_masuk, tidak_absen_pulang, belum_presensi
     */

    //REKAP TAHUNAN PEGAWAI STATUS DIPISAH
    // public function rekapHistoryTahunanPegawai(Request $request)
    // {
    //     $pegawai = $request->get('pegawai');
    //     if (!$pegawai) {
    //         return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
    //     }

    //     $pegawai->load(['pegawai.unitDetailPresensi.unit', 'pegawai']);

    //     $tahun = (int) $request->query('tahun', now('Asia/Jakarta')->year);

    //     $result = [];

    //     for ($bulan = 1; $bulan <= 12; $bulan++) {
    //         $start = \Carbon\Carbon::create($tahun, $bulan, 1, 0, 0, 0, 'Asia/Jakarta');
    //         $end = $start->copy()->endOfMonth();

    //         // Ambil presensi pada bulan tsb (format baru: 1 row per hari)
    //         $presensis = \App\Models\Presensi::where('no_ktp', $pegawai->no_ktp)
    //             ->whereBetween('waktu_masuk', [$start->toDateString() . ' 00:00:00', $end->toDateString() . ' 23:59:59'])
    //             ->get();

    //         // Ambil pengajuan pada bulan tsb
    //         $izin = \App\Models\PengajuanIzin::where('pegawai_id', $pegawai->id)
    //             ->where('status', 'diterima')
    //             ->where(function ($q) use ($start, $end) {
    //                 $q->whereBetween('tanggal_mulai', [$start, $end])
    //                     ->orWhereBetween('tanggal_selesai', [$start, $end]);
    //             })->get();

    //         $cuti = \App\Models\PengajuanCuti::where('pegawai_id', $pegawai->id)
    //             ->where('status', 'diterima')
    //             ->where(function ($q) use ($start, $end) {
    //                 $q->whereBetween('tanggal_mulai', [$start, $end])
    //                     ->orWhereBetween('tanggal_selesai', [$start, $end]);
    //             })->get();

    //         $sakit = \App\Models\PengajuanSakit::where('pegawai_id', $pegawai->id)
    //             ->where('status', 'diterima')
    //             ->where(function ($q) use ($start, $end) {
    //                 $q->whereBetween('tanggal_mulai', [$start, $end])
    //                     ->orWhereBetween('tanggal_selesai', [$start, $end]);
    //             })->get();

    //         $agg = [
    //             'hadir' => 0,
    //             'izin' => 0,
    //             'sakit' => 0,
    //             'cuti' => 0,
    //             'tidak_hadir' => 0,
    //             'dinas' => 0,
    //             'lembur' => 0,
    //             'terlambat' => 0,
    //             'pulang_awal' => 0,
    //             'tidak_absen_masuk' => 0,
    //             'tidak_absen_pulang' => 0,
    //             'belum_presensi' => 0,
    //         ];

    //         // Loop setiap hari kerja efektif dalam bulan
    //         for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
    //             $carbon = $date->copy();

    //             // Skip weekend
    //             if ($carbon->isSaturday() || $carbon->isSunday()) {
    //                 continue;
    //             }

    //             // Skip hari libur unit
    //             $unit = $pegawai->pegawai->unitDetailPresensi->unit ?? null;
    //             if ($unit) {
    //                 $isHariLibur = \App\Models\HariLibur::isHariLibur($unit->id, $carbon->toDateString());
    //                 if ($isHariLibur) {
    //                     continue;
    //                 }
    //             }

    //             $tanggal = $carbon->format('Y-m-d');

    //             // Filter presensi hari ini
    //             $presensiHari = $presensis->filter(function ($p) use ($tanggal) {
    //                 return $p->waktu_masuk && \Carbon\Carbon::parse($p->waktu_masuk)->format('Y-m-d') === $tanggal;
    //             });

    //             // Metrik tambahan (dihitung bila ada presensi)
    //             if ($presensiHari->count()) {
    //                 if ($presensiHari->where('status_presensi', 'dinas')->count()) {
    //                     $agg['dinas']++;
    //                 }
    //                 if ($presensiHari->where('overtime', true)->count()) {
    //                     $agg['lembur']++;
    //                 }
    //                 if ($presensiHari->where('status_masuk', 'terlambat')->count()) {
    //                     $agg['terlambat']++;
    //                 }
    //                 if ($presensiHari->where('status_pulang', 'pulang_awal')->count()) {
    //                     $agg['pulang_awal']++;
    //                 }
    //                 if ($presensiHari->where('status_masuk', 'tidak_absen_masuk')->count()) {
    //                     $agg['tidak_absen_masuk']++;
    //                 }
    //                 if ($presensiHari->where('status_pulang', 'tidak_absen_pulang')->count()) {
    //                     $agg['tidak_absen_pulang']++;
    //                 }
    //             }

    //             // Status utama
    //             $status = null;
    //             if ($presensiHari->count()) {
    //                 if ($presensiHari->whereIn('status_presensi', ['hadir', 'dinas'])->count()) {
    //                     $status = 'hadir';
    //                 } elseif ($presensiHari->where('status_presensi', 'tidak_hadir')->count()) {
    //                     $status = 'tidak_hadir';
    //                 }
    //             }
    //             if (!$status || $status === 'tidak_hadir') {
    //                 foreach ($izin as $i) {
    //                     if ($tanggal >= $i->tanggal_mulai && $tanggal <= $i->tanggal_selesai) {
    //                         $status = 'izin';
    //                         break;
    //                     }
    //                 }
    //             }
    //             if (!$status || $status === 'tidak_hadir') {
    //                 foreach ($cuti as $c) {
    //                     if ($tanggal >= $c->tanggal_mulai && $tanggal <= $c->tanggal_selesai) {
    //                         $status = 'cuti';
    //                         break;
    //                     }
    //                 }
    //             }
    //             if (!$status || $status === 'tidak_hadir') {
    //                 foreach ($sakit as $s) {
    //                     if ($tanggal >= $s->tanggal_mulai && $tanggal <= $s->tanggal_selesai) {
    //                         $status = 'sakit';
    //                         break;
    //                     }
    //                 }
    //             }
    //             if (!$status) {
    //                 $status = 'belum_presensi';
    //             }
    //             if (isset($agg[$status])) {
    //                 $agg[$status]++;
    //             }
    //         }

    //         $result[] = array_merge([
    //             'bulan' => $bulan,
    //             'tahun' => $tahun,
    //         ], $agg);
    //     }

    //     return response()->json($result);
    // }

    public function rekapHistoryTahunanPegawai(Request $request)
    {
        $pegawaiId = $request->query('pegawai_id');

        if ($pegawaiId) {
            $pegawai = \App\Models\MsPegawai::with(['unitDetailPresensi.unit'])
                ->find($pegawaiId);

            if (!$pegawai) {
                return response()->json(['message' => 'Pegawai dengan ID ' . $pegawaiId . ' tidak ditemukan'], 404);
            }

            $pegawai->load('orang');
            $pegawai->no_ktp = $pegawai->orang->no_ktp ?? null;
        } else {
            $pegawai = $request->get('pegawai');

            if (!$pegawai) {
                return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
            }

            $pegawai->load(['pegawai.unitDetailPresensi.unit', 'pegawai']);
        }
        $tahun = (int) $request->query('tahun', now('Asia/Jakarta')->year);

        $result = [];

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $start = \Carbon\Carbon::create($tahun, $bulan, 1, 0, 0, 0, 'Asia/Jakarta');
            $end = $start->copy()->endOfMonth();

            // Ambil presensi berdasarkan waktu_masuk
            $presensis = \App\Models\Presensi::where('no_ktp', $pegawai->no_ktp)
                ->whereBetween('waktu_masuk', [$start->toDateString() . ' 00:00:00', $end->toDateString() . ' 23:59:59'])
                ->orderBy('waktu_masuk')
                ->get();

            // Ambil pengajuan
            $izin = \App\Models\PengajuanIzin::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->get();

            $cuti = \App\Models\PengajuanCuti::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->get();

            $sakit = \App\Models\PengajuanSakit::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->get();

            $agg = [
                'hadir' => 0,
                'izin' => 0,
                'sakit' => 0,
                'cuti' => 0,
                'tidak_hadir' => 0,
                'dinas' => 0,
                'lembur' => 0,
                'terlambat' => 0,
                'pulang_awal' => 0,
                'tidak_absen_masuk' => 0,
                'tidak_absen_pulang' => 0,
                'belum_presensi' => 0,
            ];


            $hariEfektif = 0;
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $carbon = $date->copy();

                // Skip weekend
                if ($carbon->isSaturday() || $carbon->isSunday()) continue;

                // Skip libur unit
                $unit = $pegawai->pegawai->unitDetailPresensi->unit ?? null;
                if ($unit) {
                    $isHariLibur = \App\Models\HariLibur::isHariLibur($unit->id, $carbon->toDateString());
                    if ($isHariLibur) continue;
                }
                $hariEfektif++;

                $tanggal = $carbon->format('Y-m-d');

                // Presensi hari ini (bisa >1)
                $presensiHari = $presensis->filter(function ($p) use ($tanggal) {
                    return $p->waktu_masuk && \Carbon\Carbon::parse($p->waktu_masuk)->format('Y-m-d') === $tanggal;
                });

                $status = null;

                // PRIORITAS sama seperti bulanan
                if ($presensiHari->count() && $presensiHari->where('status_presensi', 'dinas')->count()) {
                    $status = 'dinas';
                } elseif ($presensiHari->count() && $presensiHari->where('overtime', true)->count()) {
                    $status = 'lembur';
                } elseif ($presensiHari->count() && $presensiHari->where('status_masuk', 'terlambat')->count()) {
                    $status = 'terlambat';
                } elseif ($presensiHari->count() && $presensiHari->where('status_masuk', 'tidak_absen_masuk')->count()) {
                    $status = 'tidak_absen_masuk';
                } elseif ($presensiHari->count() && $presensiHari->where('status_pulang', 'pulang_awal')->count()) {
                    $status = 'pulang_awal';
                } elseif (
                    $presensiHari->count() &&
                    (
                        $presensiHari->where('status_pulang', 'tidak_absen_pulang')->count()
                        || $presensiHari->whereNull('status_pulang')->count()
                    )
                ) {
                    $status = 'tidak_absen_pulang';
                } else {
                    if (
                        $presensiHari->count()
                        && $presensiHari->where('status_masuk', 'absen_masuk')->count()
                        && $presensiHari->where('status_pulang', 'absen_pulang')->count()
                    ) {
                        $status = 'hadir';
                    }
                }

                // Cek izin/cuti/sakit bila belum ada status
                if (!$status) {
                    foreach ($izin as $i) {
                        if ($tanggal >= $i->tanggal_mulai && $tanggal <= $i->tanggal_selesai) {
                            $status = 'izin';
                            break;
                        }
                    }
                }
                if (!$status) {
                    foreach ($cuti as $c) {
                        if ($tanggal >= $c->tanggal_mulai && $tanggal <= $c->tanggal_selesai) {
                            $status = 'cuti';
                            break;
                        }
                    }
                }
                if (!$status) {
                    foreach ($sakit as $s) {
                        if ($tanggal >= $s->tanggal_mulai && $tanggal <= $s->tanggal_selesai) {
                            $status = 'sakit';
                            break;
                        }
                    }
                }

                // Kalau tetap null -> bedakan tidak_hadir vs belum_presensi
                if (!$status) {
                    if ($carbon->lte(now('Asia/Jakarta')->startOfDay())) {
                        $status = 'tidak_hadir';
                    } else {
                        $status = 'belum_presensi';
                    }
                }

                if (isset($agg[$status])) {
                    $agg[$status]++;
                }
            }

            $result[] = array_merge([
                'bulan' => $bulan,
                'tahun' => $tahun,
                'hari_efektif' => $hariEfektif
            ], $agg);
        }

        return response()->json($result);
    }



    public function historyByAdminUnit(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $query = "
            SELECT id, id_orang, no_ktp, nama
            FROM sdi.v_pegawai
            WHERE id_unit = ?
        ";

        $params = [$unitId];

        if ($unitId == 1) {
            $query .= " OR terbantukan = 1";
        }

        $pegawais = collect(DB::select($query, $params));


        // echo json_encode($pegawais);
        // exit();

        $no_ktps = $pegawais->pluck('no_ktp')->toArray();

        if (empty($no_ktps)) {
            return response()->json([]);
        }

        $pegawaiMap = $pegawais->mapWithKeys(function ($pegawai) {
            return [$pegawai->no_ktp => $pegawai];
        });

        $query = Presensi::whereIn('no_ktp', $no_ktps);

        $bulan = $request->query('bulan');
        $tanggal = $request->query('tanggal');

        if ($tanggal) {
            $query->whereDate('waktu_masuk', $tanggal);
        } elseif ($bulan) {
            try {
                $start = Carbon::parse($bulan . '-01')->startOfMonth();
                $end = Carbon::parse($bulan . '-01')->endOfMonth();
                $query->whereBetween('waktu_masuk', [$start, $end]);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Format bulan tidak valid. Gunakan YYYY-MM'], 400);
            }
        } else {
            $query->whereDate('waktu_masuk', Carbon::today()->toDateString());
        }

        $presensis = $query->with('shiftDetail')->orderBy('waktu_masuk', 'desc')->get();

        $result = $presensis->map(function ($p) use ($pegawaiMap) {
            $pegawai = $pegawaiMap[$p->no_ktp] ?? null;

            $status_final = $p->status_presensi;
            if ($status_final == null) {
                if ($p->status_masuk == 'terlambat') {
                    $status_final = 'terlambat';
                } else if ($p->status_masuk == 'tidak_absen_masuk') {
                    $status_final = 'tidak_absen_masuk';
                } else if ($p->status_pulang == 'tidak_absen_pulang') {
                    $status_final = 'tidak_absen_pulang';
                } else if ($p->status_masuk == 'absen_masuk' && $p->waktu_pulang == null) {
                    $isPassed = false;
                    if ($p->waktu_masuk->isBefore(Carbon::now('Asia/Jakarta')->startOfDay())) {
                        $isPassed = true;
                    } elseif ($p->shiftDetail) {
                        $hari = strtolower($p->waktu_masuk->locale('id')->isoFormat('dddd'));
                        $jamPulang = $p->shiftDetail->{$hari . '_pulang'};
                        if ($jamPulang && Carbon::now('Asia/Jakarta')->format('H:i:s') > $jamPulang) {
                            $isPassed = true;
                        }
                    }

                    if ($isPassed) {
                        $status_final = 'tidak_absen_pulang';
                    }
                }
            }

            return [
                'id'               => $p->id,
                'no_ktp'           => $p->no_ktp,
                'nama'             => $pegawai?->nama,
                'status_masuk'     => $p->status_masuk,
                'status_pulang'    => $p->status_pulang,
                'status_presensi'  => $status_final,
                'waktu_masuk'      => $p->waktu_masuk?->timezone(config('app.timezone'))->toDateTimeString(),
                'waktu_pulang'     => $p->waktu_pulang?->timezone(config('app.timezone'))->toDateTimeString(),
                'keterangan_masuk' => $p->keterangan_masuk,
                'keterangan_pulang' => $p->keterangan_pulang,
                'created_at'       => $p->created_at,
                'updated_at'       => $p->updated_at,
            ];
        });

        return response()->json($result);
    }


    /**
     * Rekap history presensi pegawai per bulan (pegawai yang login)
     */
    // public function rekapHistoryBulananPegawai(Request $request)
    // {
    //     $pegawai = $request->get('pegawai');
    //     $pegawai->load([
    //         'pegawai.shiftDetail.shift',
    //         'pegawai.unitDetailPresensi.unit',
    //         'pegawai'
    //     ]);
    //     if (!$pegawai) {
    //         return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
    //     }

    //     $bulan = $request->query('bulan', now('Asia/Jakarta')->month);
    //     $tahun = $request->query('tahun', now('Asia/Jakarta')->year);

    //     // Ambil semua tanggal di bulan tsb
    //     $start = \Carbon\Carbon::create($tahun, $bulan, 1, 0, 0, 0, 'Asia/Jakarta');
    //     $end = $start->copy()->endOfMonth();

    //     $tanggalList = [];
    //     for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
    //         $tanggalList[] = $date->format('Y-m-d');
    //     }
    //     $hariEfektif = 0;
    //     foreach ($tanggalList as $tanggal) {
    //         $carbon = \Carbon\Carbon::parse($tanggal);

    //         if ($carbon->isSaturday() || $carbon->isSunday()) {
    //             continue;
    //         }

    //         $isHariLibur = \App\Models\HariLibur::isHariLibur($pegawai->unitDetailPresensi->unit->id, $carbon->toDateString());
    //         if ($isHariLibur) {
    //             continue;
    //         }

    //         $hariEfektif++;
    //     }

    //     // Ambil presensi pegawai di bulan tsb
    //     $presensi = \App\Models\Presensi::where('no_ktp', $pegawai->no_ktp)
    //         ->whereBetween('waktu_masuk', [$start->toDateString() . ' 00:00:00', $end->toDateString() . ' 23:59:59'])
    //         ->orderBy('waktu_masuk')
    //         ->get();

    //     // Ambil pengajuan izin, cuti, sakit
    //     $izin = \App\Models\PengajuanIzin::where('pegawai_id', $pegawai->id)
    //         ->where('status', 'diterima')
    //         ->where(function ($q) use ($start, $end) {
    //             $q->whereBetween('tanggal_mulai', [$start, $end])
    //                 ->orWhereBetween('tanggal_selesai', [$start, $end]);
    //         })->get();

    //     $cuti = \App\Models\PengajuanCuti::where('pegawai_id', $pegawai->id)
    //         ->where('status', 'diterima')
    //         ->where(function ($q) use ($start, $end) {
    //             $q->whereBetween('tanggal_mulai', [$start, $end])
    //                 ->orWhereBetween('tanggal_selesai', [$start, $end]);
    //         })->get();

    //     $sakit = \App\Models\PengajuanSakit::where('pegawai_id', $pegawai->id)
    //         ->where('status', 'diterima')
    //         ->where(function ($q) use ($start, $end) {
    //             $q->whereBetween('tanggal_mulai', [$start, $end])
    //                 ->orWhereBetween('tanggal_selesai', [$start, $end]);
    //         })->get();

    //     // Inisialisasi result
    //     $result = [
    //         'hadir' => 0,
    //         'izin' => 0,
    //         'sakit' => 0,
    //         'cuti' => 0,
    //         'tidak_hadir' => 0,
    //         'dinas' => 0,
    //         'lembur' => 0,
    //         'terlambat' => 0,
    //         'pulang_awal' => 0,
    //         'tidak_absen_masuk' => 0,
    //         'tidak_absen_pulang' => 0,
    //         'belum_presensi' => 0,
    //         'tanggal_hadir' => [],
    //         'tanggal_izin' => [],
    //         'tanggal_sakit' => [],
    //         'tanggal_cuti' => [],
    //         'tanggal_tidak_hadir' => [],
    //         'tanggal_dinas' => [],
    //         'tanggal_lembur' => [],
    //         'tanggal_terlambat' => [],
    //         'tanggal_pulang_awal' => [],
    //         'tanggal_tidak_absen_masuk' => [],
    //         'tanggal_tidak_absen_pulang' => [],
    //         'tanggal_belum_presensi' => [],
    //         'bulan' => (string)$bulan,
    //         'tahun' => (string)$tahun,
    //         'hari_efektif' => $hariEfektif
    //     ];

    //     foreach ($tanggalList as $tanggal) {
    //         $carbon = \Carbon\Carbon::parse($tanggal);

    //         // Skip kalau weekend atau libur, jadi tidak dihitung "belum presensi"
    //         if ($carbon->isSaturday() || $carbon->isSunday()) {
    //             continue;
    //         }

    //         $isHariLibur = \App\Models\HariLibur::isHariLibur($pegawai->unitDetailPresensi->unit->id, $carbon->toDateString());
    //         if ($isHariLibur) {
    //             continue;
    //         }

    //         $status = null;

    //         // Cek presensi (safe null check)
    //         $presensiHari = $presensi->filter(function ($p) use ($tanggal) {
    //             return $p->waktu_masuk && \Carbon\Carbon::parse($p->waktu_masuk)->format('Y-m-d') === $tanggal;
    //         });

    //         if ($presensiHari->count()) {
    //             $dayString = $carbon->format('d');

    //             if ($presensiHari->where('status_presensi', 'dinas')->count()) {
    //                 $result['dinas']++;
    //                 $result['tanggal_dinas'][] = $dayString;
    //             }

    //             if ($presensiHari->where('overtime', true)->count()) {
    //                 $result['overtime']++;
    //                 $result['tanggal_overtime'][] = $dayString;
    //             }

    //             if ($presensiHari->where('status_masuk', 'terlambat')->count()) {
    //                 $result['terlambat']++;
    //                 $result['tanggal_terlambat'][] = $dayString;
    //             }

    //             if ($presensiHari->where('status_pulang', 'pulang_awal')->count()) {
    //                 $result['pulang_awal']++;
    //                 $result['tanggal_pulang_awal'][] = $dayString;
    //             }

    //             if ($presensiHari->where('status_masuk', 'tidak_absen_masuk')->count()) {
    //                 $result['tidak_absen_masuk']++;
    //                 $result['tanggal_tidak_absen_masuk'][] = $dayString;
    //             }

    //             if ($presensiHari->where('status_pulang', 'tidak_absen_pulang')->count()) {
    //                 $result['tidak_absen_pulang']++;
    //                 $result['tanggal_tidak_absen_pulang'][] = $dayString;
    //             }
    //         }

    //         if ($presensiHari->count()) {
    //             if ($presensiHari->whereIn('status_presensi', ['hadir', 'dinas'])->count()) {
    //                 $status = 'hadir';
    //             } elseif ($presensiHari->where('status_presensi', 'tidak_hadir')->count()) {
    //                 $status = 'tidak_hadir';
    //             }
    //         }

    //         // Cek izin
    //         if (!$status || $status === 'tidak_hadir') {
    //             foreach ($izin as $i) {
    //                 if ($tanggal >= $i->tanggal_mulai && $tanggal <= $i->tanggal_selesai) {
    //                     $status = 'izin';
    //                     break;
    //                 }
    //             }
    //         }

    //         // Cek cuti
    //         if (!$status || $status === 'tidak_hadir') {
    //             foreach ($cuti as $c) {
    //                 if ($tanggal >= $c->tanggal_mulai && $tanggal <= $c->tanggal_selesai) {
    //                     $status = 'cuti';
    //                     break;
    //                 }
    //             }
    //         }

    //         // Cek sakit
    //         if (!$status || $status === 'tidak_hadir') {
    //             foreach ($sakit as $s) {
    //                 if ($tanggal >= $s->tanggal_mulai && $tanggal <= $s->tanggal_selesai) {
    //                     $status = 'sakit';
    //                     break;
    //                 }
    //             }
    //         }

    //         if (!$status) {
    //             $status = 'belum_presensi';
    //         }

    //         // Hitung jumlah & simpan tanggal
    //         if (isset($result[$status])) {
    //             $result[$status]++;
    //         }
    //         $result['tanggal_' . $status][] = $carbon->format('d');
    //     }


    //     return response()->json($result);
    // }

    // REKAP BULANAN STATUS TERPISAH
    public function rekapHistoryBulananPegawai(Request $request)
    {
        $pegawai = $request->get('pegawai');
        $pegawai->load([
            'pegawai.shiftDetail.shift',
            'pegawai.unitDetailPresensi.unit',
            'pegawai'
        ]);
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $bulan = $request->query('bulan', now('Asia/Jakarta')->month);
        $tahun = $request->query('tahun', now('Asia/Jakarta')->year);

        // Ambil semua tanggal di bulan tsb
        $start = \Carbon\Carbon::create($tahun, $bulan, 1, 0, 0, 0, 'Asia/Jakarta');
        $end = $start->copy()->endOfMonth();

        $tanggalList = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $tanggalList[] = $date->format('Y-m-d');
        }

        // Hitung hari efektif
        $hariEfektif = 0;
        foreach ($tanggalList as $tanggal) {
            $carbon = \Carbon\Carbon::parse($tanggal);
            if ($carbon->isSaturday() || $carbon->isSunday()) continue;
            if (\App\Models\HariLibur::isHariLibur($pegawai->unitDetailPresensi->unit->id, $carbon->toDateString())) continue;
            $hariEfektif++;
        }

        // Ambil presensi pegawai di bulan tsb (pakai waktu_masuk)
        $presensi = \App\Models\Presensi::where('no_ktp', $pegawai->no_ktp)
            ->whereBetween('waktu_masuk', [$start->toDateString() . ' 00:00:00', $end->toDateString() . ' 23:59:59'])
            ->orderBy('waktu_masuk')
            ->get();

        // Ambil pengajuan izin, cuti, sakit
        $izin = \App\Models\PengajuanIzin::where('pegawai_id', $pegawai->id)
            ->where('status', 'diterima')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('tanggal_mulai', [$start, $end])
                    ->orWhereBetween('tanggal_selesai', [$start, $end]);
            })->get();

        $cuti = \App\Models\PengajuanCuti::where('pegawai_id', $pegawai->id)
            ->where('status', 'diterima')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('tanggal_mulai', [$start, $end])
                    ->orWhereBetween('tanggal_selesai', [$start, $end]);
            })->get();

        $sakit = \App\Models\PengajuanSakit::where('pegawai_id', $pegawai->id)
            ->where('status', 'diterima')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('tanggal_mulai', [$start, $end])
                    ->orWhereBetween('tanggal_selesai', [$start, $end]);
            })->get();

        // Inisialisasi result (tetap sama struktur)
        $result = [
            'hadir' => 0,
            'izin' => 0,
            'sakit' => 0,
            'cuti' => 0,
            'tidak_hadir' => 0,
            'dinas' => 0,
            'lembur' => 0,
            'terlambat' => 0,
            'pulang_awal' => 0,
            'tidak_absen_masuk' => 0,
            'tidak_absen_pulang' => 0,
            'belum_presensi' => 0,
            'tanggal_hadir' => [],
            'tanggal_izin' => [],
            'tanggal_sakit' => [],
            'tanggal_cuti' => [],
            'tanggal_tidak_hadir' => [],
            'tanggal_dinas' => [],
            'tanggal_lembur' => [],
            'tanggal_terlambat' => [],
            'tanggal_pulang_awal' => [],
            'tanggal_tidak_absen_masuk' => [],
            'tanggal_tidak_absen_pulang' => [],
            'tanggal_belum_presensi' => [],
            'bulan' => (string)$bulan,
            'tahun' => (string)$tahun,
            'hari_efektif' => $hariEfektif
        ];

        foreach ($tanggalList as $tanggal) {
            $carbon = \Carbon\Carbon::parse($tanggal);

            // Skip weekend/libur
            if ($carbon->isSaturday() || $carbon->isSunday()) continue;
            if (\App\Models\HariLibur::isHariLibur($pegawai->unitDetailPresensi->unit->id, $carbon->toDateString())) continue;

            $status = null;
            $dayString = $carbon->format('d');

            // Kumpulkan presensi hari itu (bisa >1 row)
            $presensiHari = $presensi->filter(function ($p) use ($tanggal) {
                return $p->waktu_masuk && \Carbon\Carbon::parse($p->waktu_masuk)->format('Y-m-d') === $tanggal;
            });

            // PRIORITAS:
            // 1) dinas
            // 1) dinas
            if ($presensiHari->where('status_presensi', 'dinas')->count()) {
                $status = 'dinas';
                $result['tanggal_dinas'][] = $dayString;
            }
            // 2) lembur
            elseif ($presensiHari->where('overtime', true)->count()) {
                $status = 'lembur';
                $result['tanggal_lembur'][] = $dayString;
            }
            // 3) kalau ada absen_masuk
            elseif ($presensiHari->where('status_masuk', 'absen_masuk')->count()) {
                if ($presensiHari->where('status_pulang', 'pulang_awal')->count()) {
                    $status = 'pulang_awal';
                    $result['tanggal_pulang_awal'][] = $dayString;
                } elseif ($presensiHari->where('status_pulang', 'tidak_absen_pulang')->count()) {
                    $status = 'tidak_absen_pulang';
                    $result['tanggal_tidak_absen_pulang'][] = $dayString;
                } elseif ($presensiHari->where('status_pulang', 'absen_pulang')->count()) {
                    $status = 'hadir';
                    $result['tanggal_hadir'][] = $dayString;
                } else {
                    // status_pulang = null → dianggap tidak_absen_pulang
                    $status = 'tidak_absen_pulang';
                    $result['tanggal_tidak_absen_pulang'][] = $dayString;
                }
            }

            // 4) kalau status_masuk = terlambat
            elseif ($presensiHari->where('status_masuk', 'terlambat')->count()) {
                $status = 'terlambat';
                $result['tanggal_terlambat'][] = $dayString;
            }
            // 5) kalau status_masuk = tidak_absen_masuk
            elseif ($presensiHari->where('status_masuk', 'tidak_absen_masuk')->count()) {
                $status = 'tidak_absen_masuk';
                $result['tanggal_tidak_absen_masuk'][] = $dayString;
            }


            // Kalau tidak ada status dari presensi -> cek izin/cuti/sakit
            if (!$status) {
                foreach ($izin as $i) {
                    if ($tanggal >= $i->tanggal_mulai && $tanggal <= $i->tanggal_selesai) {
                        $status = 'izin';
                        $result['tanggal_izin'][] = $dayString;
                        break;
                    }
                }
            }
            if (!$status) {
                foreach ($cuti as $c) {
                    if ($tanggal >= $c->tanggal_mulai && $tanggal <= $c->tanggal_selesai) {
                        $status = 'cuti';
                        $result['tanggal_cuti'][] = $dayString;
                        break;
                    }
                }
            }
            if (!$status) {
                foreach ($sakit as $s) {
                    if ($tanggal >= $s->tanggal_mulai && $tanggal <= $s->tanggal_selesai) {
                        $status = 'sakit';
                        $result['tanggal_sakit'][] = $dayString;
                        break;
                    }
                }
            }

            // Kalau tetap null → bedakan tidak_hadir vs belum_presensi
            if (!$status) {
                if ($carbon->lte(now('Asia/Jakarta')->startOfDay())) {
                    $status = 'tidak_hadir';
                    $result['tanggal_tidak_hadir'][] = $dayString;
                } else {
                    $status = 'belum_presensi';
                    $result['tanggal_belum_presensi'][] = $dayString;
                }
            }

            // Tambah hitungan status utama (satu status per hari)
            if (isset($result[$status])) {
                $result[$status]++;
            }
        }

        return response()->json($result);
    }



    /**
     * Detail history presensi pegawai di unit detail tertentu (admin unit)
     * Bisa filter by pegawai, dan update presensi oleh admin unit
     * Menampilkan data presensi berpasangan (masuk dan pulang) dalam satu hari
     */
    public function detailHistoryByAdminUnit(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }


        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $unit_detail_id = $request->query('unit_detail_id');
        $id_orang = $request->query('pegawai_id');
        $from = $request->query('from');
        $to = $request->query('to');

        // $pegawaiQuery = \App\Models\MsPegawai::whereHas('unitDetailPresensi', function ($q) use ($unitId, $unit_detail_id) {
        //     $q->where('ms_unit_id', $unitId);
        //     if ($unit_detail_id) {
        //         $q->where('id', $unit_detail_id);
        //     }
        // });
        $pegawaiQuery = "
    SELECT p.*, u.nama as nama_unit 
    FROM sdi.v_pegawai p
    JOIN sdi.ms_unit u ON p.id_unit = u.id
    WHERE (p.id_unit = ?
";
        $params = [$unitId];

        if ($unitId == 1) {
            $pegawaiQuery .= " OR p.terbantukan = 1";
        }

        $pegawaiQuery .= ")";

        if ($id_orang) {
            $pegawaiQuery .= " AND p.id_orang = ?";
            $params[] = $id_orang;
        }

        $pegawais = DB::select($pegawaiQuery, $params);



        $result = [];
        foreach ($pegawais as $pegawai) {
            $presensiQuery = \App\Models\Presensi::where('no_ktp', $pegawai->no_ktp);
            if ($from) {
                $presensiQuery->whereDate('waktu_masuk', '>=', $from);
            }
            if ($to) {
                $presensiQuery->whereDate('waktu_masuk', '<=', $to);
            }
            // Menggunakan format baru - 1 row per hari
            $presensi = $presensiQuery->orderBy('waktu_masuk', 'asc')->get();

            $presensiBerpasangan = [];
            foreach ($presensi as $p) {
                $presensiBerpasangan[] = [
                    'tanggal' => $p->waktu_masuk->format('Y-m-d'),
                    'hari' => $p->waktu_masuk->locale('id')->isoFormat('dddd'),
                    'status_presensi' => $p->status_presensi,
                    'masuk' => [
                        'id' => $p->id,
                        'waktu' => $p->waktu_masuk->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('H:i:s'),
                        'status' => $p->status_masuk,
                        'lokasi' => $p->lokasi_masuk,
                        'keterangan' => $p->keterangan_masuk,
                        'created_at' => $p->created_at->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s'),
                        'updated_at' => $p->updated_at->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s'),
                    ],
                    'pulang' => $p->waktu_pulang ? [
                        'id' => $p->id,
                        'waktu' => $p->waktu_pulang->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('H:i:s'),
                        'status' => $p->status_pulang,
                        'lokasi' => $p->lokasi_pulang,
                        'keterangan' => $p->keterangan_pulang,
                        'created_at' => $p->created_at->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s'),
                        'updated_at' => $p->updated_at->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s'),
                    ] : null,
                ];
            }


            $unitDetailName = null;
            if ($pegawai->presensi_ms_unit_detail_id) {
                $unitDetail = \App\Models\UnitDetail::find($pegawai->presensi_ms_unit_detail_id);
                $unitDetailName = $unitDetail?->nama;
            }


            $result[] = [
                'pegawai' => [
                    'id' => $pegawai->id,
                    'no_ktp' => $pegawai->no_ktp,
                    'nama' => $pegawai->nama,
                    'unit_detail_name' => $pegawai->nama_unit
                ],
                'presensi' => $presensiBerpasangan,
            ];
        }
        return response()->json($result);
    }

    /**
     * Update presensi pegawai secara bulk oleh admin unit
     */
    public function updatePresensiByAdminUnitBulk(Request $request, $pegawai_id, $tanggal)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $updates = $request->input('updates');
        if (!$pegawai_id || !$tanggal || !is_array($updates)) {
            return response()->json(['message' => 'pegawai_id, tanggal, dan updates wajib diisi'], 422);
        }

        // Validasi format tanggal
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return response()->json(['message' => 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD'], 422);
        }
        $pegawai = MsPegawai::with('orang')->where('id_orang', $pegawai_id)->firstOrFail();


        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        // // Validasi pegawai milik unit admin
        // if (!$pegawai->unitDetailPresensi || $pegawai->unitDetailPresensi->unit_id != $unitId) {
        //     return response()->json(['message' => 'Tidak memiliki akses edit presensi pegawai ini'], 403);
        // }

        // Menggunakan format baru - 1 row per hari
        $presensi = \App\Models\Presensi::where('no_ktp', $pegawai->orang->no_ktp)
            ->whereDate('waktu_masuk', $tanggal)
            ->first();
        if (!$presensi) {
            return response()->json(['message' => 'Tidak ada presensi pada tanggal tersebut'], 404);
        }
        $updated = [];
        foreach ($updates as $update) {
            $statusMasuk = $update['status_masuk'] ?? null;
            $statusPulang = $update['status_pulang'] ?? null;
            $waktuMasuk = $update['waktu_masuk'] ?? null;
            $waktuPulang = $update['waktu_pulang'] ?? null;


            if ($statusMasuk && !\App\Models\Presensi::isValidStatusMasuk($statusMasuk)) {
                return response()->json(['message' => 'Status masuk tidak valid'], 422);
            }

            // Validasi status pulang
            if ($statusPulang && !\App\Models\Presensi::isValidStatusPulang($statusPulang)) {
                return response()->json(['message' => 'Status pulang tidak valid'], 422);
            }

            // Konversi input waktu jika dalam format jam (HH:mm)
            if ($waktuMasuk && !str_contains($waktuMasuk, '-') && !str_contains($waktuMasuk, 'T')) {
                // Validasi format jam (HH:mm)
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $waktuMasuk)) {
                    return response()->json(['message' => 'Format waktu masuk tidak valid. Gunakan format HH:mm'], 422);
                }
                $waktuMasuk = $tanggal . ' ' . $waktuMasuk . ':00';
            }

            if ($waktuPulang && !str_contains($waktuPulang, '-') && !str_contains($waktuPulang, 'T')) {
                // Validasi format jam (HH:mm)
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $waktuPulang)) {
                    return response()->json(['message' => 'Format waktu pulang tidak valid. Gunakan format HH:mm'], 422);
                }
                $waktuPulang = $tanggal . ' ' . $waktuPulang . ':00';
            }

            // Validasi logika waktu (waktu pulang harus setelah waktu masuk)
            if ($waktuMasuk && $waktuPulang) {
                $waktuMasukObj = \Carbon\Carbon::parse($waktuMasuk);
                $waktuPulangObj = \Carbon\Carbon::parse($waktuPulang);

                if ($waktuPulangObj->lte($waktuMasukObj)) {
                    return response()->json(['message' => 'Waktu pulang harus setelah waktu masuk'], 422);
                }
            }

            $updateData = array_filter([
                'status_masuk' => $statusMasuk,
                'status_pulang' => $statusPulang,
                'waktu_masuk' => $waktuMasuk,
                'waktu_pulang' => $waktuPulang,
                'lokasi_masuk' => $update['lokasi_masuk'] ?? null,
                'lokasi_pulang' => $update['lokasi_pulang'] ?? null,
                'keterangan_masuk' => $update['keterangan_masuk'] ?? null,
                'keterangan_pulang' => $update['keterangan_pulang'] ?? null,
                'status_presensi' => $update['status_presensi'] ?? null,
            ], fn($v) => $v !== null);

            // Recalculate status_presensi jika ada perubahan status
            if ($statusMasuk || $statusPulang) {
                $updateData['status_presensi'] = $this->calculateFinalStatus(
                    $statusMasuk ?? $presensi->status_masuk,
                    $statusPulang ?? $presensi->status_pulang
                );
            }

            $presensi->update($updateData);
            $updated[] = $presensi;
        }
        return response()->json([
            'message' => 'Presensi berhasil diupdate',
            'updated' => $updated
        ]);
    }

    public function rekapBulananUnitByAdmin(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }


        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $tahun = $request->query('tahun', now('Asia/Jakarta')->year);
        $bulanSekarang = now('Asia/Jakarta')->month;
        $result = [];
        $namaBulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];
        for ($bulan = 1; $bulan <= $bulanSekarang; $bulan++) {
            // Ambil semua pegawai di unit admin
            $pegawais = \App\Models\MsPegawai::whereHas('unitDetailPresensi', function ($q) use ($unitId) {
                $q->where('ms_unit_id', $unitId);
            })
                ->whereHas('orang')
                ->get();
            $rekapBulan = [
                'hadir' => 0,
                'izin' => 0,
                'sakit' => 0,
                'cuti' => 0,
                'tidak_hadir' => 0,
                'belum_presensi' => 0
            ];
            $start = \Carbon\Carbon::create($tahun, $bulan, 1, 0, 0, 0, 'Asia/Jakarta');
            $end = $start->copy()->endOfMonth();
            $jumlahHari = $end->day;
            foreach ($pegawais as $pegawai) {
                // Ambil presensi pegawai di bulan tsb (format baru)
                $presensi = \App\Models\Presensi::where('no_ktp', $pegawai->no_ktp)
                    ->whereBetween('waktu_masuk', [$start->toDateString() . ' 00:00:00', $end->toDateString() . ' 23:59:59'])
                    ->orderBy('waktu_masuk')
                    ->get();
                $izin = \App\Models\PengajuanIzin::where('pegawai_id', $pegawai->id)
                    ->where('status', 'diterima')
                    ->where(function ($q) use ($start, $end) {
                        $q->whereBetween('tanggal_mulai', [$start, $end])
                            ->orWhereBetween('tanggal_selesai', [$start, $end]);
                    })->get();
                $cuti = \App\Models\PengajuanCuti::where('pegawai_id', $pegawai->id)
                    ->where('status', 'diterima')
                    ->where(function ($q) use ($start, $end) {
                        $q->whereBetween('tanggal_mulai', [$start, $end])
                            ->orWhereBetween('tanggal_selesai', [$start, $end]);
                    })->get();
                $sakit = \App\Models\PengajuanSakit::where('pegawai_id', $pegawai->id)
                    ->where('status', 'diterima')
                    ->where(function ($q) use ($start, $end) {
                        $q->whereBetween('tanggal_mulai', [$start, $end])
                            ->orWhereBetween('tanggal_selesai', [$start, $end]);
                    })->get();
                for ($hari = 1; $hari <= $jumlahHari; $hari++) {
                    $tanggal = $start->copy()->day($hari)->format('Y-m-d');
                    $status = null;
                    $presensiHari = $presensi->where(fn($p) => $p->waktu_masuk->format('Y-m-d') === $tanggal);
                    if ($presensiHari->count()) {
                        if ($presensiHari->where('status_presensi', 'hadir')->count()) {
                            $status = 'hadir';
                        } elseif ($presensiHari->where('status_presensi', 'tidak_hadir')->count()) {
                            $status = 'tidak_hadir';
                        } else {
                            $status = 'lain';
                        }
                    }
                    if (!$status || $status === 'tidak_hadir' || $status === 'lain') {
                        foreach ($izin as $i) {
                            if ($tanggal >= $i->tanggal_mulai && $tanggal <= $i->tanggal_selesai) {
                                $status = 'izin';
                                break;
                            }
                        }
                    }
                    if (!$status || $status === 'tidak_hadir' || $status === 'lain') {
                        foreach ($cuti as $c) {
                            if ($tanggal >= $c->tanggal_mulai && $tanggal <= $c->tanggal_selesai) {
                                $status = 'cuti';
                                break;
                            }
                        }
                    }
                    if (!$status || $status === 'tidak_hadir' || $status === 'lain') {
                        foreach ($sakit as $s) {
                            if ($tanggal >= $s->tanggal_mulai && $tanggal <= $s->tanggal_selesai) {
                                $status = 'sakit';
                                break;
                            }
                        }
                    }
                    if (!$status) {
                        $status = 'belum_presensi';
                    }
                    if (isset($rekapBulan[$status])) {
                        $rekapBulan[$status]++;
                    }
                }
            }
            $result[] = array_merge(['bulan' => $namaBulan[$bulan]], $rekapBulan);
        }
        return response()->json($result);
    }

    public function rekapBulananByPegawai(Request $request)
    {
        $pegawai_id = $request->query('pegawai_id');
        $tahun = $request->query('tahun', now('Asia/Jakarta')->year);
        $bulanSekarang = now('Asia/Jakarta')->month;

        $pegawai = \App\Models\MsPegawai::with('orang')->where('id_orang', $pegawai_id)->first();
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 404);
        }

        $result = [];
        $namaBulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        for ($bulan = 1; $bulan <= $bulanSekarang; $bulan++) {
            $rekapBulan = [
                'hadir' => 0,
                'izin' => 0,
                'sakit' => 0,
                'cuti' => 0,
                'tidak_hadir' => 0,
                'dinas' => 0,
                'lembur' => 0,
                'terlambat' => 0,
                'pulang_awal' => 0,
                'tidak_absen_masuk' => 0,
                'tidak_absen_pulang' => 0,
                'belum_presensi' => 0
            ];

            $start = \Carbon\Carbon::create($tahun, $bulan, 1, 0, 0, 0, 'Asia/Jakarta');
            $end = $start->copy()->endOfMonth();

            // Hitung hari efektif (Senin–Jumat, Sabtu & Minggu skip)
            $hariEfektif = 0;
            $tanggalEfektif = [];
            for ($hari = 1; $hari <= $end->day; $hari++) {
                $tgl = $start->copy()->day($hari);
                if ($tgl->isSaturday() || $tgl->isSunday()) {
                    continue; // skip Sabtu & Minggu
                }
                $hariEfektif++;
                $tanggalEfektif[] = $tgl->format('Y-m-d');
            }


            $presensi = \App\Models\Presensi::where('no_ktp', $pegawai->orang->no_ktp)
                ->whereBetween('waktu_masuk', [$start->toDateString() . ' 00:00:00', $end->toDateString() . ' 23:59:59'])
                ->orderBy('waktu_masuk')
                ->get();

            $izin = \App\Models\PengajuanIzin::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->get();

            $cuti = \App\Models\PengajuanCuti::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->get();

            $sakit = \App\Models\PengajuanSakit::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->get();

            // Loop hanya hari efektif
            foreach ($tanggalEfektif as $tanggal) {
                $status = null;
                $presensiHari = $presensi->filter(fn($p) => \Carbon\Carbon::parse($p->waktu_masuk)->format('Y-m-d') === $tanggal);

                if ($presensiHari->count()) {
                    if ($presensiHari->where('status_presensi', 'dinas')->count()) {
                        $status = 'dinas';
                    } elseif ($presensiHari->where('overtime', true)->count()) {
                        $status = 'lembur';
                    } elseif ($presensiHari->where('status_masuk', 'absen_masuk')->count()) {
                        if ($presensiHari->where('status_pulang', 'pulang_awal')->count()) {
                            $status = 'pulang_awal';
                        } elseif ($presensiHari->where('status_pulang', 'tidak_absen_pulang')->count()) {
                            $status = 'tidak_absen_pulang';
                        } elseif ($presensiHari->where('status_pulang', 'absen_pulang')->count()) {
                            $status = 'hadir';
                        } else {
                            $status = 'tidak_absen_pulang';
                        }
                    } elseif ($presensiHari->where('status_masuk', 'terlambat')->count()) {
                        $status = 'terlambat';
                    } elseif ($presensiHari->where('status_masuk', 'tidak_absen_masuk')->count()) {
                        $status = 'tidak_absen_masuk';
                    }
                }

                // cek izin, cuti, sakit
                if (!$status) {
                    foreach ($izin as $i) {
                        if ($tanggal >= $i->tanggal_mulai && $tanggal <= $i->tanggal_selesai) {
                            $status = 'izin';
                            break;
                        }
                    }
                }
                if (!$status) {
                    foreach ($cuti as $c) {
                        if ($tanggal >= $c->tanggal_mulai && $tanggal <= $c->tanggal_selesai) {
                            $status = 'cuti';
                            break;
                        }
                    }
                }
                if (!$status) {
                    foreach ($sakit as $s) {
                        if ($tanggal >= $s->tanggal_mulai && $tanggal <= $s->tanggal_selesai) {
                            $status = 'sakit';
                            break;
                        }
                    }
                }

                // default → tidak_hadir atau belum_presensi
                if (!$status) {
                    if (\Carbon\Carbon::parse($tanggal)->lte(now('Asia/Jakarta')->startOfDay())) {
                        $status = 'tidak_hadir';
                    } else {
                        $status = 'belum_presensi';
                    }
                }

                if (isset($rekapBulan[$status])) {
                    $rekapBulan[$status]++;
                }
            }

            $result[] = array_merge([
                'bulan' => $namaBulan[$bulan],
                'hari_efektif' => $hariEfektif
            ], $rekapBulan);
        }

        return response()->json($result);
    }

    public function hitungLemburMenit($waktuPulang)
    {
        $mulaiLembur = Carbon::parse(date('Y-m-d', strtotime($waktuPulang)) . ' 18:30:00');

        $pulang = Carbon::parse($waktuPulang);

        if ($pulang->lessThanOrEqualTo($mulaiLembur)) {
            return 0;
        }

        $lemburMenit = $mulaiLembur->diffInMinutes($pulang);

        return $lemburMenit;
    }

    private function getKategoriTerlambat($waktuMasuk)
    {
        $jamMasuk = Carbon::parse($waktuMasuk);
        $jam = (int) $jamMasuk->format('H');
        $menit = (int) $jamMasuk->format('i');

        if ($jam < 9 || ($jam == 8 && $menit <= 59)) {
            return 'terlambat_sebelum_09_00';
        }

        if ($jam == 9) {
            return 'terlambat_sebelum_10_00';
        }

        if ($jam >= 10) {
            return 'terlambat_setelah_10_00';
        }

        return null;
    }

    private function getDetailPotongan($presensiCollection, $rekap, $laukPaukUnit)
    {
        $detail = [
            "potongan_terlambat" => 0,
            "potongan_tidak_absen_masuk" => 0,
            "potongan_tidak_absen_pulang" => 0,
            "potongan_pulang_awal_beralasan" => 0,
            "potongan_pulang_awal_tanpa_beralasan" => 0,
            "potongan_izin" => 0,
            "potongan_sakit" => 0,
            "potongan_tanpa_izin" => 0,
            "potongan_belum_presensi" => 0,
            "potongan_dinas" => 0,
            "lembur_weekday" => 0,
            "lembur_weekend" => 0,
        ];

        $detail['count_terlambat_sebelum_09_00'] = 0;
        $detail['count_terlambat_sebelum_10_00'] = 0;
        $detail['count_terlambat_setelah_10_00'] = 0;



        // --- POTONGAN REKAP ---
        foreach ($rekap as $tanggal => $status) {
            switch ($status) {
                case 'izin':
                    $detail["potongan_izin"] += $laukPaukUnit->pot_izin_pribadi ?? 0;
                    break;
                case 'sakit':
                    $detail["potongan_sakit"] += $laukPaukUnit->pot_sakit ?? 0;
                    break;
                case 'tidak_hadir':
                    $detail["potongan_tanpa_izin"] += $laukPaukUnit->pot_tanpa_izin ?? 0;
                    break;
                case 'belum_presensi':
                    $detail["potongan_belum_presensi"] += $laukPaukUnit->pot_tanpa_izin ?? 0;
                    break;
                case 'dinas':
                    $detail["potongan_dinas"] += $laukPaukUnit->pot_tanpa_izin ?? 0;
                    break;
            }
        }

        // --- LOGIKA MASUK, PULANG, LEMBUR ---
        foreach ($presensiCollection as $p) {

            // ===== STATUS MASUK =====
            if ($p->status_masuk === 'terlambat') {

                $kategori = $this->getKategoriTerlambat($p->waktu_masuk);

                if ($kategori === 'terlambat_sebelum_09_00') {
                    $detail["potongan_terlambat"] += $laukPaukUnit->pot_terlambat_0806_0900 ?? 0;
                    $detail["count_terlambat_sebelum_09_00"]++;
                } elseif ($kategori === 'terlambat_sebelum_10_00') {
                    $detail["potongan_terlambat"] += $laukPaukUnit->pot_terlambat_0901_1000 ?? 0;
                    $detail["count_terlambat_sebelum_10_00"]++;
                } elseif ($kategori === 'terlambat_setelah_10_00') {
                    $detail["potongan_terlambat"] += $laukPaukUnit->pot_terlambat_setelah_1000 ?? 0;
                    $detail["count_terlambat_setelah_10_00"]++;
                }
            } elseif ($p->status_masuk === 'tidak_absen_masuk') {
                $detail["potongan_tidak_absen_masuk"] += $laukPaukUnit->pot_tidak_absen_masuk ?? 0;
            }

            // ===== STATUS PULANG HANYA JIKA ABSEN MASUK =====
            if ($p->status_masuk !== 'absen_masuk') {
                // tapi lembur tetap dihitung → tidak di-continue
            } else {

                if ($p->status_pulang === '' || $p->status_pulang === null) {
                    $detail["potongan_tidak_absen_pulang"] += $laukPaukUnit->pot_tidak_absen_pulang ?? 0;
                }

                if ($p->status_pulang === 'pulang_awal') {
                    $ket = trim($p->keterangan_pulang);
                    if ($ket === "Pulang sebelum waktu pulang") {
                        $detail["potongan_pulang_awal_beralasan"] += $laukPaukUnit->pot_pulang_awal_tanpa_beralasan ?? 0;
                    } else {
                        $detail["potongan_pulang_awal_tanpa_beralasan"] += $laukPaukUnit->pot_pulang_awal_beralasan ?? 0;
                    }
                }
            }

            // ===== LEMBUR =====
            if ($p->overtime == 1 && $p->waktu_pulang) {

                $baseDate = Carbon::parse($p->waktu_pulang)->format('Y-m-d');

                $jamBatas = Carbon::parse($baseDate . ' 18:30:00');
                $waktuPulang = Carbon::parse($p->waktu_pulang);

                if ($waktuPulang->greaterThan($jamBatas)) {

                    // hitung menit lembur
                    $menitLembur = $waktuPulang->diffInMinutes($jamBatas);

                    // max 240 menit / 4 jam
                    $menitLembur = min($menitLembur, 240);

                    // dihitung per kelipatan 30 menit
                    $interval = 30;

                    $kelipatan = floor($menitLembur / $interval);

                    if ($kelipatan > 0) {

                        $totalMenitPerhitungan = $kelipatan * $interval;

                        // weekend = Sabtu(6) & Minggu(0)
                        $hari = Carbon::parse($p->tanggal)->dayOfWeek;

                        if ($hari == 0 || $hari == 6) {
                            // weekend
                            $detail["lembur_weekend"] += $totalMenitPerhitungan * ($laukPaukUnit->nom_lembur_permenit_weekend ?? 0);
                        } else {
                            // weekday
                            $detail["lembur_weekday"] += $totalMenitPerhitungan * ($laukPaukUnit->nom_lembur_permenit ?? 0);
                        }
                    }
                }
            }
        }

        return $detail;
    }





    // rekap + lauk pauk
    public function rekapPresensiBulananByAdminUnit(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }


        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitIds = $unitResult['unit_id'];

        $bulan = (int) $request->query('bulan', now('Asia/Jakarta')->month);
        $tahun = (int) $request->query('tahun', now('Asia/Jakarta')->year);
        $start = \Carbon\Carbon::create($tahun, $bulan, 1, 0, 0, 0, 'Asia/Jakarta');
        $end = $start->copy()->endOfMonth();

        $hariLiburMap = [];
        $hariLiburAll = \App\Models\HariLibur::whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])->get();
        foreach ($hariLiburAll as $hl) {
            $hariLiburMap[$hl->unit_detail_id][$hl->tanggal->format('Y-m-d')] = true;
        }

        $result = [];
        $additionalCondition = '';

        if ($unitIds == 1) {
            $additionalCondition = ' OR pg.terbantukan = 1';
        }

        $rawSql = "
            WITH RECURSIVE parent_cte AS (
                SELECT id, id_parent
                FROM sdi.ms_unit
                WHERE id = :unitId

                UNION ALL

                SELECT u.id, u.id_parent
                FROM sdi.ms_unit u
                JOIN parent_cte p ON u.id = p.id_parent
            )
            SELECT 
                pg.id_orang AS id,
                pg.no_ktp,
                TRIM(
                    CONCAT_WS(
                        ' ',
                        pg.gelar_depan,
                        pg.nama,
                        CASE 
                            WHEN pg.gelar_belakang <> '' THEN CONCAT(', ', pg.gelar_belakang)
                            ELSE ''
                        END
                    )
                ) AS nama,
                pg.tmpt_lahir,
                pg.tgl_lahir,
                pg.jenis_kelamin,
                pg.kelurahan_ktp AS alamat_ktp,
                pg.no_hp,
                pg.unit AS nama_unit,
                pg.id_unit AS unit_id_presensi,
                pg.presensi_ms_unit_detail_id,
                pg.presensi_shift_detail_id,
                s.name AS nama_shift,
                mu.nama AS nama_lokasi_presensi,
                pmud.lokasi AS lokasi_presensi
            FROM sdi.v_pegawai pg
            LEFT JOIN sdi_presensi.shift_detail sd ON sd.id = pg.presensi_shift_detail_id
            LEFT JOIN sdi_presensi.shift s ON s.id = sd.shift_id
            LEFT JOIN sdi_presensi.presensi_ms_unit_detail pmud ON pmud.id = pg.presensi_ms_unit_detail_id
            LEFT JOIN sdi.ms_unit mu ON mu.id = pmud.ms_unit_id
            WHERE (
                pg.id_unit = (
                    SELECT id
                    FROM parent_cte
                    WHERE id_parent IS NULL
                    LIMIT 1
                )
                $additionalCondition
            )
            AND pg.id_status_pegawai NOT IN (3, 4)
        ";

        $pegawais = DB::connection('mysql_sdi')->select($rawSql, ['unitId' => $unitIds]);



        $no = 1;
        // $detailTerlambat = [
        //     'terlambat_sebelum_09_00' => 0,
        //     'terlambat_sebelum_10_00' => 0,
        //     'terlambat_setelah_10_00' => 0,
        // ];

        foreach ($pegawais as $pegawai) {
            // $unitDetail = $pegawai->unitDetailPresensi;
            $unitDetailName = $pegawai->nama_unit;
            $unitId = $pegawai->unit_id_presensi;

            $unitDetailIdForLibur = $pegawai->presensi_ms_unit_detail_id;

            $nominalLaukPauk = 0;
            $laukPaukUnit = null;
            if ($unitIds) {
                $laukPaukUnit = \App\Models\LaukPaukUnit::where('unit_id', $unitIds)->first();
                $nominalLaukPauk = $laukPaukUnit ? $laukPaukUnit->nominal : 0;
            }

            $hariEfektif = 0;
            $jumlahLibur = 0;
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $isWeekend = in_array($date->dayOfWeek, [6, 0]); // 6=Sabtu, 0=Minggu
                $isLibur = $unitDetailIdForLibur && isset($hariLiburMap[$unitDetailIdForLibur][$date->format('Y-m-d')]);

                if ($isLibur) {
                    $jumlahLibur++;
                    continue;
                }
                if ($isWeekend) {
                    continue;
                }

                $hariEfektif++;
            }

            $nik = $pegawai->orang?->no_ktp ?? $pegawai->no_ktp ?? null;
            $presensi = collect();
            if ($nik) {
                $presensi = \App\Models\Presensi::where('no_ktp', $nik)
                    ->whereBetween('waktu_masuk', [$start->toDateString() . ' 00:00:00', $end->toDateString() . ' 23:59:59'])
                    ->get();
            }
            // echo json_encode($presensi);
            // exit();

            $izin = \App\Models\PengajuanIzin::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->get();

            $cuti = \App\Models\PengajuanCuti::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->get();

            $sakit = \App\Models\PengajuanSakit::where('pegawai_id', $pegawai->id)
                ->where('status', 'diterima')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('tanggal_mulai', [$start, $end])
                        ->orWhereBetween('tanggal_selesai', [$start, $end]);
                })->get();

            // Rekap status harian (skip sabtu, minggu, libur)
            $rekap = [];
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $tanggal = $date->format('Y-m-d');

                // Skip sabtu, minggu, atau libur
                $isWeekend = in_array($date->dayOfWeek, [6, 0]);
                $isLibur = $unitDetailIdForLibur && isset($hariLiburMap[$unitDetailIdForLibur][$tanggal]);
                if ($isWeekend || $isLibur) {
                    continue;
                }

                $status = null;

                $presensiHari = $presensi->filter(function ($p) use ($tanggal) {
                    return $p->waktu_masuk && \Carbon\Carbon::parse($p->waktu_masuk)->format('Y-m-d') === $tanggal;
                });

                if ($presensiHari->where('status_presensi', 'dinas')->count()) {
                    $status = 'dinas';
                    // } elseif ($presensiHari->where('overtime', 1)->count() || $presensiHari->where('overtime', true)->count()) {
                    //     $status = 'lembur';
                } elseif ($presensiHari->where('status_masuk', 'absen_masuk')->count()) {
                    if ($presensiHari->where('status_pulang', 'pulang_awal')->count()) {
                        $status = 'pulang_awal';
                    } elseif ($presensiHari->whereNull('status_pulang')->count()) {
                        $status = 'tidak_absen_pulang';
                    } elseif ($presensiHari->where('status_pulang', 'absen_pulang')->count() || $presensiHari->where('overtime', 1) || $presensiHari->where('overtime', true)) {
                        $status = 'hadir';
                    } else {
                        $status = 'tidak_absen_pulang';
                    }
                } elseif ($presensiHari->where('status_masuk', 'terlambat')->count()) {
                    $status = 'terlambat';
                } elseif ($presensiHari->where('status_masuk', 'tidak_absen_masuk')->count()) {
                    $status = 'tidak_absen_masuk';
                }

                if (!$status) {
                    foreach ($izin as $i) {
                        if ($tanggal >= $i->tanggal_mulai && $tanggal <= $i->tanggal_selesai) {
                            $status = 'izin';
                            break;
                        }
                    }
                }
                if (!$status) {
                    foreach ($cuti as $c) {
                        if ($tanggal >= $c->tanggal_mulai && $tanggal <= $c->tanggal_selesai) {
                            $status = 'cuti';
                            break;
                        }
                    }
                }
                if (!$status) {
                    foreach ($sakit as $s) {
                        if ($tanggal >= $s->tanggal_mulai && $tanggal <= $s->tanggal_selesai) {
                            $status = 'sakit';
                            break;
                        }
                    }
                }

                if (!$status) {
                    if ($date->lte(now('Asia/Jakarta')->startOfDay())) {
                        $status = 'tidak_hadir';
                    } else {
                        $status = 'belum_presensi';
                    }
                }

                $rekap[$tanggal] = $status;
            }

            // Hitung jumlah per status
            $counts = collect($rekap)->countBy();

            $potTidakIzin = ($laukPaukUnit->pot_tanpa_izin ?? 0) * $counts->get('tidak_hadir', 0);
            $potBelumPresensi = ($laukPaukUnit->pot_tanpa_izin ?? 0) * $counts->get('belum_presensi', 0);
            $totalPenalty = 0;
            $totalLembur = 0;

            $detailPotongandanLembur = $this->getDetailPotongan($presensi, $rekap, $laukPaukUnit);
            $totalPenalty =
                $detailPotongandanLembur["potongan_terlambat"]
                + $detailPotongandanLembur["potongan_tidak_absen_masuk"]
                + $detailPotongandanLembur["potongan_tidak_absen_pulang"]
                + $detailPotongandanLembur["potongan_pulang_awal_beralasan"]
                + $detailPotongandanLembur["potongan_pulang_awal_tanpa_beralasan"]
                + $detailPotongandanLembur["potongan_izin"]
                + $detailPotongandanLembur["potongan_sakit"]
                + $detailPotongandanLembur["potongan_tanpa_izin"]
                + $detailPotongandanLembur["potongan_belum_presensi"]
                + $detailPotongandanLembur["potongan_dinas"];

            $totalLembur =
                $detailPotongandanLembur["lembur_weekday"]
                + $detailPotongandanLembur["lembur_weekend"];

            $finalNominalLaukPauk = max(0, $nominalLaukPauk - $totalPenalty);

            $result[] = [
                'no' => $no++,
                'nik' => $pegawai->no_ktp,
                'nama_pegawai' => $pegawai->nama,
                'unit_kerja' => $pegawai->nama_unit ?? null,
                'hari_efektif' => $hariEfektif,
                'hadir' => $counts->get('hadir', 0),
                'izin' => $counts->get('izin', 0),
                'sakit' => $counts->get('sakit', 0),
                'cuti' => $counts->get('cuti', 0),
                'tidak_hadir' => $counts->get('tidak_hadir', 0),
                'dinas' => $counts->get('dinas', 0),
                'lembur' => $counts->get('lembur', 0),
                'terlambat' => $counts->get('terlambat', 0),
                'detail_terlambat' => [
                    'terlambat_sebelum_09_00' => $detailPotongandanLembur['count_terlambat_sebelum_09_00'],
                    'terlambat_sebelum_10_00' => $detailPotongandanLembur['count_terlambat_sebelum_10_00'],
                    'terlambat_setelah_10_00' => $detailPotongandanLembur['count_terlambat_setelah_10_00'],
                ],

                'pulang_awal' => $counts->get('pulang_awal', 0),
                'tidak_absen_masuk' => $counts->get('tidak_absen_masuk', 0),
                'tidak_absen_pulang' => $counts->get('tidak_absen_pulang', 0),
                'belum_presensi' => $counts->get('belum_presensi', 0),
                'jumlah_libur' => $jumlahLibur,
                'nominal_lauk_pauk' => $finalNominalLaukPauk,
                'detail_potongan_dan_lembur' => $detailPotongandanLembur
            ];
        }

        return response()->json($result);
    }




    /**
     * Integrasikan pengajuan sakit, izin, cuti ke tabel presensi
     * Dipanggil ketika admin approve pengajuan
     */
    public function integratePengajuanToPresensi($pegawai_id, $jenis_pengajuan, $tanggal_mulai, $tanggal_selesai, $keterangan = null)
    {
        $pegawai = \App\Models\MsPegawai::with(['orang', 'shiftDetail'])->find($pegawai_id);
        if (!$pegawai) {
            return false;
        }

        $shiftDetail = $pegawai->shiftDetail;
        $shiftId = $shiftDetail ? $shiftDetail->shift_id : null;

        $mapHari = [
            'monday'    => 'senin',
            'tuesday'   => 'selasa',
            'wednesday' => 'rabu',
            'thursday'  => 'kamis',
            'friday'    => 'jumat',
            'saturday'  => 'sabtu',
            'sunday'    => 'minggu',
        ];

        $start = \Carbon\Carbon::parse($tanggal_mulai);
        $end = \Carbon\Carbon::parse($tanggal_selesai);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $tanggal = $date->format('Y-m-d');

            // Skip weekend dan hari libur sesuai request
            $isWeekend = $date->isSaturday() || $date->isSunday();
            
            // Ambil unit_id_presensi dari DB sdi.v_pegawai untuk konsistensi logic hari libur
            $pegawaiData = DB::connection('mysql_sdi')
                ->table('sdi.v_pegawai')
                ->where('id', $pegawai_id)
                ->select([
                    DB::raw('CASE 
                                WHEN terbantukan = 1 THEN 1
                                ELSE id_unit
                             END as unit_effective')
                ])
                ->first();

            $unitEffective = $pegawaiData ? $pegawaiData->unit_effective : $pegawai->id_unit;
            $isHariLibur = \App\Models\HariLibur::isHariLibur($unitEffective, $tanggal);

            if ($isWeekend || $isHariLibur) {
                continue;
            }

            $hari = strtolower($date->isoFormat('dddd'));
            $namaHari = $mapHari[$hari] ?? null;

            $waktuMasukStr = $namaHari && $shiftDetail ? $shiftDetail->{$namaHari . '_masuk'} : null;
            $waktuPulangStr = $namaHari && $shiftDetail ? $shiftDetail->{$namaHari . '_pulang'} : null;

            $waktuMasuk = $waktuMasukStr ? $date->copy()->setTimeFromTimeString($waktuMasukStr) : $date->copy()->setTime(8, 0, 0);
            $waktuPulang = $waktuPulangStr ? $date->copy()->setTimeFromTimeString($waktuPulangStr) : null;

            $existingPresensi = \App\Models\Presensi::where('no_ktp', $pegawai->orang->no_ktp)
                ->whereDate('waktu_masuk', $tanggal)
                ->first();

            if ($existingPresensi) {
                $existingPresensi->update([
                    'status_masuk' => $jenis_pengajuan,
                    'status_pulang' => $jenis_pengajuan,
                    'status_presensi' => $jenis_pengajuan,
                    'keterangan_masuk' => $keterangan ?? "Pengajuan {$jenis_pengajuan} yang disetujui",
                    'keterangan_pulang' => $keterangan ?? "Pengajuan {$jenis_pengajuan} yang disetujui",
                    'waktu_masuk' => $waktuMasuk,
                    'waktu_pulang' => $waktuPulang,
                ]);
            }
            // else {
            //     // Jangan insert baru, biarkan scheduler yang meng-insert
            // }
        }

        return true;
    }


    /**
     * Hapus integrasi pengajuan dari presensi (ketika pengajuan ditolak/dibatalkan)
     */
    public function removePengajuanFromPresensi($pegawai_id, $jenis_pengajuan, $tanggal_mulai, $tanggal_selesai)
    {
        $pegawai = \App\Models\MsPegawai::find($pegawai_id);
        if (!$pegawai) {
            return false;
        }

        $start = \Carbon\Carbon::parse($tanggal_mulai);
        $end = \Carbon\Carbon::parse($tanggal_selesai);

        // Update kembali ke status tidak_hadir jika pengajuan ditolak/dibatalkan
        Presensi::where('no_ktp', $pegawai->no_ktp)
            ->whereBetween('waktu_masuk', [$start->toDateString() . ' 00:00:00', $end->toDateString() . ' 23:59:59'])
            ->where('status_masuk', $jenis_pengajuan)
            ->where('keterangan_masuk', 'like', "Pengajuan {$jenis_pengajuan} yang disetujui%")
            ->update([
                'status_masuk' => 'tidak_hadir',
                'status_pulang' => 'tidak_hadir',
                'status_presensi' => 'tidak_hadir',
                'keterangan_masuk' => 'Tidak hadir (Pengajuan sebelumnya ditolak/dibatalkan)',
                'keterangan_pulang' => 'Tidak hadir (Pengajuan sebelumnya ditolak/dibatalkan)'
            ]);

        return true;
    }

    public function getLaporanKehadiranKaryawan(Request $request, $pegawai_id)
    {
        Carbon::setLocale('id');
        $idRealPegawai = DB::connection('mysql_sdi')->table('sdi.v_pegawai')->where('id_orang', $pegawai_id)->value('id');
        $pegawai = MsPegawai::with('orang')->where('id', $idRealPegawai)->firstOrFail();


        $noKtp   = $pegawai->orang->no_ktp;

        $bulan = $request->get('bulan', now()->month);
        $tahun = $request->get('tahun', now()->year);


        $presensiList = Presensi::where('no_ktp', $noKtp)
            ->whereMonth('waktu_masuk', $bulan)
            ->whereYear('waktu_masuk', $tahun)
            ->orderBy('waktu_masuk')
            ->get();

        $data = [];

        foreach ($presensiList as $p) {
            $tanggalPresensi = $p->waktu_masuk
                ? Carbon::parse($p->waktu_masuk)->toDateString()
                : Carbon::parse($p->waktu_pulang)->toDateString();

            $shiftDetail = ShiftDetail::find($pegawai->presensi_shift_detail_id);

            $hari = strtolower(Carbon::parse($tanggalPresensi)->locale('id')->isoFormat('dddd'));

            $kolomMasuk = "{$hari}_masuk";
            $kolomPulang = "{$hari}_pulang";

            $shiftMasuk = $shiftDetail->$kolomMasuk;
            $shiftPulang = $shiftDetail->$kolomPulang;

            if (!$shiftMasuk || !$shiftPulang || strtolower($shiftMasuk) === 'libur' || strtolower($shiftPulang) === 'libur') {
                continue;
            }

            $jamKerjaMasuk = Carbon::parse($tanggalPresensi . ' ' . $shiftMasuk);
            $jamKerjaPulang = Carbon::parse($tanggalPresensi . ' ' . $shiftPulang);


            $jamMasuk = $p->waktu_masuk ? Carbon::parse($p->waktu_masuk) : null;
            $jamKeluar = $p->waktu_pulang ? Carbon::parse($p->waktu_pulang) : null;

            $menitCepat = $menitTelat = '-';

            if ($jamMasuk && $jamMasuk->format('H:i:s') !== '00:00:00') {

                $menitCepat = 0;
                $menitTelat = 0;

                if ($jamMasuk->lessThan($jamKerjaMasuk)) {
                    $menitCepat = $jamMasuk->diffInMinutes($jamKerjaMasuk);
                } elseif ($jamMasuk->greaterThan($jamKerjaMasuk)) {
                    $menitTelat = $jamKerjaMasuk->diffInMinutes($jamMasuk);
                }
            }



            $menitPulangCepat = $menitLembur = '-';

            if ($jamKeluar && $jamKeluar->format('H:i:s') !== '00:00:00') {

                $menitPulangCepat = 0;
                $menitLembur = 0;

                if ($jamKeluar->lessThan($jamKerjaPulang)) {
                    $menitPulangCepat = $jamKeluar->diffInMinutes($jamKerjaPulang);
                } elseif ($jamKeluar->greaterThan($jamKerjaPulang)) {
                    $menitLembur = $jamKerjaPulang->diffInMinutes($jamKeluar);
                }
            }



            // Hitung total jam kerja
            $jamKerjaTotal = 0;
            if ($jamMasuk && $jamKeluar) {
                $jamKerjaTotal = round($jamMasuk->floatDiffInHours($jamKeluar), 2);
            }

            $data[] = [
                'tgl_absensi' => $jamMasuk ? $jamMasuk->translatedFormat('l, j F Y') : null,
                'jam_kerja' => [
                    'masuk' => $jamKerjaMasuk->format('H:i'),
                    'pulang' => $jamKerjaPulang->format('H:i'),
                ],
                'jam_masuk' => $jamMasuk ? $jamMasuk->format('H:i') : '-',
                'jam_keluar' => $jamKeluar ? $jamKeluar->format('H:i') : '-',
                'jumlah_menit_datang' => [
                    'menit_datang_cepat' => $menitCepat,
                    'menit_telat' => $menitTelat,
                ],
                'jumlah_menit_pulang' => [
                    'menit_pulang_cepat' => $menitPulangCepat,
                    'menit_lembur' => $menitLembur,
                ],
                'jam_kerja_total' => $jamKerjaTotal,
                'alasan' => $p->keterangan_masuk ?: ($p->keterangan_pulang ?: ''),
            ];
        }
        $upk = DB::connection('mysql_sdi')->selectOne("
                SELECT 
                    CASE
                        WHEN oyg.id IS NOT NULL
                        AND oyg.aktif = 1 THEN oy.nama
                        ELSE upk.nama
                    END AS upk
                FROM ms_pegawai
                LEFT JOIN ms_unit upk ON ms_pegawai.id_upk = upk.id
                LEFT JOIN organ_yayasan_anggota oyg ON oyg.id_orang = ms_pegawai.id_orang
                LEFT JOIN organ_yayasan_jabatan oyj ON oyj.id = oyg.id_organ_jabatan
                LEFT JOIN organ_yayasan oy ON oy.id = oyj.id_organ
                WHERE ms_pegawai.id_orang = ?
                LIMIT 1
            ", [$pegawai->id_orang]);

        $upkName = $upk ? $upk->upk : null;

        return response()->json([
            'pegawai' => [
                'no_ktp' => $pegawai->orang->no_ktp,
                'nama' => $pegawai->orang->nama,
                'unit_kerja' => $pegawai->unit ? $pegawai->unit->nama : null,
                'jabatan' => $upkName,
            ],
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
            ],
            'data' => $data
        ]);
    }

    public function getOvertimePegawai(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $bulan = $request->query('bulan');
        $tahun = $request->query('tahun');

        $pegawais = MsPegawai::whereHas('unitDetailPresensi', function ($q) use ($unitId) {
            $q->where('id_unit', $unitId);
        })
            ->whereHas('orang.presensi', function ($q) use ($bulan, $tahun) {
                if ($bulan) $q->whereMonth('waktu_pulang', $bulan);
                if ($tahun) $q->whereYear('waktu_pulang', $tahun);
                $q->where('overtime', 1);
            })
            ->with([
                'unitDetailPresensi.unit',
                'orang:id,no_ktp,nama',
                'orang.presensi' => function ($q) use ($bulan, $tahun) {
                    if ($bulan) $q->whereMonth('waktu_pulang', $bulan);
                    if ($tahun) $q->whereYear('waktu_pulang', $tahun);
                    $q->where('overtime', 1)->orderBy('waktu_pulang', 'desc');
                }
            ])
            ->get();

        $result = collect();

        foreach ($pegawais as $pegawai) {

            $unitModel = $pegawai->unitDetailPresensi->unit ?? null;
            $unitIdForLauk = $unitModel->id ?? null;

            $lauk = null;
            if ($unitIdForLauk) {
                $lauk = \App\Models\LaukPaukUnit::where('unit_id', $unitIdForLauk)->first();
            }

            foreach ($pegawai->orang->presensi as $p) {
                if (!$p->waktu_pulang) continue;

                $waktuPulang = \Carbon\Carbon::parse($p->waktu_pulang);

                $startOT = $waktuPulang->copy()->setTimeFromTimeString('18:30');

                if ($waktuPulang->gt($startOT)) {
                    $menitLembur = $startOT->diffInMinutes($waktuPulang);
                } else {
                    $menitLembur = 0;
                }

                $menitLembur = min($menitLembur, 240);

                $isWeekend = in_array(
                    strtolower($waktuPulang->format('l')),
                    ['saturday', 'sunday']
                );

                $nomLemburPerMenit = 0;
                if ($lauk) {
                    $nomLemburPerMenit = $isWeekend
                        ? ($lauk->nom_lembur_permenit_weekend ?? 0)
                        : ($lauk->nom_lembur_permenit ?? 0);
                }

                $threshold = 30;

                $totalNominal = 0;
                if ($menitLembur >= $threshold && $nomLemburPerMenit > 0) {
                    $totalNominal = $menitLembur * $nomLemburPerMenit;
                }

                $result->push([
                    'no_ktp' => $pegawai->orang->no_ktp,
                    'nama' => $pegawai->orang->nama,
                    'jabatan' => $pegawai->profesi,
                    'unit_detail' => $pegawai->unitDetailPresensi->unit->nama ?? null,
                    'tanggal' => $waktuPulang->format('Y-m-d'),
                    'waktu_masuk' => $p->waktu_masuk ? \Carbon\Carbon::parse($p->waktu_masuk)->format('H:i') : null,
                    'waktu_pulang' => $waktuPulang->format('H:i'),

                    'menit_overtime' => $menitLembur,
                    'nom_lembur' => $nomLemburPerMenit,
                    'total_nom_lembur' => $totalNominal
                ]);
            }
        }

        $result = $result->sortByDesc(fn($i) => $i['tanggal'] . ' ' . $i['waktu_pulang'])->values();
        return response()->json($result);
    }



    public function adminPresensiPegawai(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string|max:255',
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:mysql_sdi.ms_pegawai,id',
        ]);

        $pegawais = MsPegawai::with('shiftDetail', 'orang')
            ->whereIn('id_orang', $request->pegawai_ids)
            ->get();

        if ($pegawais->isEmpty()) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 404);
        }

        $createdPresensi = [];
        $errors = [];

        $start = Carbon::parse($request->tanggal_mulai);
        $end = Carbon::parse($request->tanggal_selesai);

        foreach ($pegawais as $pegawai) {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $tanggal = $date->format('Y-m-d');

                // Cek duplikat presensi
                $existingPresensi = Presensi::where('no_ktp', $pegawai->orang->no_ktp ?? null)
                    ->whereDate('waktu_masuk', $tanggal)
                    ->first();

                if ($existingPresensi) {
                    $errors[] = "Pegawai {$pegawai->orang->nama} sudah memiliki presensi pada tanggal {$tanggal}";
                    continue;
                }

                $shiftDetail = $pegawai->shiftDetail;
                if (!$shiftDetail) {
                    $errors[] = "Pegawai {$pegawai->orang->nama} tidak memiliki shift detail";
                    continue;
                }

                $waktuMasuk = $this->getWaktuMasukShift($shiftDetail, $date);
                $waktuPulang = $this->getWaktuPulangShift($shiftDetail, $date);

                if (!$waktuMasuk || !$waktuPulang) {
                    $errors[] = "Pegawai {$pegawai->orang->nama} tidak memiliki jam kerja pada hari " . $date->locale('id')->isoFormat('dddd');
                    continue;
                }

                try {
                    $presensi = Presensi::create([
                        'no_ktp' => $pegawai->orang->no_ktp,
                        'shift_id' => $shiftDetail->shift_id,
                        'shift_detail_id' => $shiftDetail->id,
                        'waktu_masuk' => $waktuMasuk,
                        'waktu_pulang' => $waktuPulang,
                        'status_masuk' => 'absen_masuk',
                        'status_pulang' => 'absen_pulang',
                        'lokasi_masuk' => null,
                        'lokasi_pulang' => null,
                        'keterangan_masuk' => $request->keterangan,
                        'keterangan_pulang' => $request->keterangan,
                        'status_presensi' => 'hadir',
                    ]);

                    $createdPresensi[] = [
                        'pegawai' => $pegawai->orang->nama,
                        'tanggal' => $tanggal,
                        'presensi_id' => $presensi->id,
                    ];
                } catch (\Exception $e) {
                    $errors[] = "Gagal membuat presensi untuk pegawai {$pegawai->orang->nama} pada tanggal {$tanggal}: " . $e->getMessage();
                }
            }
        }

        return response()->json([
            'message' => 'Berhasil Mempresensikan pegawai',
            'created_count' => count($createdPresensi),
            'error_count' => count($errors),
            'created_data' => $createdPresensi,
            'errors' => $errors,
        ]);
    }

    /**
     * Get waktu masuk berdasarkan shift detail dan tanggal
     * 
     * @param ShiftDetail $shiftDetail
     * @param Carbon $date
     * @return Carbon|null
     */
    private function getWaktuMasukShift($shiftDetail, $date)
    {
        $hari = strtolower($date->locale('id')->isoFormat('dddd'));
        $masukKey = $hari . '_masuk';
        //dd($masukKey, $shiftDetail->$masukKey);
        $jamString = trim($shiftDetail->$masukKey ?? '');

        if (!$jamString) {
            return null; // tidak ada data jam
        }

        try {
            // Gunakan H:i karena di DB formatnya "08:00"
            $jamMasuk = Carbon::createFromFormat('H:i', $jamString);
            return $date->copy()->setTime($jamMasuk->hour, $jamMasuk->minute, 0);
        } catch (\Exception $e) {
            //Log::error("Format jam masuk tidak valid: {$jamString}");
            return null;
        }
    }


    /**
     * Get waktu pulang berdasarkan shift detail dan tanggal
     * 
     * @param ShiftDetail $shiftDetail
     * @param Carbon $date
     * @return Carbon|null
     */
    private function getWaktuPulangShift($shiftDetail, $date)
    {
        $hari = strtolower($date->locale('id')->isoFormat('dddd'));
        $pulangKey = $hari . '_pulang';

        if (!$shiftDetail->$pulangKey) {
            return null;
        }

        $jamPulang = Carbon::createFromFormat('H:i', $shiftDetail->$pulangKey);

        return $date->copy()->setTime($jamPulang->hour, $jamPulang->minute, 0);
    }

    // update FITUR KHUSUS KEPALA UNIT

    public function getSummaryPresensiUnit(Request $request)
    {
        $pegawai = $request->get('pegawai');

        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $pegawai->load([
            'shiftDetail.shift',
            'unitDetailPresensi.unit',
            'pegawai'
        ]);

        // if ($pegawai->pegawai->profesi !== 'Kepala Sekolah') {
        //     return response()->json([
        //         'message' => 'Anda bukan kepala unit!'
        //     ]);
        // }

        $unitId = $pegawai->pegawai->id_unit;

        $pegawaiSummary = DB::connection('mysql_sdi')->selectOne("
            SELECT 
                SUM(CASE WHEN pg.id_status_pegawai = 2 THEN 1 ELSE 0 END) AS jumlah_tetap,
                SUM(CASE WHEN pg.id_status_pegawai = 3 THEN 1 ELSE 0 END) AS jumlah_kontrak,
                SUM(CASE WHEN pg.id_status_pegawai NOT IN (2, 3) OR pg.id_status_pegawai IS NULL THEN 1 ELSE 0 END) AS jumlah_lain
            FROM v_pegawai pg 
            WHERE pg.id_unit = '$unitId'
        ");


        $presensi = DB::connection('mysql')->table('presensi as pr')
            ->join('sdi.ms_orang as o', 'o.no_ktp', '=', 'pr.no_ktp')
            ->join('sdi.ms_pegawai as pg', 'pg.id_orang', '=', 'o.id')
            ->where('pg.id_unit', $unitId)
            ->whereDate('pr.created_at', now()->toDateString())
            ->count();

        $totalPegawai = ($pegawaiSummary->jumlah_tetap ?? 0)
            + ($pegawaiSummary->jumlah_kontrak ?? 0)
            + ($pegawaiSummary->jumlah_lain ?? 0);

        return response()->json([
            'id_kepala_unit' => $pegawai->id,
            'id_unit' => $unitId,
            'jumlah_tetap' => $pegawaiSummary->jumlah_tetap ?? 0,
            'jumlah_kontrak' => $pegawaiSummary->jumlah_kontrak ?? 0,
            'jumlah_lain' => $pegawaiSummary->jumlah_lain ?? 0,
            'total_pegawai' => $totalPegawai,
            'total_presensi_hari_ini' => $presensi,
        ]);
    }



    public function historyByKepalaUnit(Request $request)
    {
        $pegawai = $request->get('pegawai');

        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $pegawai->load([
            'shiftDetail.shift',
            'unitDetailPresensi.unit',
            'pegawai'
        ]);

        // if ($pegawai->pegawai->profesi !== 'Kepala Sekolah') {
        //     return response()->json([
        //         'message' => 'Anda bukan kepala unit!'
        //     ]);
        // }

        $unitId = $pegawai->pegawai->id_unit;

        $tanggal = $request->query('tanggal', Carbon::today()->toDateString());

        $pegawais = MsPegawai::whereHas('unitDetailPresensi', function ($q) use ($unitId) {
            $q->where('id_unit', $unitId);
        })
            ->with('orang:id,no_ktp,nama')
            ->get(['id', 'id_orang']);

        $no_ktps = $pegawais->pluck('orang.no_ktp');

        $pegawaiMap = $pegawais->mapWithKeys(function ($pegawai) {
            $noKtp = optional($pegawai->orang)->no_ktp;
            return $noKtp ? [$noKtp => $pegawai->orang] : [];
        });


        $query = Presensi::whereIn('no_ktp', $no_ktps);

        if ($tanggal) {
            $query->whereBetween('waktu_masuk', [
                Carbon::parse($tanggal)->startOfDay(),
                Carbon::parse($tanggal)->endOfDay()
            ]);
        }


        $presensis = $query->orderBy('waktu_masuk', 'desc')->get();

        $result = $presensis->map(function ($p) use ($pegawaiMap) {
            $pegawai = $pegawaiMap[$p->no_ktp] ?? null;

            $statusMasuk  = empty($p->status_masuk)  ? 'tidak_absen_masuk'  : $p->status_masuk;
            $statusPulang = empty($p->status_pulang) ? 'tidak_absen_pulang' : $p->status_pulang;

            return [
                'id'                => $p->id,
                'no_ktp'            => $p->no_ktp,
                'nama'              => $pegawai?->nama,
                'status_masuk'      => $statusMasuk,
                'status_pulang'     => $statusPulang,
                'status_presensi'   => $p->status_presensi,
                'waktu_masuk'       => $p->waktu_masuk,
                'waktu_pulang'      => $p->waktu_pulang,
                'keterangan_masuk'  => $p->keterangan_masuk,
                'keterangan_pulang' => $p->keterangan_pulang,
                'created_at'        => $p->created_at,
                'updated_at'        => $p->updated_at,
            ];
        });


        return response()->json($result);
    }


    public function rekapPresensiHarianBulananByKepalaUnit(Request $request)
    {
        $pegawai = $request->get('pegawai');
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $pegawai->load([
            'shiftDetail.shift',
            'unitDetailPresensi.unit',
            'pegawai'
        ]);

        // if ($pegawai->pegawai->profesi !== 'Kepala Sekolah') {
        //     return response()->json([
        //         'message' => 'Anda bukan kepala unit!'
        //     ]);
        // }

        $unitId = $pegawai->pegawai->id_unit;
        $tanggal = $request->query('tanggal'); // format YYYY-MM-DD
        $bulan   = $request->query('bulan');   // format YYYY-MM

        $pegawais = \App\Models\MsPegawai::where('id_unit', $unitId)
            ->whereNotNull('id_orang')
            ->whereHas('orang')
            ->with('orang:id,no_ktp,nama')
            ->get(['id', 'id_orang', 'id_unit']);

        $noKtps = $pegawais
            ->pluck('orang.no_ktp')
            ->filter()
            ->values();



        $makeResult = function () use ($pegawais) {
            return [
                'hadir' => 0,
                'izin' => 0,
                'sakit' => 0,
                'cuti' => 0,
                'tidak_hadir' => 0,
                'dinas' => 0,
                'lembur' => 0,
                'terlambat' => 0,
                'pulang_awal' => 0,
                'tidak_absen_masuk' => 0,
                'tidak_absen_pulang' => 0,
                'belum_presensi' => 0,
                'total_pegawai' => $pegawais->count(),
            ];
        };

        $hitungPerHari = function ($tanggal, $pegawais, $noKtps) {
            $result = [
                'filter' => $tanggal,
                'rekap'   => [
                    'hadir' => 0,
                    'pegawai_hadir' => [],
                    'izin' => 0,
                    'pegawai_izin' => [],
                    'sakit' => 0,
                    'pegawai_sakit' => [],
                    'cuti' => 0,
                    'pegawai_cuti' => [],
                    'tidak_hadir' => 0,
                    'pegawai_tidak_hadir' => [],
                    'dinas' => 0,
                    'pegawai_dinas' => [],
                    'lembur' => 0,
                    'pegawai_lembur' => [],
                    'terlambat' => 0,
                    'pegawai_terlambat' => [],
                    'pulang_awal' => 0,
                    'pegawai_pulang_awal' => [],
                    'tidak_absen_masuk' => 0,
                    'pegawai_tidak_absen_masuk' => [],
                    'tidak_absen_pulang' => 0,
                    'pegawai_tidak_absen_pulang' => [],
                    'belum_presensi' => 0,
                    'pegawai_belum_presensi' => [],
                    'total_pegawai' => $pegawais->count(),
                ]
            ];

            $presensis = \App\Models\Presensi::whereIn('no_ktp', $noKtps)
                ->whereBetween('waktu_masuk', [
                    \Carbon\Carbon::parse($tanggal)->startOfDay(),
                    \Carbon\Carbon::parse($tanggal)->endOfDay()
                ])
                ->get();

            $izin = \App\Models\PengajuanIzin::whereIn('pegawai_id', $pegawais->pluck('id'))
                ->where('status', 'diterima')
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->get();

            $cuti = \App\Models\PengajuanCuti::whereIn('pegawai_id', $pegawais->pluck('id'))
                ->where('status', 'diterima')
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->get();

            $sakit = \App\Models\PengajuanSakit::whereIn('pegawai_id', $pegawais->pluck('id'))
                ->where('status', 'diterima')
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->get();

            foreach ($pegawais as $pgw) {
                if (is_null($pgw->id_orang) || !$pgw->orang) {
                    continue;
                }
                $status = null;
                $namaPegawai = optional($pgw->orang)->nama;

                $presensiHari = $presensis->filter(function ($p) use ($pgw, $tanggal) {
                    return $p->no_ktp === optional($pgw->orang)->no_ktp
                        && $p->waktu_masuk
                        && \Carbon\Carbon::parse($p->waktu_masuk)->isSameDay($tanggal);
                });

                if ($presensiHari->where('status_presensi', 'dinas')->count()) {
                    $status = 'dinas';
                } elseif ($presensiHari->where('overtime', true)->count()) {
                    $status = 'lembur';
                } elseif ($presensiHari->where('status_masuk', 'absen_masuk')->count()) {
                    if ($presensiHari->where('status_pulang', 'pulang_awal')->count()) {
                        $status = 'pulang_awal';
                    } elseif ($presensiHari->where('status_pulang', '')->count()) {
                        $status = 'hadir';
                    } elseif ($presensiHari->where('status_pulang', 'tidak_absen_pulang')->count()) {
                        $status = 'tidak_absen_pulang';
                    } elseif ($presensiHari->where('status_pulang', 'absen_pulang')->count()) {
                        $status = 'hadir';
                    } else {
                        $status = 'tidak_absen_pulang';
                    }
                } elseif ($presensiHari->where('status_masuk', 'terlambat')->count()) {
                    $status = 'terlambat';
                } elseif ($presensiHari->where('status_masuk', 'tidak_absen_masuk')->count()) {
                    $status = 'tidak_absen_masuk';
                }

                if (!$status) {
                    if ($izin->where('pegawai_id', $pgw->id)->count()) {
                        $status = 'izin';
                    } elseif ($cuti->where('pegawai_id', $pgw->id)->count()) {
                        $status = 'cuti';
                    } elseif ($sakit->where('pegawai_id', $pgw->id)->count()) {
                        $status = 'sakit';
                    }
                }

                if (!$status) {
                    if (\Carbon\Carbon::parse($tanggal)->lte(now('Asia/Jakarta')->startOfDay())) {
                        $status = 'tidak_hadir';
                    } else {
                        $status = 'belum_presensi';
                    }
                }

                if (isset($result['rekap'][$status])) {
                    $result['rekap'][$status]++;
                    $keyNama = 'pegawai_' . $status;
                    $result['rekap'][$keyNama][] = $namaPegawai ?? 'Tidak diketahui';
                }
            }

            return $result;
        };


        if ($tanggal) {
            $rekap = $hitungPerHari($tanggal, $pegawais, $noKtps);
            return response()->json($rekap);
        }

        if ($bulan) {
            try {
                $start = \Carbon\Carbon::createFromFormat('Y-m', $bulan, 'Asia/Jakarta')->startOfMonth();
            } catch (\Exception $e) {
                return response()->json(['message' => 'Format bulan harus YYYY-MM'], 422);
            }
            $end = $start->copy()->endOfMonth();

            $rekapBulanan = [
                'hadir' => 0,
                'izin' => 0,
                'sakit' => 0,
                'cuti' => 0,
                'tidak_hadir' => 0,
                'dinas' => 0,
                'lembur' => 0,
                'terlambat' => 0,
                'pulang_awal' => 0,
                'tidak_absen_masuk' => 0,
                'tidak_absen_pulang' => 0,
                'belum_presensi' => 0,
            ];

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $harian = $hitungPerHari($date->toDateString(), $pegawais, $noKtps);
                foreach ($rekapBulanan as $key => $val) {
                    $rekapBulanan[$key] += $harian['rekap'][$key];
                }
            }

            // Bungkus supaya sama formatnya dengan harian
            return response()->json([
                'filter' => $bulan,
                'rekap' => array_merge($rekapBulanan, [
                    'total_pegawai' => $pegawais->count()
                ])
            ]);
        }


        return response()->json([
            'message' => 'Harap sertakan parameter tanggal (YYYY-MM-DD) ATAU bulan (YYYY-MM)'
        ], 422);
    }



    public function historyAll(Request $request)
    {
        $authHeader = $request->header('Authorization');

        // if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
        //     return response()->json(['message' => 'Unauthorized'], 401);
        // }

        $encoded = substr($authHeader, 6);
        $decoded = base64_decode($encoded);
        [$username, $password] = explode(':', $decoded) + [null, null];

        $validUsername = 'presensi_ybwsa';
        $validPassword = 'presensiXyz#230!';

        if ($username !== $validUsername || $password !== $validPassword) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $tanggal = $request->query('tanggal', Carbon::today()->toDateString());

        $query = Presensi::query();

        if ($tanggal) {
            $query->whereDate('waktu_masuk', $tanggal);
        }

        $presensis = $query->orderBy('waktu_masuk', 'desc')->paginate(20);

        $no_ktps = $presensis->pluck('no_ktp')->unique();

        $pegawais = MsPegawai::whereIn('id_orang', function ($q) use ($no_ktps) {
            $q->select('id')
                ->from('ms_orang')
                ->whereIn('no_ktp', $no_ktps);
        })
            ->with('orang:id,no_ktp,nama')
            ->get(['id', 'id_orang']);

        $pegawaiMap = $pegawais->filter(fn($p) => $p->orang)
            ->mapWithKeys(fn($p) => [$p->orang->no_ktp => $p->orang]);

        $result = $presensis->getCollection()->map(function ($p) use ($pegawaiMap) {
            $pegawai = $pegawaiMap[$p->no_ktp] ?? null;
            return [
                'id'               => $p->id,
                'no_ktp'           => $p->no_ktp,
                'nama'             => $pegawai?->nama,
                'status_masuk'     => $p->status_masuk,
                'status_pulang'    => $p->status_pulang,
                'status_presensi'  => $p->status_presensi,
                'waktu_masuk'      => $p->waktu_masuk
                    ? $p->waktu_masuk->timezone(config('app.timezone'))->toDateTimeString()
                    : null,
                'waktu_pulang'     => $p->waktu_pulang
                    ? $p->waktu_pulang->timezone(config('app.timezone'))->toDateTimeString()
                    : null,
                'keterangan_masuk' => $p->keterangan_masuk,
                'keterangan_pulang' => $p->keterangan_pulang,
                'created_at'       => $p->created_at,
                'updated_at'       => $p->updated_at,
            ];
        });

        $presensis->setCollection($result);

        return response()->json($presensis);
    }

    public function historyAllAdmin(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $tanggal = $request->query('tanggal', Carbon::today()->toDateString());

        $query = Presensi::query();

        if ($tanggal) {
            $query->whereDate('waktu_masuk', $tanggal);
        }

        $presensis = $query->orderBy('waktu_masuk', 'desc')->paginate(20);

        $no_ktps = $presensis->pluck('no_ktp')->unique();

        $pegawais = MsPegawai::whereIn('id_orang', function ($q) use ($no_ktps) {
            $q->select('id')
                ->from('ms_orang')
                ->whereIn('no_ktp', $no_ktps);
        })
            ->with('orang:id,no_ktp,nama')
            ->get(['id', 'id_orang']);

        $pegawaiMap = $pegawais->filter(fn($p) => $p->orang)
            ->mapWithKeys(fn($p) => [$p->orang->no_ktp => $p->orang]);

        $result = $presensis->getCollection()->map(function ($p) use ($pegawaiMap) {
            $pegawai = $pegawaiMap[$p->no_ktp] ?? null;
            return [
                'id'               => $p->id,
                'no_ktp'           => $p->no_ktp,
                'nama'             => $pegawai?->nama,
                'status_masuk'     => $p->status_masuk,
                'status_pulang'    => $p->status_pulang,
                'status_presensi'  => $p->status_presensi,
                'waktu_masuk'      => $p->waktu_masuk
                    ? $p->waktu_masuk->timezone(config('app.timezone'))->toDateTimeString()
                    : null,
                'waktu_pulang'     => $p->waktu_pulang
                    ? $p->waktu_pulang->timezone(config('app.timezone'))->toDateTimeString()
                    : null,
                'keterangan_masuk' => $p->keterangan_masuk,
                'keterangan_pulang' => $p->keterangan_pulang,
                'created_at'       => $p->created_at,
                'updated_at'       => $p->updated_at,
            ];
        });

        $presensis->setCollection($result);

        return response()->json($presensis);
    }
}
