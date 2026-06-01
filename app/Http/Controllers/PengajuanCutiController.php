<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PengajuanCutiService;
use Illuminate\Validation\Rule;

class PengajuanCutiController extends Controller
{
    public function __construct(
        protected PengajuanCutiService $pengajuanCutiService
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'cuti_id' => ['required', Rule::exists('cuti', 'id')],
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'dokumen' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
                ]);

        return $this->pengajuanCutiService->store($request);
    }

    public function index(Request $request)
    {
        return $this->pengajuanCutiService->index($request);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:diterima,ditolak',
            'keterangan_admin' => 'nullable|string'
                ]);

        return $this->pengajuanCutiService->approve($request, $id);
    }

    public function history(Request $request)
    {
        return $this->pengajuanCutiService->history($request);
    }
}
