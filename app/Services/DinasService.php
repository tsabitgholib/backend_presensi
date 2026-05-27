<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Presensi;
use App\Models\Pegawai;
use App\Models\ShiftDetail;
use App\Models\PresensiJadwalDinas;
use Carbon\Carbon;
use App\Helpers\AdminUnitHelper;
use Illuminate\Support\Facades\Log;

class DinasService
{
    public function store(Request $request)
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

        $pegawais = Pegawai::whereIn('id', $request->pegawai_ids)
            ->where('unit_id', $unitId)
            ->get();

        try {
            $jadwalDinas = PresensiJadwalDinas::create([
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'keterangan' => $request->keterangan,
                'pegawai_ids' => $request->pegawai_ids,
                'unit_id' => $unitId,
                'created_by' => $admin->id,
                'is_active' => true
            ]);

            return response()->json([
                'message' => 'Jadwal dinas berhasil dibuat',
                'jadwal_dinas_id' => $jadwalDinas->id,
                'tanggal_mulai' => $jadwalDinas->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $jadwalDinas->tanggal_selesai->format('Y-m-d'),
                'keterangan' => $jadwalDinas->keterangan,
                'jumlah_pegawai' => count($request->pegawai_ids),
                'pegawai_list' => $pegawais->map(function ($pegawai) {
                    return [
                        'id' => $pegawai->id,
                        'nama' => $pegawai->nama,
                        'no_ktp' => $pegawai->no_ktp
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating jadwal dinas: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal membuat jadwal dinas: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getWaktuMasukShift($shiftDetail, $date)
    {
        $hari = strtolower($date->locale('id')->isoFormat('dddd'));
        $masukKey = $hari . '_masuk';
        $jamString = trim($shiftDetail->$masukKey ?? '');

        if (!$jamString) {
            return null;
        }

        try {
            $jamMasuk = Carbon::createFromFormat('H:i', $jamString);
            return $date->copy()->setTime($jamMasuk->hour, $jamMasuk->minute, 0);
        } catch (\Exception $e) {
            return null;
        }
    }

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

    public function index(Request $request)
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

        $bulan = $request->query('bulan', Carbon::now()->month);
        $tahun = $request->query('tahun', Carbon::now()->year);
        $pegawai_id = $request->query('pegawai_id');

        $start = Carbon::create($tahun, $bulan, 1, 0, 0, 0, 'Asia/Jakarta');
        $end = $start->copy()->endOfMonth();

        $query = PresensiJadwalDinas::active()
            ->where('unit_id', $unitId)
            ->inDateRange($start->toDateString(), $end->toDateString())
            ->with(['unit', 'createdBy'])
            ->orderBy('tanggal_mulai');

        if ($pegawai_id) {
            $query->whereJsonContains('pegawai_ids', $pegawai_id);
        }

        $jadwalDinas = $query->get();

        $pegawaiIds = collect();
        foreach ($jadwalDinas as $jadwal) {
            $pegawaiIds = $pegawaiIds->merge($jadwal->pegawai_ids);
        }
        $pegawaiIds = $pegawaiIds->unique();

        $pegawais = Pegawai::whereIn('id', $pegawaiIds)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($jadwalDinas as $jadwal) {
            $pegawaiList = [];
            foreach ($jadwal->pegawai_ids as $pegawaiId) {
                $pegawai = $pegawais->get($pegawaiId);
                if ($pegawai) {
                    $pegawaiList[] = [
                        'id' => $pegawai->id,
                        'nama' => $pegawai->nama,
                        'no_ktp' => $pegawai->no_ktp
                    ];
                }
            }

            $result[] = [
                'id' => $jadwal->id,
                'tanggal_mulai' => $jadwal->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $jadwal->tanggal_selesai->format('Y-m-d'),
                'keterangan' => $jadwal->keterangan,
                'unit' => $jadwal->unit ? $jadwal->unit->nama_unit : null,
                'created_by' => $jadwal->createdBy ? $jadwal->createdBy->name : null,
                'created_at' => $jadwal->created_at->format('Y-m-d H:i:s'),
                'pegawai_list' => $pegawaiList,
                'jumlah_pegawai' => count($pegawaiList)
            ];
        }

        return response()->json($result);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $jadwalDinas = PresensiJadwalDinas::find($id);
        if (!$jadwalDinas) {
            return response()->json(['message' => 'Jadwal dinas tidak ditemukan'], 404);
        }

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $pegawais = Pegawai::whereIn('id', $request->pegawai_ids)
            ->where('unit_id', $unitId)
            ->get();

        if ($pegawais->count() !== count($request->pegawai_ids)) {
            return response()->json(['message' => 'Beberapa pegawai tidak ditemukan atau tidak memiliki akses'], 400);
        }

        try {
            $jadwalDinas->update([
                'tanggal_mulai'   => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'keterangan'      => $request->keterangan,
                'pegawai_ids'     => $request->pegawai_ids,
                'unit_id'         => $unitId,
                'updated_by'      => $admin->id,
            ]);

            return response()->json([
                'message'        => 'Jadwal dinas berhasil diperbarui',
                'jadwal_dinas_id' => $jadwalDinas->id,
                'tanggal_mulai'  => $jadwalDinas->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $jadwalDinas->tanggal_selesai->format('Y-m-d'),
                'keterangan'     => $jadwalDinas->keterangan,
                'jumlah_pegawai' => count($request->pegawai_ids),
                'pegawai_list'   => $pegawais->map(function ($pegawai) {
                    return [
                        'id'     => $pegawai->id,
                        'nama'   => $pegawai->nama,
                        'no_ktp' => $pegawai->no_ktp
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating jadwal dinas: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal memperbarui jadwal dinas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $jadwal_dinas_id)
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

        $jadwalDinas = PresensiJadwalDinas::where('id', $jadwal_dinas_id)
            ->where('unit_id', $unitId)
            ->first();

        if (!$jadwalDinas) {
            return response()->json(['message' => 'Jadwal dinas tidak ditemukan atau tidak memiliki akses'], 404);
        }

        try {
            $jadwalDinas->delete();

            return response()->json([
                'message' => 'Jadwal dinas berhasil dihapus',
                'jadwal_dinas_id' => $jadwal_dinas_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting jadwal dinas: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menghapus jadwal dinas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function presensiDinas(Request $request)
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

        $bulan = $request->query('bulan', Carbon::now()->month);
        $tahun = $request->query('tahun', Carbon::now()->year);
        $pegawai_id = $request->query('pegawai_id');

        $pegawaiQuery = Pegawai::where('unit_id', $unitId);

        if ($pegawai_id) {
            $pegawaiQuery->where('id', $pegawai_id);
        }

        $pegawais = $pegawaiQuery->get();
        $noKtps = $pegawais->pluck('no_ktp')->filter();

        $start = Carbon::create($tahun, $bulan, 1, 0, 0, 0, 'Asia/Jakarta');
        $end = $start->copy()->endOfMonth();

        $presensiDinas = Presensi::whereIn('no_ktp', $noKtps)
            ->where('status_presensi', 'dinas')
            ->whereBetween('waktu_masuk', [$start->toDateString() . ' 00:00:00', $end->toDateString() . ' 23:59:59'])
            ->with(['shiftDetail.shift'])
            ->orderBy('waktu_masuk')
            ->get();

        $result = [];
        $pegawaiMap = $pegawais->keyBy('no_ktp');

        foreach ($presensiDinas as $presensi) {
            $pegawai = $pegawaiMap[$presensi->no_ktp] ?? null;
            if (!$pegawai) continue;

            $tanggal = $presensi->waktu_masuk->format('Y-m-d');
            $key = $pegawai->id . '_' . $tanggal;

            if (!isset($result[$key])) {
                $result[$key] = [
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'no_ktp' => $pegawai->no_ktp,
                        'nama' => $pegawai->nama,
                    ],
                    'tanggal' => $tanggal,
                    'hari' => $presensi->waktu_masuk->locale('id')->isoFormat('dddd'),
                    'waktu_masuk' => $presensi->waktu_masuk->format('H:i:s'),
                    'waktu_pulang' => $presensi->waktu_pulang ? $presensi->waktu_pulang->format('H:i:s') : null,
                    'keterangan' => $presensi->keterangan_masuk,
                    'shift_name' => $presensi->shiftDetail && $presensi->shiftDetail->shift ? $presensi->shiftDetail->shift->nama : null,
                    'presensi_id' => $presensi->id,
                ];
            }
        }

        return response()->json(array_values($result));
    }
}
