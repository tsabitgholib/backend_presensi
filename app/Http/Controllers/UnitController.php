<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UnitService;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function __construct(
        protected UnitService $unitService
    ) {}

    public function index()
    {
        return $this->unitService->index();
    }

    public function show($id)
    {
        return $this->unitService->show($id);
    }

    // public function getUnit()
    // {
    //     return $this->unitService->getUnit();
    // }

    // public function getUPK($unitId)
    // {
    //     return $this->unitService->getUPK($unitId);
    // }

    // public function getUnitsWithLocation(Request $request)
    // {
    //     return $this->unitService->getUnitsWithLocation($request);
    // }

    public function store(Request $request)
    {
        return $this->unitService->store($request);
    }

    public function update(Request $request, $id)
    {
        return $this->unitService->update($request, $id);
    }

    public function addPegawaiTounit(Request $request)
    {
        $request->validate([
            'unit_id' => ['required', Rule::exists('unit', 'id')],
            'pegawai_ids' => 'required|array|min:1',
            'pegawai_ids.*' => ['required', 'integer', 'distinct', Rule::exists('pegawai', 'id')],
        ]);

        return $this->unitService->addPegawaiTounit($request);
    }

    public function destroy($id)
    {
        return $this->unitService->destroy($id);
    }
}
