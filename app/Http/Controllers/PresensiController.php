<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PresensiService;

class PresensiController extends Controller
{
    public function __construct(
        protected PresensiService $presensiService
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'lokasi' => 'required|array|size:2',
                ]);

        return $this->presensiService->store($request);
    }

    public function today(Request $request)
    {
        return $this->presensiService->today($request);
    }

    public function history(Request $request)
    {
        return $this->presensiService->history($request);
    }

    public function rekapPresensiByAdminUnit(Request $request)
    {
        return $this->presensiService->rekapPresensiByAdminUnit($request);
    }

    public function rekapHistoryTahunanPegawai(Request $request)
    {
        return $this->presensiService->rekapHistoryTahunanPegawai($request);
    }

    public function historyByAdminUnit(Request $request)
    {
        return $this->presensiService->historyByAdminUnit($request);
    }

    public function rekapHistoryBulananPegawai(Request $request)
    {
        return $this->presensiService->rekapHistoryBulananPegawai($request);
    }

    public function detailHistoryByAdminUnit(Request $request)
    {
        return $this->presensiService->detailHistoryByAdminUnit($request);
    }

    public function updatePresensiByAdminUnitBulk(Request $request, $pegawai_id, $tanggal)
    {
        return $this->presensiService->updatePresensiByAdminUnitBulk($request, $pegawai_id, $tanggal);
    }

    public function rekapBulananUnitByAdmin(Request $request)
    {
        return $this->presensiService->rekapBulananUnitByAdmin($request);
    }

    public function rekapBulananByPegawai(Request $request)
    {
        return $this->presensiService->rekapBulananByPegawai($request);
    }

    public function hitungLemburMenit($waktuPulang)
    {
        return $this->presensiService->hitungLemburMenit($waktuPulang);
    }

    public function rekapPresensiBulananByAdminUnit(Request $request)
    {
        return $this->presensiService->rekapPresensiBulananByAdminUnit($request);
    }

    public function integratePengajuanToPresensi($pegawai_id, $jenis_pengajuan, $tanggal_mulai, $tanggal_selesai, $keterangan = null)
    {
        return $this->presensiService->integratePengajuanToPresensi($pegawai_id, $jenis_pengajuan, $tanggal_mulai, $tanggal_selesai, $keterangan);
    }

    public function removePengajuanFromPresensi($pegawai_id, $jenis_pengajuan, $tanggal_mulai, $tanggal_selesai)
    {
        return $this->presensiService->removePengajuanFromPresensi($pegawai_id, $jenis_pengajuan, $tanggal_mulai, $tanggal_selesai);
    }

    public function getLaporanKehadiranKaryawan(Request $request, $pegawai_id)
    {
        return $this->presensiService->getLaporanKehadiranKaryawan($request, $pegawai_id);
    }

    public function getOvertimePegawai(Request $request)
    {
        return $this->presensiService->getOvertimePegawai($request);
    }

    public function adminPresensiPegawai(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string|max:255',
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:pegawai,id',
                ]);

        return $this->presensiService->adminPresensiPegawai($request);
    }

    public function getSummaryPresensiUnit(Request $request)
    {
        return $this->presensiService->getSummaryPresensiUnit($request);
    }

    public function historyByKepalaUnit(Request $request)
    {
        return $this->presensiService->historyByKepalaUnit($request);
    }

    public function rekapPresensiHarianBulananByKepalaUnit(Request $request)
    {
        return $this->presensiService->rekapPresensiHarianBulananByKepalaUnit($request);
    }

    public function historyAll(Request $request)
    {
        return $this->presensiService->historyAll($request);
    }

    public function historyAllAdmin(Request $request)
    {
        return $this->presensiService->historyAllAdmin($request);
    }
}
