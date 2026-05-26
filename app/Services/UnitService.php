<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Unit;
use App\Models\UnitDetail;
use App\Helpers\AdminUnitHelper;
use Illuminate\Support\Facades\DB;

class UnitService
{
    public function index()
    {
        return response()->json(Unit::with('children.children')->get());
    }

    public function getUnit()
    {
        $query = DB::select("
            SELECT * FROM sdi.ms_unit
            WHERE id_parent IS NULL OR level = 2
        ");

        return response()->json($query);
    }

    public function getUPK($unitId){
        $query = DB::select("select * from sdi.ms_unit where id_parent = '$unitId' or id = '$unitId';");

        return response()->json($query);
    }


    public function getUnitsWithLocation(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        if ($admin->role === 'admin_unit') {
            $rootUnit = Unit::with('childrenRecursive')->find($admin->unit_id);

            if (!$rootUnit) {
                return response()->json(['message' => 'Unit admin tidak ditemukan'], 404);
            }

        $units = collect([$rootUnit])
            ->merge($this->flattenUnits($rootUnit->childrenRecursive))
            ->map(function ($unit) {
                $unitDetail = $unit->unitDetails->first();
                return [
                    'id' => $unit->id,
                    'nama' => $unit->nama,
                    'level' => $unit->level,
                    'id_parent' => $unit->id_parent,
                    'lokasi' => $unitDetail ? $unitDetail->lokasi : null,
                    'lokasi2' => $unitDetail ? $unitDetail->lokasi2 : null,
                    'lokasi3' => $unitDetail ? $unitDetail->lokasi3 : null,
                ];
            });
        } else {
            $units = Unit::with('childrenRecursive', 'unitDetails')->get()
                ->map(function ($unit) {
                    $unitDetail = $unit->unitDetails->first();
                    return [
                        'id' => $unit->id,
                        'nama' => $unit->nama,
                        'level' => $unit->level,
                        'id_parent' => $unit->id_parent,
                        'lokasi' => $unitDetail ? $unitDetail->lokasi : null,
                        'lokasi2' => $unitDetail ? $unitDetail->lokasi2 : null,
                        'lokasi3' => $unitDetail ? $unitDetail->lokasi3 : null,
                    ];
                });
        }

        return response()->json($units);
    }

    /**
     * Helper flattening tree jadi list
     */
    private function flattenUnits($units)
    {
        $result = collect();

        foreach ($units as $unit) {
            $result->push($unit);

            if ($unit->childrenRecursive->isNotEmpty()) {
                $result = $result->merge($this->flattenUnits($unit->childrenRecursive));
            }
        }

        return $result;
    }




    // Hapus method store, update, destroy
}
