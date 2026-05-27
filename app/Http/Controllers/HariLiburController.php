<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\HariLiburService;
use App\Helpers\AdminUnitHelper;

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
        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);
        
        $request->validate(array_merge([
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
        ], $unitValidationRules));

        return $this->hariLiburService->store($request);
    }

    public function storeMultiple(Request $request)
    {
        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);
        
        $request->validate(array_merge([
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
        ], $unitValidationRules));

        return $this->hariLiburService->storeMultiple($request);
    }

    public function updateMultiple(Request $request)
    {
        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);
        
        $request->validate(array_merge([
            'tanggal' => 'required|date',
            'keterangan' => 'required|string|max:255',
        ], $unitValidationRules));

        return $this->hariLiburService->updateMultiple($request);
    }

    public function deleteMultiple(Request $request)
    {
        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);
        
        $request->validate(array_merge([
            'tanggal' => 'required|date',
        ], $unitValidationRules));

        return $this->hariLiburService->deleteMultiple($request);
    }
}
