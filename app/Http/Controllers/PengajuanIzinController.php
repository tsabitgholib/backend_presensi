<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PengajuanIzin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PengajuanIzinController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'izin_id' => 'required|exists:mysql.izin,id',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'dokumen' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
        ]);

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

        $pengajuan = DB::table('sdi_presensi.pengajuan_izin as pc')
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

        $pengajuan = PengajuanIzin::findOrFail($id);

        $admin = $request->get('admin');
        $unitId = $admin->unit_id;
        // $isPegawaiInUnit = \App\Models\MsPegawai::where('id', $pengajuan->pegawai_id)
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
            $presensiController = new \App\Http\Controllers\PresensiController();
            $keterangan = "Pengajuan izin: {$pengajuan->alasan}";
            $presensiController->integratePengajuanToPresensi(
                $pengajuan->pegawai_id,
                'izin',
                $pengajuan->tanggal_mulai,
                $pengajuan->tanggal_selesai,
                $keterangan
            );
        } else {
            // Hapus dari presensi jika ditolak
            $presensiController = new \App\Http\Controllers\PresensiController();
            $presensiController->removePengajuanFromPresensi(
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
