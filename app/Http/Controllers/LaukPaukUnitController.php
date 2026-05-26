<?php

namespace App\Http\Controllers;


use App\Helpers\AdminUnitHelper;
use Illuminate\Http\Request;
use App\Services\LaukPaukUnitService;

class LaukPaukUnitController extends Controller
{
    public function __construct(
        protected LaukPaukUnitService $laukPaukUnitService
    ) {}

    public function index()
    {
        return $this->laukPaukUnitService->index();
    }

    public function show($id)
    {
        return $this->laukPaukUnitService->show($id);
    }

    public function showByAdminUnit(Request $request)
    {
        return $this->laukPaukUnitService->showByAdminUnit($request);
    }

    public function store(Request $request)
    {
        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);
        
                $request->validate(array_merge([
            'nominal' => 'required|numeric|min:0',
            'pot_izin_pribadi' => 'nullable|numeric|min:0',
            'pot_tanpa_izin' => 'nullable|numeric|min:0',
            'pot_sakit' => 'nullable|numeric|min:0',
            'pot_pulang_awal_beralasan' => 'nullable|numeric|min:0',
            'pot_pulang_awal_tanpa_beralasan' => 'nullable|numeric|min:0',
            'pot_terlambat_0806_0900' => 'nullable|numeric|min:0',
            'pot_terlambat_0901_1000' => 'nullable|numeric|min:0',
            'pot_terlambat_setelah_1000' => 'nullable|numeric|min:0',
            'nom_lembur_permenit' => 'nullable|numeric|min:0',
            'nom_lembur_permenit_weekend' => 'nullable|numeric|min:0',
            'pot_tidak_absen_masuk' => 'nullable|numeric|min:0',
            'pot_tidak_absen_pulang' => 'nullable|numeric|min:0'
                ], $unitValidationRules));

        return $this->laukPaukUnitService->store($request);
    }

    public function update(Request $request, $id)
    {
        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);
        
                $request->validate(array_merge([
            'nominal' => 'required|numeric|min:0',
        
            'pot_izin_pribadi' => 'nullable|numeric|min:0',
            'pot_tanpa_izin' => 'nullable|numeric|min:0',
            'pot_sakit' => 'nullable|numeric|min:0',
            'pot_pulang_awal_beralasan' => 'nullable|numeric|min:0',
            'pot_pulang_awal_tanpa_beralasan' => 'nullable|numeric|min:0',
            'pot_terlambat_0806_0900' => 'nullable|numeric|min:0',
            'pot_terlambat_0901_1000' => 'nullable|numeric|min:0',
            'pot_terlambat_setelah_1000' => 'nullable|numeric|min:0',
            'nom_lembur_permenit' => 'nullable|numeric|min:0',
            'nom_lembur_permenit_weekend' => 'nullable|numeric|min:0',
            'pot_tidak_absen_masuk' => 'nullable|numeric|min:0',
            'pot_tidak_absen_pulang' => 'nullable|numeric|min:0',
        
                ], $unitValidationRules));

        return $this->laukPaukUnitService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->laukPaukUnitService->destroy($id);
    }
}
