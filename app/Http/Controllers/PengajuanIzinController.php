<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PengajuanIzinService;

class PengajuanIzinController extends Controller
{
    public function __construct(
        protected PengajuanIzinService $pengajuanIzinService
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'izin_id' => 'required|exists:izin,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'dokumen' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
                ]);

        return $this->pengajuanIzinService->store($request);
    }

    public function index(Request $request)
    {
        return $this->pengajuanIzinService->index($request);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:diterima,ditolak',
            'keterangan_admin' => 'nullable|string'
                ]);

        return $this->pengajuanIzinService->approve($request, $id);
    }

    public function history(Request $request)
    {
        return $this->pengajuanIzinService->history($request);
    }
}
