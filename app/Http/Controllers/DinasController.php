<?php

namespace App\Http\Controllers;


use App\Helpers\AdminUnitHelper;
use Illuminate\Http\Request;
use App\Services\DinasService;

class DinasController extends Controller
{
    public function __construct(
        protected DinasService $dinasService
    ) {}

    public function store(Request $request)
    {
        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);
        
                $request->validate(array_merge([
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan' => 'required|string|max:255',
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:pegawai,id',
                ], $unitValidationRules));

        return $this->dinasService->store($request);
    }

    public function index(Request $request)
    {
        return $this->dinasService->index($request);
    }

    public function update(Request $request, $id)
    {
        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);
        
                $request->validate(array_merge([
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan'      => 'required|string|max:255',
            'pegawai_ids'     => 'required|array',
            'pegawai_ids.*'   => 'exists:pegawai,id',
                ], $unitValidationRules));

        return $this->dinasService->update($request, $id);
    }

    public function destroy(Request $request, $jadwal_dinas_id)
    {
        return $this->dinasService->destroy($request, $jadwal_dinas_id);
    }

    public function presensiDinas(Request $request)
    {
        return $this->dinasService->presensiDinas($request);
    }
}
