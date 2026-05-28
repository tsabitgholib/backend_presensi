<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PegawaiService;

class PegawaiController extends Controller
{
    public function __construct(
        protected PegawaiService $pegawaiService
    ) {}

    public function index(Request $request)
    {
        return $this->pegawaiService->index($request);
    }

    public function getByUnitIdPresensi(Request $request)
    {
        return $this->pegawaiService->getByUnitIdPresensi($request);
    }

    public function getLokasiPresensi(Request $request)
    {
        return $this->pegawaiService->getLokasiPresensi($request);
    }

    public function cekHariLibur(Request $request)
    {
        return $this->pegawaiService->cekHariLibur($request);
    }

    public function getByKepalaUnit(Request $request)
    {
        return $this->pegawaiService->getByKepalaUnit($request);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string',
            'no_ktp' => 'required|string|unique:pegawai,no_ktp',
            'nip_unit' => 'nullable|string',
            'unit_id' => 'required|exists:unit,id',
            'shift_id' => 'nullable|exists:shift,id',
            'profesi' => 'nullable|string',
            'status' => 'nullable|in:aktif,nonaktif',
            'status_lain' => 'nullable|string',
        ]);

        return $this->pegawaiService->store($request);
    }

    public function show($id)
    {
        return $this->pegawaiService->show($id);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama' => 'sometimes|required|string',
            'no_ktp' => 'sometimes|required|string|unique:pegawai,no_ktp,' . $id,
            'nip_unit' => 'nullable|string',
            'unit_id' => 'sometimes|required|exists:unit,id',
            'shift_id' => 'nullable|exists:shift,id',
            'profesi' => 'nullable|string',
            'status' => 'nullable|in:aktif,nonaktif',
            'status_lain' => 'nullable|string',
        ]);

        return $this->pegawaiService->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->pegawaiService->destroy($id);
    }
}
