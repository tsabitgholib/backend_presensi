<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AdminService;

class AdminController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    public function index()
    {
        return $this->adminService->index();
    }

    public function indexMonitoring()
    {
        return $this->adminService->indexMonitoring();
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

        return $this->adminService->store($request);
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

        return $this->adminService->storeMonitoring($request);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required',
            'email' => 'sometimes|required|email|unique:admin,email,' . $id,
            'password' => 'nullable|min:6',
            'role' => 'sometimes|required|in:super_admin,admin_unit',
            'unit_id' => 'nullable|exists:mysql_sdi.ms_unit,id',
            'status' => 'sometimes|required|in:aktif,nonaktif',
                ]);

        return $this->adminService->update($request, $id);
    }

    public function updateMonitoring(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required',
            'email' => 'sometimes|required|email|unique:admin,email,' . $id,
            'password' => 'nullable|min:6',
            'status' => 'sometimes|required|in:aktif,nonaktif',
            'unit_ids' => 'sometimes|array',
            'unit_ids.*' => 'exists:mysql_sdi.ms_unit,id',
                ]);

        return $this->adminService->updateMonitoring($request, $id);
    }

    public function destroy($id)
    {
        return $this->adminService->destroy($id);
    }

    public function getMonitoringUnits(Request $request)
    {
        return $this->adminService->getMonitoringUnits($request);
    }
}
