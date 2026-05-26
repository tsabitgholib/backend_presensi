<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\HariLiburService;

class HariLiburController extends Controller
{
    public function __construct(
        protected HariLiburService $hariLiburService
    ) {}

    public function index(Request $request)
    {
        return $this->hariLiburService->index($request);
    }

    public function store(Request $request)
    {
        $request->validate(array_merge([
            'unit_detail_id' => 'required',
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
                ], $unitDetailValidationRules));

        return $this->hariLiburService->store($request);
    }

    public function storeMultiple(Request $request)
    {
        $request->validate(array_merge([
            'unit_detail_ids' => 'required|array',
            //'unit_detail_ids.*' => 'exists:presensi_ms_unit_detail,id',
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
                ], $unitDetailIdsValidationRules));

        return $this->hariLiburService->storeMultiple($request);
    }

    public function updateMultiple(Request $request)
    {
        $request->validate(array_merge([
            'unit_detail_ids' => 'required|array',
            'unit_detail_ids.*' => 'exists:presensi_ms_unit_detail,ms_unit_id',
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
                ], $unitDetailIdsValidationRules));

        return $this->hariLiburService->updateMultiple($request);
    }

    public function deleteMultiple(Request $request)
    {
        $request->validate(array_merge([
            'unit_detail_ids' => 'required|array',
            'unit_detail_ids.*' => 'required',
            'tanggal' => 'required|date',
                ], $unitDetailIdsValidationRules));

        return $this->hariLiburService->deleteMultiple($request);
    }
}
