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
}
