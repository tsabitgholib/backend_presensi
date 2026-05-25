<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Presensi;
use Carbon\Carbon;

class TidakAbsenController extends Controller
{
    private function checkAuth(Request $request)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            return false;
        }

        $encoded = substr($authHeader, 6);
        $decoded = base64_decode($encoded);

        if (!$decoded || !str_contains($decoded, ':')) {
            return false;
        }

        [$user, $pass] = explode(':', $decoded, 2);

        $validUser = 'YBWSAPresensi';
        $validPass = 'xYzYBWSAPresensixYz11!';

        return $user === $validUser && $pass === $validPass;
    }

        public function generateAbsentToday(Request $request)
    {
        // if (!$this->checkAuth($request)) {
        //     return response()->json(['error' => 'Unauthorized'], 401);
        // }

        $day = now()->dayOfWeek;

        // Skip Sabtu / Minggu
        if ($day == 0 || $day == 6) {
            return response()->json([
                'status' => 'skipped',
                'message' => 'Hari ini adalah Sabtu/Minggu, proses dihentikan.'
            ]);
        }


        $today = Carbon::now('Asia/Jakarta')->toDateString();
        // echo "Today: $today";
        // exit();
    //    $today = "2026-02-24";


        $pegawaiTidakAbsen = DB::connection('mysql')
            ->table('sdi.v_pegawai as p')
            ->leftJoin('sdi_presensi.shift_detail as sd', 'sd.id', '=', 'p.presensi_shift_detail_id')
            ->leftJoin('sdi_presensi.shift as s', 's.id', '=', 'sd.shift_id')
            ->leftJoin('sdi_presensi.presensi as pr', function ($join) use ($today) {
                $join->on('pr.no_ktp', '=', 'p.no_ktp')
                    ->whereDate('pr.waktu_masuk', $today);
            })
            ->whereNotNull('p.presensi_ms_unit_detail_id')
            ->whereNotNull('p.presensi_shift_detail_id')
            ->where('p.id_status_aktif', 1)
            ->whereNull('pr.no_ktp')
            ->select([
                'p.id as pegawai_id',
                'p.no_ktp',
                's.id as shift_id',
                'sd.id as shift_detail_id',
                DB::raw('CASE 
                            WHEN p.terbantukan = 1 THEN 1
                            ELSE p.id_unit
                         END as unit_effective')
            ])
            ->get();


        $hariLiburToday = \App\Models\HariLibur::whereDate('tanggal', $today)
            ->pluck('unit_detail_id')
            ->toArray();

        $hariLiburUnits = array_flip($hariLiburToday);

        // Ambil data Izin, Sakit, Cuti yang sudah di-approve untuk hari ini
        $izinToday = \App\Models\PengajuanIzin::where('status', 'diterima')
            ->whereDate('tanggal_mulai', '<=', $today)
            ->whereDate('tanggal_selesai', '>=', $today)
            ->pluck('alasan', 'pegawai_id')
            ->toArray();

        $sakitToday = \App\Models\PengajuanSakit::where('status', 'diterima')
            ->whereDate('tanggal_mulai', '<=', $today)
            ->whereDate('tanggal_selesai', '>=', $today)
            ->pluck('alasan', 'pegawai_id')
            ->toArray();

        $cutiToday = \App\Models\PengajuanCuti::where('status', 'diterima')
            ->whereDate('tanggal_mulai', '<=', $today)
            ->whereDate('tanggal_selesai', '>=', $today)
            ->pluck('alasan', 'pegawai_id')
            ->toArray();

        $total = 0;


        foreach ($pegawaiTidakAbsen as $p) {

            $isHariLibur = isset($hariLiburUnits[$p->unit_effective]);

            // Default status
            $statusPresensi = $isHariLibur ? 'libur' : 'tidak_hadir';
            $keterangan = $isHariLibur ? 'Hari Libur' : 'Tidak hadir';

            // Cek Prioritas: Hari Libur > Izin/Sakit/Cuti > Tidak Hadir
            if (!$isHariLibur) {
                if (isset($izinToday[$p->pegawai_id])) {
                    $statusPresensi = 'izin';
                    $keterangan = "Pengajuan izin yang disetujui: " . $izinToday[$p->pegawai_id];
                } elseif (isset($sakitToday[$p->pegawai_id])) {
                    $statusPresensi = 'sakit';
                    $keterangan = "Pengajuan sakit yang disetujui: " . $sakitToday[$p->pegawai_id];
                } elseif (isset($cutiToday[$p->pegawai_id])) {
                    $statusPresensi = 'cuti';
                    $keterangan = "Pengajuan cuti yang disetujui: " . $cutiToday[$p->pegawai_id];
                }
            }

            Presensi::create([
                'no_ktp' => $p->no_ktp,
                'shift_id' => $p->shift_id,
                'shift_detail_id' => $p->shift_detail_id,

                'status_presensi' => $statusPresensi,
                'status_masuk'  => $statusPresensi,
                'status_pulang' => $statusPresensi,

                'waktu_masuk' => $today . ' 00:00:00',
                'waktu_pulang' => $today . ' 00:00:00',

                'lokasi_masuk' => null,
                'lokasi_pulang' => null,

                'keterangan_masuk' => $keterangan,
                'keterangan_pulang' => $keterangan,

                'overtime' => 0
            ]);

            $total++;
        }

        return response()->json([
            "status" => "success",
            "total_inserted" => $total
        ]);
    }
}
