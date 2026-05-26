<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\PengajuanSakit;
use Illuminate\Support\Facades\DB;

class PengajuanSakitService
{
    public function __construct(
        protected PresensiService $presensiService
    ) {}

    public function store(Request $request)
    {
        

        $data = $request->only(['sakit_id', 'tanggal_mulai', 'tanggal_selesai', 'alasan']);
        $pegawai = $request->get('pegawai');
        $data['pegawai_id'] = $pegawai->id;

        if ($request->hasFile('dokumen')) {
            $file = $request->file('dokumen');
            $namaFile = time() . '_' . $file->getClientOriginalName();

            $file->move(public_path('storage/pengajuan_sakit'), $namaFile);

            $data['dokumen'] = 'pengajuan_sakit/' . $namaFile;
        }

        $pengajuan = PengajuanSakit::create($data);

        return response()->json(['message' => 'Pengajuan sakit berhasil', 'data' => $pengajuan], 201);
    }

    public function index(Request $request)
    {
        $admin = $request->get('admin');
        $unitId = $admin->unit_id;

        $pengajuan = DB::table('sdi_presensi.pengajuan_sakit as pc')
            ->join('sdi.v_pegawai as p', 'p.id', '=', 'pc.pegawai_id')
            ->select('pc.*', 'p.nama')
            ->where(function ($q) use ($unitId) {
                $q->where('p.id_unit', $unitId);

                if ($unitId == 1) {
                    $q->orWhere('p.terbantukan', 1);
                }
            })
            ->orderBy('pc.id', 'desc')
            ->paginate(10);

        return response()->json($pengajuan);
    }


    public function approve(Request $request, $id)
    {
        

        $pengajuan = PengajuanSakit::findOrFail($id);

        $admin = $request->get('admin');
        $unitId = $admin->unit_id;
        // $isPegawaiInUnit = \App\Models\Pegawai::where('id', $pengajuan->pegawai_id)
        //     ->whereHas('unitDetail', function($q) use ($unitId) {
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
            $keterangan = "Pengajuan sakit: {$pengajuan->alasan}";
            $this->presensiService->integratePengajuanToPresensi(
                $pengajuan->pegawai_id,
                'sakit',
                $pengajuan->tanggal_mulai,
                $pengajuan->tanggal_selesai,
                $keterangan
            );
        } else {
            // Hapus dari presensi jika ditolak
            $this->presensiService->removePengajuanFromPresensi(
                $pengajuan->pegawai_id,
                'sakit',
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
        $history = \App\Models\PengajuanSakit::where('pegawai_id', $pegawai->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($history);
    }
}
