<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\EventService;

class EventController extends Controller
{
    public function __construct(
        protected EventService $eventService
    ) {}

    public function index(Request $request)
    {
        return $this->eventService->index($request);
    }

    public function show($id)
    {
        return $this->eventService->show($id);
    }

    public function store(Request $request)
    {
        $request->validate(array_merge([
            'nama_event' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tipe_event' => 'required|string|max:100',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'waktu_mulai' => 'nullable|date_format:H:i',
            'waktu_selesai' => 'nullable|date_format:H:i',
            'waktu_masuk_mulai' => 'nullable|date_format:H:i',
            'waktu_masuk_selesai' => 'nullable|date_format:H:i',
            'waktu_pulang_mulai' => 'nullable|date_format:H:i',
            'waktu_pulang_selesai' => 'nullable|date_format:H:i',
            // 'hari_mingguan' => 'nullable|string|max:20',
            'nama_tempat' => 'nullable|string|max:150',
            'lokasi' => 'nullable',
            'lokasi2' => 'nullable',
            'lokasi3' => 'nullable',
                ]));

        return $this->eventService->store($request);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_event' => 'sometimes|required|string|max:255',
            'deskripsi' => 'nullable|string',
            'tipe_event' => 'sometimes|required|string|max:100',
            'tanggal_mulai' => 'sometimes|required|date',
            'tanggal_selesai' => 'sometimes|required|date|after_or_equal:tanggal_mulai',
            'waktu_mulai' => 'sometimes|required|date_format:H:i',
            'waktu_selesai' => 'sometimes|required|date_format:H:i',
            'waktu_masuk_mulai' => 'sometimes|date_format:H:i',
            'waktu_masuk_selesai' => 'sometimes|date_format:H:i',
            'waktu_pulang_mulai' => 'sometimes|date_format:H:i',
            'waktu_pulang_selesai' => 'sometimes|date_format:H:i',
            // 'hari_mingguan' => 'nullable|string|max:20',
            'nama_tempat' => 'nullable|string|max:150',
            'lokasi' => 'nullable',
            'lokasi2' => 'nullable',
            'lokasi3' => 'nullable',
            'is_active' => 'sometimes|required|boolean',
                ]);

        return $this->eventService->update($request, $id);
    }

    public function addPegawaiToEvent(Request $request)
    {
        $request->validate([
            'events_id' => 'required',
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:mysql_sdi.ms_orang,id',
                ]);

        return $this->eventService->addPegawaiToEvent($request);
    }

    public function removePegawaiFromEvent(Request $request)
    {
        $request->validate([
            'events_id' => 'required',
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:mysql_sdi.ms_orang,id',
                ]);

        return $this->eventService->removePegawaiFromEvent($request);
    }

    public function getListPegawaiByEvent(Request $request, $eventId)
    {
        return $this->eventService->getListPegawaiByEvent($request, $eventId);
    }

    public function getHistoryPresensiEvent(Request $request)
    {
        return $this->eventService->getHistoryPresensiEvent($request);
    }

    public function rekapPresensiEventPegawai(Request $request)
    {
        $request->validate([
            'pegawai_id'    => 'required|integer',
            'events_id'     => 'required',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
                ]);

        return $this->eventService->rekapPresensiEventPegawai($request);
    }
}
