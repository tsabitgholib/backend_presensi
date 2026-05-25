<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\Unit;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function index()
    {
        return response()->json(Admin::with('unit')->get());
    }

    public function indexMonitoring()
    {
        $admins = Admin::where('role', 'monitoring')->get();

        $result = $admins->map(function ($admin) {
            $unitIds = \App\Models\AdminMonitoringUnit::where('admin_id', $admin->id)
                ->pluck('unit_id')
                ->toArray();

            $units = Unit::whereIn('id', $unitIds)->get(['id', 'nama']);

            return [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'status' => $admin->status,
                'units' => $units,
            ];
        });

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:admin,email',
            'password' => 'required|min:6',
            'role' => 'required|in:super_admin,admin_unit',
            'unit_id' => 'nullable|exists:mysql_sdi.ms_unit,id',
            'status' => 'required|in:aktif,nonaktif',
        ]);
        try {
            $admin = Admin::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'unit_id' => $request->unit_id,
                'status' => $request->status,
            ]);
            return response()->json($admin);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function storeMonitoring(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:admin,email',
            'password' => 'required|min:6',
            'status' => 'required|in:aktif,nonaktif',
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:mysql_sdi.ms_unit,id',
        ]);

        try {
            $admin = Admin::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'monitoring',
                'unit_id' => null,
                'status' => $request->status,
            ]);

            foreach ($request->unit_ids as $unitId) {
                \App\Models\AdminMonitoringUnit::create([
                    'admin_id' => $admin->id,
                    'unit_id' => $unitId,
                ]);
            }

            return response()->json("Admin monitoring berhasil dibuat");
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        $admin = Admin::find($id);
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 404);
        }
        $request->validate([
            'name' => 'sometimes|required',
            'email' => 'sometimes|required|email|unique:admin,email,' . $id,
            'password' => 'nullable|min:6',
            'role' => 'sometimes|required|in:super_admin,admin_unit',
            'unit_id' => 'nullable|exists:mysql_sdi.ms_unit,id',
            'status' => 'sometimes|required|in:aktif,nonaktif',
        ]);
        try {
            $data = $request->only(['name', 'email', 'role', 'unit_id', 'status']);
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }
            $admin->update($data);
            return response()->json($admin);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function updateMonitoring(Request $request, $id)
    {
        $admin = Admin::where('role', 'monitoring')->where('id', $id)->first();
        if (!$admin) {
            return response()->json(['message' => 'Admin monitoring tidak ditemukan'], 404);
        }

        $request->validate([
            'name' => 'sometimes|required',
            'email' => 'sometimes|required|email|unique:admin,email,' . $id,
            'password' => 'nullable|min:6',
            'status' => 'sometimes|required|in:aktif,nonaktif',
            'unit_ids' => 'sometimes|array',
            'unit_ids.*' => 'exists:mysql_sdi.ms_unit,id',
        ]);

        try {
            $data = $request->only(['name', 'email', 'status']);
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }
            $admin->update($data);

            if ($request->has('unit_ids')) {
                \App\Models\AdminMonitoringUnit::where('admin_id', $admin->id)->delete();

                $unitIds = $request->unit_ids ?? [];
                foreach ($unitIds as $unitId) {
                    \App\Models\AdminMonitoringUnit::create([
                        'admin_id' => $admin->id,
                        'unit_id' => $unitId,
                    ]);
                }
            }

            return response()->json("Admin monitoring berhasil diperbarui");
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy($id)
    {
        $admin = Admin::find($id);
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 404);
        }
        try {
            $admin->delete();
            return response()->json(['message' => 'Admin deleted']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function getMonitoringUnits(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin || $admin->role !== 'monitoring') {
            return response()->json([
                'message' => 'Hanya akun monitoring yang dapat mengakses unit monitoring'
            ], 403);
        }

        $unitIds = \App\Models\AdminMonitoringUnit::where('admin_id', $admin->id)
            ->pluck('unit_id')
            ->toArray();

        $units = Unit::whereIn('id', $unitIds)->get(['id', 'nama']);

        $result = $units->map(function ($unit) {
            return [
                'id' => $unit->id,
                'nama' => $unit->nama,
            ];
        });

        return response()->json($result);
    }
} 
