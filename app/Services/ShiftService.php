<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Shift;
use App\Models\ShiftDetail;
use App\Helpers\AdminUnitHelper;
use Illuminate\Validation\Rule;

class ShiftService
{
    // CRUD Shift
    public function index(Request $request)
    {
        $admin = $request->get('admin');
        $unitId = null;
        if ($admin && $admin->role === 'admin_unit') {
            $unitId = $admin->unit_id;
            $query = Shift::with(['unit', 'shiftDetail'])
                ->where('unit_id', $unitId);
        } else {
            $query = Shift::with(['unit', 'shiftDetail']);
        }
        $data = $query->get()->map(function ($shift) use ($unitId) {
            return [
                'id' => $shift->id,
                'name' => $shift->name,
                'unit_name' => $shift->unit->nama ?? null,
                'unit_id' => $unitId ?? $shift->unit->id,
                'created_at' => $shift->created_at,
                'updated_at' => $shift->updated_at,
                'shift_detail' => $shift->shiftDetail
            ];
        });
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        // Get validation rules using helper
        

        // Get unit_id using helper
        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        try {
            $shift = Shift::create([
                'name' => $request->name,
                'unit_id' => $unitId,
            ]);
            return response()->json($shift);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['message' => 'Shift tidak ditemukan'], 404);
        }
        
        try {
            $shift->update($request->only('name'));
            return response()->json($shift);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy($id)
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['message' => 'Shift tidak ditemukan'], 404);
        }
        try {
            $shift->delete();
            return response()->json(['message' => 'Shift deleted']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // CRUD Shift Detail
    public function storeShiftDetail(Request $request)
    {
        
        try {
            $shiftDetail = ShiftDetail::create($request->all());
            return response()->json($shiftDetail);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function updateShiftDetail(Request $request, $id)
    {
        $shiftDetail = ShiftDetail::find($id);
        if (!$shiftDetail) {
            return response()->json(['message' => 'Shift detail tidak ditemukan'], 404);
        }
        
        try {
            $shiftDetail->update($request->all());
            return response()->json($shiftDetail);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroyShiftDetail($id)
    {
        $shiftDetail = ShiftDetail::find($id);
        if (!$shiftDetail) {
            return response()->json(['message' => 'Shift detail tidak ditemukan'], 404);
        }
        try {
            $shiftDetail->delete();
            return response()->json(['message' => 'Shift detail deleted']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function getByUnit($unit_id)
    {
        $shifts = Shift::where('unit_id', $unit_id)->with('shiftDetail')->get();
        return response()->json($shifts);
    }

    public function assignPegawaiToShiftDetail(Request $request)
    {
        try {
            

            $count = \App\Models\Pegawai::whereIn('id_orang', $request->pegawai_ids)
                ->update(['presensi_shift_detail_id' => $request->shift_detail_id]);

            return response()->json([
                'message' => 'Berhasil Menambahkan Pegawai ke Shift ini',
                'jumlah_pegawai_diupdate' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function getShiftDetailById($id)
    {
        $shiftDetail = ShiftDetail::with('shift')->find($id);
        if (!$shiftDetail) {
            return response()->json(['message' => 'Shift detail tidak ditemukan'], 404);
        }
        return response()->json($shiftDetail);
    }
}
