<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public static function getUnitIdValidationRules(Request $request, string $unitIdKey = 'unit_id'): array
    {
        $admin = $request->get('admin');
        
        if ($admin && $admin->role === 'super_admin') {
            return [$unitIdKey => ['required', Rule::exists('unit', 'id')]];
        }
        
        return [];
    }
}
