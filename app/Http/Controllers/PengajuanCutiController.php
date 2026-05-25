<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PengajuanCuti;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PengajuanCutiController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'cuti_id' => 'required|exists:mysql.cuti,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'dokumen' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
        ]);

        $data = $request->only(['cuti_id', 'tanggal_mulai', 'tanggal_selesai', 'alasan']);
        $pegawai = $request->get('pegawai');
        $data['pegawai_id'] = $pegawai->id;

        if ($request->hasFile('dokumen')) {
            $file = $request->file('dokumen');
            $namaFile = time() . '_' . $file->getClientOriginalName();

            $file->move(public_path('storage/pengajuan_cuti'), $namaFile);

            $data['dokumen'] = 'pengajuan_cuti/' . $namaFile;
        }

        $pengajuan = PengajuanCuti::create($data);

        return response()->json(['message' => 'Pengajuan cuti berhasil', 'data' => $pengajuan], 201);
    }

    public function index(Request $request)
    {
        $admin = $request->get('admin');
        $unitId = $admin->unit_id;

        $pengajuan = DB::table('sdi_presensi.pengajuan_cuti as pc')
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
        $request->validate([
            'status' => 'required|in:diterima,ditolak',
            'keterangan_admin' => 'nullable|string'
        ]);

        $pengajuan = PengajuanCuti::findOrFail($id);

        $admin = $request->get('admin');
        $unitId = $admin->unit_id;
        // $isPegawaiInUnit = \App\Models\MsPegawai::where('id', $pengajuan->pegawai_id)
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
            $presensiController = new \App\Http\Controllers\PresensiController();
            $keterangan = "{$pengajuan->alasan}";
            $presensiController->integratePengajuanToPresensi(
                $pengajuan->pegawai_id,
                'cuti',
                $pengajuan->tanggal_mulai,
                $pengajuan->tanggal_selesai,
                $keterangan
            );
        } else {
            // Hapus dari presensi jika ditolak
            $presensiController = new \App\Http\Controllers\PresensiController();
            $presensiController->removePengajuanFromPresensi(
                $pengajuan->pegawai_id,
                'cuti',
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
        $history = \App\Models\PengajuanCuti::where('pegawai_id', $pegawai->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($history);
    }
}
