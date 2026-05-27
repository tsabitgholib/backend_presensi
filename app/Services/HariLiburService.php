<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\HariLibur;
use App\Models\UnitDetail;
use Carbon\Carbon;
use App\Helpers\AdminUnitHelper;
use Illuminate\Support\Facades\DB;

class HariLiburService
{
    /**
     * Tampilkan daftar hari libur berdasarkan admin unit yang login
     */
    public function index(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $bulan = $request->query('bulan');
        $tahun = $request->query('tahun');

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $query = HariLibur::where('unit_id', $unitId);

        if ($tahun && $bulan) {
            $query->whereYear('tanggal', $tahun)
                ->whereMonth('tanggal', $bulan);
        }

        $query->orderBy('tanggal');

        $hariLibur = $query->get();

        $result = $hariLibur->map(function ($hl) {
            return [
                'id' => $hl->id,
                'unit_id' => $hl->unit_id,
                'tanggal' => $hl->tanggal->format('Y-m-d'),
                'keterangan' => $hl->keterangan,
                'unit_name' => $hl->unit->nama_unit ?? null,
            ];
        });

        return response()->json($result);
    }

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

        $existingHariLibur = HariLibur::where('unit_id', $unitId)
            ->whereDate('tanggal', $request->tanggal)
            ->first();

        if ($existingHariLibur) {
            return response()->json(['message' => 'Hari libur untuk tanggal ini sudah ada'], 400);
        }

        try {
            $hariLibur = HariLibur::create([
                'unit_id' => $unitId,
                'tanggal' => $request->tanggal,
                'keterangan' => $request->keterangan,
                'admin_unit_id' => $admin->id,
            ]);

            $hariLibur->load(['unit', 'admin']);

            return response()->json([
                'message' => 'Hari libur berhasil ditambahkan',
                'data' => $hariLibur
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menambahkan hari libur: ' . $e->getMessage()], 400);
        }
    }

    public function storeMultiple(Request $request)
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

        if (!is_array($unitIds)) {
            $unitIds = [$unitIds];
        }

        $createdHariLibur = [];
        $errors = [];

        foreach ($unitIds as $unitId) {
            $existingHariLibur = HariLibur::where('unit_id', $unitId)
                ->whereDate('tanggal', $request->tanggal)
                ->first();

            if ($existingHariLibur) {
                $errors[] = "Hari libur untuk unit ID {$unitId} pada tanggal {$request->tanggal} sudah ada";
                continue;
            }

            try {
                $hariLibur = HariLibur::create([
                    'unit_id' => $unitId,
                    'tanggal' => $request->tanggal,
                    'keterangan' => $request->keterangan,
                    'admin_unit_id' => $admin->id,
                ]);
                $createdHariLibur[] = $hariLibur;
            } catch (\Exception $e) {
                $errors[] = "Gagal menambahkan hari libur untuk unit ID {$unitId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'message' => 'Proses penambahan sukses',
            'created_count' => count($createdHariLibur),
            'error_count' => count($errors),
            'created_data' => $createdHariLibur,
            'errors' => $errors
        ]);
    }

    /**
     * Update hari libur untuk multiple unit
     */
    public function updateMultiple(Request $request)
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

        if (!is_array($unitIds)) {
            $unitIds = [$unitIds];
        }

        $updated = HariLibur::whereIn('unit_id', $unitIds)
            ->whereDate('tanggal', $request->tanggal)
            ->update(['keterangan' => $request->keterangan]);

        return response()->json([
            'message' => 'Update sukses',
            'updated_count' => $updated
        ]);
    }

    /**
     * Delete hari libur untuk multiple unit
     */
    public function deleteMultiple(Request $request)
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
        $tanggal = $request->tanggal;

        if (!is_array($unitIds)) {
            $unitIds = [$unitIds];
        }

        $deleted = HariLibur::whereIn('unit_id', $unitIds)
            ->whereDate('tanggal', $tanggal)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Tidak ada data yang dihapus'], 404);
        }

        return response()->json([
            'message' => 'Delete sukses',
        ]);
    }
}
