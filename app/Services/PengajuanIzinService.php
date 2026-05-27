<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\PengajuanIzin;
use Illuminate\Support\Facades\DB;

class PengajuanIzinService
{
    public function __construct(
        protected PresensiService $presensiService
    ) {}

    public function store(Request $request)
    {
        

        $data = $request->only(['izin_id', 'tanggal_mulai', 'tanggal_selesai', 'alasan']);
        $pegawai = $request->get('pegawai');
        $data['pegawai_id'] = $pegawai->id;

        if ($request->hasFile('dokumen')) {
            $file = $request->file('dokumen');
            $namaFile = time() . '_' . $file->getClientOriginalName();

            $file->move(public_path('storage/pengajuan_izin'), $namaFile);

            $data['dokumen'] = 'pengajuan_izin/' . $namaFile;
        }
        
        $pengajuan = PengajuanIzin::create($data);

        return response()->json(['message' => 'Pengajuan izin berhasil', 'data' => $pengajuan], 201);
    }


    public function index(Request $request)
    {
        $admin = $request->get('admin');
        $unitId = $admin->unit_id;

        $pengajuan = PengajuanIzin::with(['pegawai' => function ($query) {
                $query->select('id', 'nama');
            }])
            ->whereHas('pegawai', function ($q) use ($unitId) {
                $q->where('unit_id', $unitId);

                if ($unitId == 1) {
                    $q->orWhereRaw('1=1');
                }
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json($pengajuan);
    }


    public function approve(Request $request, $id)
    {
        

        $pengajuan = PengajuanIzin::findOrFail($id);

        $admin = $request->get('admin');
        $unitId = $admin->unit_id;
        // $isPegawaiInUnit = \App\Models\Pegawai::where('id', $pengajuan->pegawai_id)
        //     ->whereHas('unit', function($q) use ($unitId) {
        //         $q->where('unit_id', $unitId);
        //     })->exists();

        // if (!$isPegawaiInUnit) {
        //     return response()->json(['message' => 'Tidak berhak memproses pengajuan ini'], 403);
        // }

        $pengajuan->status = $request->status;
        $pengajuan->admin_unit_id = $admin->id;
        $pengajuan->keterangan_admin = $request->keterangan_admin;
        $pengajuan->save();

        // Integrasikan ke presensi jika diterima
        if ($request->status === 'diterima') {
            $keterangan = "Pengajuan izin: {$pengajuan->alasan}";
            $this->presensiService->integratePengajuanToPresensi(
                $pengajuan->pegawai_id,
                'izin',
                $pengajuan->tanggal_mulai,
                $pengajuan->tanggal_selesai,
                $keterangan
            );
        } else {
            // Hapus dari presensi jika ditolak
            $this->presensiService->removePengajuanFromPresensi(
                $pengajuan->pegawai_id,
                'izin',
                $pengajuan->tanggal_mulai,
                $pengajuan->tanggal_selesai
            );
        }

        return response()->json(['message' => 'Status pengajuan diperbarui', 'data' => $pengajuan]);
    }

    public function history(Request $request)
    {
        $pegawai = $request->get('pegawai');
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }
        $history = \App\Models\PengajuanIzin::where('pegawai_id', $pegawai->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($history);
    }
}
