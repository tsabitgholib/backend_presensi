<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class AdminUnitHelper
{

    public static function getUnitId(Request $request, string $unitIdKey = 'unit_id'): array
    {
        $admin = $request->get('admin');
        
        if (!$admin) {
            return ['unit_id' => null, 'error' => 'Admin tidak ditemukan'];
        }

        if ($admin->role === 'admin_unit') {
            $unitId = $admin->unit_id;
            if (!$unitId) {
                return ['unit_id' => null, 'error' => 'Unit tidak ditemukan untuk admin unit ini'];
            }
            return ['unit_id' => $unitId, 'error' => null];
        }

        if ($admin->role === 'super_admin') {
            $unitId = $request->input($unitIdKey);
            if (!$unitId) {
                return ['unit_id' => null, 'error' => "{$unitIdKey} wajib diisi untuk super admin"];
            }
            return ['unit_id' => $unitId, 'error' => null];
        }

        return ['unit_id' => null, 'error' => 'Unauthorized'];
    }

    public static function getUnitDetailIds(Request $request, string $unitDetailIdsKey = 'unit_detail_ids'): array
    {
        $admin = $request->get('admin');
        
        if (!$admin) {
            return ['unit_detail_ids' => [], 'error' => 'Admin tidak ditemukan'];
        }

        if ($admin->role === 'admin_unit') {
            $unitDetails = \App\Models\UnitDetail::where('ms_unit_id', $admin->unit_id)->get();
            $unitDetailIds = $unitDetails->pluck('ms_unit_id')->toArray();
            return ['unit_detail_ids' => $unitDetailIds, 'error' => null];
        }

        if ($admin->role === 'super_admin') {
            $unitDetailIds = $request->input($unitDetailIdsKey, []);
            if (empty($unitDetailIds)) {
                return ['unit_detail_ids' => [], 'error' => "{$unitDetailIdsKey} wajib diisi untuk super admin"];
            }
            return ['unit_detail_ids' => $unitDetailIds, 'error' => null];
        }

        return ['unit_detail_ids' => [], 'error' => 'Unauthorized'];
    }


    public static function validateUnitAccess(Request $request, int $unitId): array
    {
        $admin = $request->get('admin');
        
        if (!$admin) {
            return ['valid' => false, 'error' => 'Admin tidak ditemukan'];
        }

        if ($admin->role === 'admin_unit') {
            if ($admin->unit_id != $unitId) {
                return ['valid' => false, 'error' => 'Tidak memiliki akses ke unit ini'];
            }
            return ['valid' => true, 'error' => null];
        }

        if ($admin->role === 'super_admin') {
            return ['valid' => true, 'error' => null];
        }

        return ['valid' => false, 'error' => 'Unauthorized'];
    }


    public static function validateUnitDetailAccess(Request $request, int $unitDetailId): array
    {
        $admin = $request->get('admin');
        
        if (!$admin) {
            return ['valid' => false, 'error' => 'Admin tidak ditemukan'];
        }

        $unitDetail = \App\Models\UnitDetail::find($unitDetailId);
        if (!$unitDetail) {
            return ['valid' => false, 'error' => 'Unit detail tidak ditemukan'];
        }

        return self::validateUnitAccess($request, $unitDetail->unit_id);
    }

    public static function getUnitIdValidationRules(Request $request, string $unitIdKey = 'unit_id'): array
    {
        $admin = $request->get('admin');
        
        if ($admin && $admin->role === 'super_admin') {
            return [$unitIdKey => 'required|exists:mysql_sdi.ms_unit,id'];
        }
        
        return [];
    }

    public static function getUnitDetailIdsValidationRules(Request $request, string $unitDetailIdsKey = 'unit_detail_ids'): array
    {
        $admin = $request->get('admin');
        
        if ($admin && $admin->role === 'super_admin') {
            return [
                $unitDetailIdsKey => 'required|array',
                $unitDetailIdsKey . '.*' => 'exists:mysql.presensi_ms_unit_detail,id'
            ];
        }
        
        return [];
    }
}
