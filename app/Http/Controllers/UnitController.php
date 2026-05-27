<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UnitService;

class UnitController extends Controller
{
    public function __construct(
        protected UnitService $unitService
    ) {}

    public function index()
    {
        return $this->unitService->index();
    }

    public function getUnit()
    {
        return $this->unitService->getUnit();
    }

    public function getUPK($unitId)
    {
        return $this->unitService->getUPK($unitId);
    }

    public function getUnitsWithLocation(Request $request)
    {
        return $this->unitService->getUnitsWithLocation($request);
    }

    public function store(Request $request)
    {
        return $this->unitService->store($request);
    }

    public function update(Request $request, $id)
    {
        return $this->unitService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->unitService->destroy($id);
    }
}
