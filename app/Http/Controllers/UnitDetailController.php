<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UnitDetailService;

class UnitDetailController extends Controller
{
    public function __construct(
        protected UnitDetailService $unitDetailService
    ) {}

    public function index($unit_id)
    {
        return $this->unitDetailService->index($unit_id);
    }

    public function getAll()
    {
        return $this->unitDetailService->getAll();
    }

    public function updateLocation(Request $request, $unit_id)
    {
        $request->validate([
            'lokasi' => 'array',
            'lokasi2' => 'array',
            'lokasi3' => 'array',
                ]);

        return $this->unitDetailService->updateLocation($request, $unit_id);
    }

    public function show($id)
    {
        return $this->unitDetailService->show($id);
    }

    public function assignPegawai(Request $request)
    {
        $request->validate([
            'unit_detail_id' => 'required|exists:presensi_ms_unit_detail,id',
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:mysql_sdi.ms_orang,id',
                ]);

        return $this->unitDetailService->assignPegawai($request);
    }
}
