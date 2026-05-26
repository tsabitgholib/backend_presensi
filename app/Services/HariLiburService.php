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
     * Tampilkan daftar hari libur untuk unit detail tertentu
     */
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

        $query = "
        SELECT 
            hl.id,
            hl.unit_detail_id,
            hl.tanggal,
            hl.keterangan,
            ud.nama AS unit_detail_name,
            (
                SELECT nama 
                FROM sdi.ms_unit 
                WHERE id = (
                    SELECT id_parent 
                    FROM sdi.ms_unit 
                    WHERE id = hl.unit_detail_id
                    LIMIT 1
                )
                LIMIT 1
            ) AS unit_name
        FROM sdi_presensi.hari_libur hl
        LEFT JOIN sdi.ms_unit AS ud ON ud.id = hl.unit_detail_id
        WHERE hl.unit_detail_id = ?
    ";

        $bindings = [$unitId];

        if ($tahun && $bulan) {
            $query .= " AND YEAR(hl.tanggal) = ? AND MONTH(hl.tanggal) = ?";
            $bindings[] = $tahun;
            $bindings[] = $bulan;
        }

        $query .= " ORDER BY hl.tanggal";

        $hariLibur = DB::select($query, $bindings);

        $result = collect($hariLibur)->map(function ($hl) {
            return [
                'id' => $hl->id,
                'unit_detail_id' => $hl->unit_detail_id,
                'tanggal' => date('Y-m-d', strtotime($hl->tanggal)),
                'keterangan' => $hl->keterangan,
                'unit_name' => $hl->unit_name ?? $hl->unit_detail_name,
                'unit_detail_name' => $hl->unit_detail_name ?? $hl->unit_name,
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

        $unitDetailValidationRules = AdminUnitHelper::getUnitIdValidationRules($request, 'unit_detail_id');

        

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $unitDetail = UnitDetail::where('ms_unit_id', $unitId)
            ->first();

        if (!$unitDetail) {
            return response()->json(['message' => 'Unit detail tidak ditemukan'], 404);
        }

        $existingHariLibur = HariLibur::where('unit_detail_id', $request->unit_detail_id)
            ->whereDate('tanggal', $request->tanggal)
            ->first();

        if ($existingHariLibur) {
            return response()->json(['message' => 'Hari libur untuk tanggal ini sudah ada'], 400);
        }

        try {
            $hariLibur = HariLibur::create([
                'unit_detail_id' => $unitId,
                'tanggal' => $request->tanggal,
                'keterangan' => $request->keterangan,
                'admin_unit_id' => $admin->id,
            ]);

            $hariLibur->load(['unitDetail', 'adminUnit']);

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

        $unitDetailIdsValidationRules = AdminUnitHelper::getUnitDetailIdsValidationRules($request);

        

        $unitDetailIdsResult = AdminUnitHelper::getUnitDetailIds($request);
        if ($unitDetailIdsResult['error']) {
            return response()->json(['message' => $unitDetailIdsResult['error']], 400);
        }
        $unitDetailIds = $unitDetailIdsResult['unit_detail_ids'];

        $unitDetails = UnitDetail::whereIn('ms_unit_id', $unitDetailIds)->get();

        if ($unitDetails->count() !== count($unitDetailIds)) {
            return response()->json(['message' => 'Beberapa unit detail tidak ditemukan atau tidak memiliki akses'], 400);
        }

        $createdHariLibur = [];
        $errors = [];

        foreach ($unitDetailIds as $unitDetailId) {
            $existingHariLibur = HariLibur::where('unit_detail_id', $unitDetailId)
                ->whereDate('tanggal', $request->tanggal)
                ->first();

            if ($existingHariLibur) {
                $errors[] = "Hari libur untuk unit detail ID {$unitDetailId} pada tanggal {$request->tanggal} sudah ada";
                continue;
            }

            try {
                $hariLibur = HariLibur::create([
                    'unit_detail_id' => $unitDetailId,
                    'tanggal' => $request->tanggal,
                    'keterangan' => $request->keterangan,
                    'admin_unit_id' => $admin->id,
                ]);
                $createdHariLibur[] = $hariLibur;
            } catch (\Exception $e) {
                $errors[] = "Gagal menambahkan hari libur untuk unit detail ID {$unitDetailId}: " . $e->getMessage();
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
     * Update hari libur untuk multiple unit detail
     */
    public function updateMultiple(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        // Get validation rules using helper
        $unitDetailIdsValidationRules = AdminUnitHelper::getUnitDetailIdsValidationRules($request);

        

        // Get unit_detail_ids using helper
        $unitDetailIdsResult = AdminUnitHelper::getUnitDetailIds($request);
        if ($unitDetailIdsResult['error']) {
            return response()->json(['message' => $unitDetailIdsResult['error']], 400);
        }
        $unitDetailIds = $unitDetailIdsResult['unit_detail_ids'];

        // Validasi bahwa semua unit detail milik unit admin
        $unitDetails = UnitDetail::whereIn('id', $unitDetailIds)->get();
        if ($unitDetails->count() !== count($unitDetailIds)) {
            return response()->json(['message' => 'Beberapa unit detail tidak ditemukan atau tidak memiliki akses'], 400);
        }

        $updated = HariLibur::whereIn('unit_detail_id', $unitDetailIds)
            ->whereDate('tanggal', $request->tanggal)
            ->update(['keterangan' => $request->keterangan]);

        return response()->json([
            'message' => 'Update sukses',
            'updated_count' => $updated
        ]);
    }

    /**
     * Delete hari libur untuk multiple unit detail
     */
    public function deleteMultiple(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitDetailIdsValidationRules = AdminUnitHelper::getUnitDetailIdsValidationRules($request);

        

        $unitDetailIdsResult = AdminUnitHelper::getUnitDetailIds($request);
        if ($unitDetailIdsResult['error']) {
            return response()->json(['message' => $unitDetailIdsResult['error']], 400);
        }
        $unitDetailIds = $unitDetailIdsResult['unit_detail_ids'];
        $tanggal = $request->tanggal;

        $unitDetails = UnitDetail::whereIn('id', $unitDetailIds)->get();
        if ($unitDetails->count() !== count($unitDetailIds)) {
            return response()->json(['message' => 'Beberapa unit detail tidak ditemukan atau tidak memiliki akses'], 400);
        }

        $deleted = DB::table('hari_libur')
            ->whereIn('unit_detail_id', $unitDetailIds)
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
