<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Unit;
use App\Models\Pegawai;
use App\Helpers\AdminUnitHelper;

class UnitService
{
    public function index()
    {
        $units = Unit::whereNull('parent_id')->with('children.children')->get();
        return response()->json($units);
    }

    public function show($id)
    {
        $unit = Unit::find($id);

        if (!$unit) {
            return response()->json(['message' => 'Unit tidak ditemukan'], 404);
        }

        return response()->json($unit);
    }

    // public function getUnit()
    // {
    //     $query = Unit::whereNull('parent_id')->orWhere('level', 2)->get();
    //     return response()->json($query);
    // }

    // public function getUPK($unitId)
    // {
    //     $query = Unit::where('parent_id', $unitId)->orWhere('id', $unitId)->get();
    //     return response()->json($query);
    // }

    // public function getUnitsWithLocation(Request $request)
    // {
    //     $admin = $request->get('admin');
    //     if (!$admin) {
    //         return response()->json(['message' => 'Admin tidak ditemukan'], 401);
    //     }

    //     if ($admin->role === 'admin_unit') {
    //         $rootUnit = Unit::with('childrenRecursive')->find($admin->unit_id);

    //         if (!$rootUnit) {
    //             return response()->json(['message' => 'Unit admin tidak ditemukan'], 404);
    //         }

    //         $units = collect([$rootUnit])
    //             ->merge($this->flattenUnits($rootUnit->childrenRecursive))
    //             ->map(function ($unit) {
    //                 return [
    //                     'id' => $unit->id,
    //                     'nama_unit' => $unit->nama_unit,
    //                     'level' => $unit->level,
    //                     'parent_id' => $unit->parent_id,
    //                     'lokasi' => $unit->lokasi,
    //                     'lokasi2' => $unit->lokasi2,
    //                     'lokasi3' => $unit->lokasi3,
    //                 ];
    //             });
    //     } else {
    //         $units = Unit::with('childrenRecursive')->get()
    //             ->map(function ($unit) {
    //                 return [
    //                     'id' => $unit->id,
    //                     'nama_unit' => $unit->nama_unit,
    //                     'level' => $unit->level,
    //                     'parent_id' => $unit->parent_id,
    //                     'lokasi' => $unit->lokasi,
    //                     'lokasi2' => $unit->lokasi2,
    //                     'lokasi3' => $unit->lokasi3,
    //                 ];
    //             });
    //     }

    //     return response()->json($units);
    // }

    public function store(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $request->validate([
            'nama_unit' => 'required|string',
            'alias' => 'nullable|string',
            'parent_id' => 'nullable|exists:unit,id',
            'level' => 'required|integer',
            'lokasi' => 'nullable|array',
            'lokasi2' => 'nullable|array',
            'lokasi3' => 'nullable|array',
        ]);

        try {
            $unit = Unit::create($request->all());
            return response()->json(['message' => 'Success'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit tidak ditemukan'], 404);
        }

        $request->validate([
            'nama_unit' => 'sometimes|required|string',
            'alias' => 'nullable|string',
            'parent_id' => 'nullable|exists:unit,id',
            'level' => 'sometimes|required|integer',
            'lokasi' => 'nullable|array',
            'lokasi2' => 'nullable|array',
            'lokasi3' => 'nullable|array',
        ]);

        try {
            $unit->update($request->all());
            return response()->json(['message' => 'Success']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy($id)
    {
        $admin = request()->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit tidak ditemukan'], 404);
        }

        try {
            $unit->delete();
            return response()->json(['message' => 'Unit deleted']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function assignPegawai(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitId = (int) $request->unit_id;
        $pegawaiIds = collect($request->pegawai_ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($admin->role === 'admin_unit' && (int) $admin->unit_id !== $unitId) {
            return response()->json(['message' => 'Admin unit hanya boleh assign ke unit miliknya'], 403);
        }

        $updated = Pegawai::whereIn('id', $pegawaiIds)->update(['unit_id' => $unitId]);

        return response()->json([
            'message' => 'Pegawai berhasil di-assign ke unit',
            'unit_id' => $unitId,
            'pegawai_ids' => $pegawaiIds,
            'total_requested' => $pegawaiIds->count(),
            'total_updated' => $updated,
        ]);
    }

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

}
