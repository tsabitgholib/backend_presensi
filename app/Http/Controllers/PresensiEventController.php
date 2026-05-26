<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PresensiEventService;

class PresensiEventController extends Controller
{
    public function __construct(
        protected PresensiEventService $presensiEventService
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'events_id' => 'required|exists:events,id',
            'lokasi' => 'required|array|min:2',
                ]);

        return $this->presensiEventService->store($request);
    }

    public function getListEventsPegawai(Request $request)
    {
        return $this->presensiEventService->getListEventsPegawai($request);
    }

    public function getAllEventName(Request $request)
    {
        return $this->presensiEventService->getAllEventName($request);
    }

    public function getHistoryPresensiByEventPegawai(Request $request, $eventId)
    {
        return $this->presensiEventService->getHistoryPresensiByEventPegawai($request, $eventId);
    }

    public function getPresensiByEventId(Request $request, $id)
    {
        return $this->presensiEventService->getPresensiByEventId($request, $id);
    }
}
