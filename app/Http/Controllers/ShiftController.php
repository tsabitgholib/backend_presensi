<?php

namespace App\Http\Controllers;


use App\Helpers\AdminUnitHelper;
use Illuminate\Http\Request;
use App\Services\ShiftService;

class ShiftController extends Controller
{
    public function __construct(
        protected ShiftService $shiftService
    ) {}

    public function index(Request $request)
    {
        return $this->shiftService->index($request);
    }

    public function store(Request $request)
    {
        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);
        
                $request->validate(array_merge([
            'nama' => 'required',
                ], $unitValidationRules));

        return $this->shiftService->store($request);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama' => 'sometimes|required',
                ]);

        return $this->shiftService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->shiftService->destroy($id);
    }

    public function storeShiftDetail(Request $request)
    {
        $request->validate([
            'shift_id' => 'required|exists:shift,id',
            'senin_masuk' => 'nullable|date_format:H:i',
            'senin_pulang' => 'nullable|date_format:H:i',
            'selasa_masuk' => 'nullable|date_format:H:i',
            'selasa_pulang' => 'nullable|date_format:H:i',
            'rabu_masuk' => 'nullable|date_format:H:i',
            'rabu_pulang' => 'nullable|date_format:H:i',
            'kamis_masuk' => 'nullable|date_format:H:i',
            'kamis_pulang' => 'nullable|date_format:H:i',
            'jumat_masuk' => 'nullable|date_format:H:i',
            'jumat_pulang' => 'nullable|date_format:H:i',
            'sabtu_masuk' => 'nullable',
            'sabtu_pulang' => 'nullable',
            'minggu_masuk' => 'nullable',
            'minggu_pulang' => 'nullable',
            'toleransi_terlambat' => 'nullable|integer|min:0',
            'toleransi_pulang' => 'nullable|integer|min:0',
                ]);

        return $this->shiftService->storeShiftDetail($request);
    }

    public function updateShiftDetail(Request $request, $id)
    {
        $request->validate([
            'senin_masuk' => 'nullable|date_format:H:i',
            'senin_pulang' => 'nullable|date_format:H:i',
            'selasa_masuk' => 'nullable|date_format:H:i',
            'selasa_pulang' => 'nullable|date_format:H:i',
            'rabu_masuk' => 'nullable|date_format:H:i',
            'rabu_pulang' => 'nullable|date_format:H:i',
            'kamis_masuk' => 'nullable|date_format:H:i',
            'kamis_pulang' => 'nullable|date_format:H:i',
            'jumat_masuk' => 'nullable|date_format:H:i',
            'jumat_pulang' => 'nullable|date_format:H:i',
            'sabtu_masuk' => 'nullable',
            'sabtu_pulang' => 'nullable',
            'minggu_masuk' => 'nullable',
            'minggu_pulang' => 'nullable',
            'toleransi_terlambat' => 'nullable|integer|min:0',
            'toleransi_pulang' => 'nullable|integer|min:0',
                ]);

        return $this->shiftService->updateShiftDetail($request, $id);
    }

    public function destroyShiftDetail($id)
    {
        return $this->shiftService->destroyShiftDetail($id);
    }

    public function getByUnit($unit_id)
    {
        return $this->shiftService->getByUnit($unit_id);
    }

    public function assignPegawaiToShift(Request $request)
    {
        $request->validate([
                'shift_id' => 'required|exists:shift,id',
                'pegawai_ids' => 'required|array',
                'pegawai_ids.*' => 'exists:pegawai,id',
            ]);

        return $this->shiftService->assignPegawaiToShift($request);
    }

    public function getShiftDetailById($id)
    {
        return $this->shiftService->getShiftDetailById($id);
    }
}
