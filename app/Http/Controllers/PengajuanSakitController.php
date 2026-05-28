<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PengajuanSakitService;

class PengajuanSakitController extends Controller
{
    public function __construct(
        protected PengajuanSakitService $pengajuanSakitService
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'sakit_id' => 'required|exists:sakit,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'dokumen' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
                ]);

        return $this->pengajuanSakitService->store($request);
    }

    public function index(Request $request)
    {
        return $this->pengajuanSakitService->index($request);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:diterima,ditolak',
            'keterangan_admin' => 'nullable|string'
                ]);

        return $this->pengajuanSakitService->approve($request, $id);
    }

    public function history(Request $request)
    {
        return $this->pengajuanSakitService->history($request);
    }
}
