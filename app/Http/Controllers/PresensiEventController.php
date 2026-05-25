<?php

namespace App\Http\Controllers;

use App\Helpers\AdminUnitHelper;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\PresensiEvent;

class PresensiEventController extends Controller
{
    public function store(Request $request)
    {
        $pegawai = $request->get('pegawai');
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $no_ktp = $pegawai->no_ktp ?? ($pegawai->pegawai->no_ktp ?? null);

        if (!$no_ktp) {
            return response()->json(['message' => 'Data KTP pegawai tidak ditemukan'], 400);
        }

        $request->validate([
            'events_id' => 'required|exists:events,id',
            'lokasi' => 'required|array|min:2',
        ]);

        $eventId = $request->events_id;
        $userLocation = $request->lokasi;

        $event = Event::find($eventId);

        if (!$event) {
            return response()->json(['message' => 'Event tidak ditemukan'], 404);
        }

        $now = Carbon::now();
        $now->setLocale('id');
        $todayStr = $now->toDateString();
        $timeStr = $now->format('H:i:s');
        $hariIni = strtolower($now->isoFormat('dddd'));

        $dbLokasi = $event->lokasi;
        $dbLokasi2 = $event->lokasi2;
        $dbLokasi3 = $event->lokasi3;

        $polygon1 = $dbLokasi ? json_decode($dbLokasi, true) : null;
        $polygon2 = $dbLokasi2 ? json_decode($dbLokasi2, true) : null;
        $polygon3 = $dbLokasi3 ? json_decode($dbLokasi3, true) : null;

        $validLocation = false;

        if ($polygon1 && is_array($polygon1)) {
            if ($this->isPointInPolygon($userLocation, $polygon1)) {
                $validLocation = true;
            }
        }

        if (!$validLocation && $polygon2 && is_array($polygon2)) {
            if ($this->isPointInPolygon($userLocation, $polygon2)) {
                $validLocation = true;
            }
        }

        if (!$validLocation && $polygon3 && is_array($polygon3)) {
            if ($this->isPointInPolygon($userLocation, $polygon3)) {
                $validLocation = true;
            }
        }

        // if (!$validLocation) {
        //      return response()->json(['message' => 'Lokasi diluar area event (Lokasi 1, 2, atau 3)'], 400);
        // }

        $tipeEvent = $event->tipe_event;

        if ($tipeEvent == 'Sholat Fardhu') {
            if ($timeStr < $event->waktu_mulai || $timeStr > $event->waktu_selesai) {
                return response()->json(['message' => 'Belum waktunya presensi'], 400);
            }
            $existing = PresensiEvent::where('no_ktp', $no_ktp)
                ->where('events_id', $eventId)
                ->whereDate('created_at', $todayStr)
                ->first();
            
            if ($existing) {
                return response()->json(['message' => 'Anda sudah melakukan presensi hari ini'], 400);
            }

            $presensi = new PresensiEvent();
            $presensi->no_ktp = $no_ktp;
            $presensi->events_id = $eventId;
            $presensi->status_presensi = 'hadir';
            $presensi->waktu_masuk = $timeStr;
            $presensi->lokasi_masuk = json_encode($userLocation);
            $presensi->save();

            return response()->json($presensi, 200);

        // } elseif ($tipeEvent == 'mingguan') {
        //     // Mingguan: Cek Hari
        //     $hariEvent = strtolower($event->hari_mingguan);
        //     // if ($hariEvent != $hariIni) {
        //     //      return response()->json(['message' => 'Event tidak berlangsung hari ini'], 400);
        //     // }

        //     // if ($timeStr < $event->waktu_mulai) {
        //     //      return response()->json(['message' => 'Belum waktunya presensi'], 400);
        //     // }

        //     $existing = PresensiEvent::where('no_ktp', $no_ktp)
        //         ->where('events_id', $eventId)
        //         ->whereDate('created_at', $todayStr)
        //         ->first();

        //     if ($existing) {
        //         return response()->json(['message' => 'Anda sudah melakukan presensi hari ini'], 400);
        //     }

        //     $presensi = new PresensiEvent();
        //     $presensi->no_ktp = $no_ktp;
        //     $presensi->events_id = $eventId;
        //     $presensi->status_presensi = 'hadir';
        //     $presensi->waktu_masuk = $timeStr;
        //     $presensi->lokasi_masuk = json_encode($userLocation);
        //     $presensi->save();

        //     return response()->json($presensi, 200);

        } elseif ($tipeEvent == 'Event & Kegiatan Islam') {
            if ($todayStr < $event->tanggal_mulai || $todayStr > $event->tanggal_selesai) {
                 return response()->json(['message' => 'Event tidak berlangsung hari ini'], 400);
            }

            $isMasukRange = ($timeStr >= $event->waktu_masuk_mulai && $timeStr <= $event->waktu_masuk_selesai);
            $isPulangRange = ($timeStr >= $event->waktu_pulang_mulai && $timeStr <= $event->waktu_pulang_selesai);

            if (!$isMasukRange && !$isPulangRange) {
                return response()->json(['message' => 'Belum waktunya presensi'], 400);
            }

            $existing = PresensiEvent::where('no_ktp', $no_ktp)
                ->where('events_id', $eventId)
                ->whereDate('created_at', $todayStr)
                ->first();

            if ($isMasukRange) {
                if ($existing) {
                    return response()->json(['message' => 'Anda sudah absen masuk hari ini'], 400);
                }
                $presensi = new PresensiEvent();
                $presensi->no_ktp = $no_ktp;
                $presensi->events_id = $eventId;
                $presensi->status_presensi = 'hadir';
                $presensi->status_masuk = 'absen_masuk';
                $presensi->waktu_masuk = $timeStr;
                $presensi->lokasi_masuk = json_encode($userLocation);
                $presensi->save();
                
                return response()->json($presensi, 200);
            }

            if ($isPulangRange) {
                if (!$existing) {
                    return response()->json(['message' => 'Anda belum absen masuk'], 400);
                }
                if ($existing->waktu_pulang) {
                    return response()->json(['message' => 'Anda sudah absen pulang hari ini'], 400);
                }
                
                $existing->status_pulang = 'absen_pulang';
                $existing->waktu_pulang = $timeStr;
                $existing->lokasi_pulang = json_encode($userLocation);
                $existing->save();

                return response()->json($existing, 200);
            }

        } else {
             return response()->json(['message' => 'Tipe event tidak valid'], 400);
        }

        return response()->json(['message' => 'error'], 500);

    }

    private function isPointInPolygon($point, $polygon)
    {
        $x = $point[0];
        $y = $point[1];
        $inside = false;
        $n = count($polygon);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];
            $intersect = (($yi > $y) != ($yj > $y)) &&
                ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-10) + $xi);
            if ($intersect) $inside = !$inside;
        }
        return $inside;
    }

    public function getListEventsPegawai(Request $request)
    {
        $pegawai = $request->get('pegawai');
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $active     = $request->query('is_active', 1);
        $tipeEvent  = $request->query('tipe_event');
        $today      = Carbon::today();

        //limit 2
        $events = Event::select('events.*')
            ->join('events_pegawai', 'events.id', '=', 'events_pegawai.events_id')
            ->where('events_pegawai.pegawai_id', $pegawai->pegawai->id)
            // ->limit(2)
            ->when($tipeEvent, function ($query) use ($tipeEvent) {
                $query->where('events.tipe_event', $tipeEvent);
            })

            ->where(function ($query) use ($active, $today) {
                if ($active == 1) {
                    $query->where('events.is_active', 1)
                        ->where(function ($q) use ($today) {
                            $q->whereNull('events.tanggal_mulai')
                                ->orWhereDate('events.tanggal_mulai', '>=', $today);
                        });
                } else {
                    $query->where(function ($q) use ($today) {
                        $q->where('events.is_active', 0)
                            ->orWhereDate('events.tanggal_mulai', '<', $today);
                    });
                }
            })
            ->orderBy('events.id', 'asc')
            ->get();

        if ($events->isEmpty()) {
            return response()->json(['message' => 'Belum ada event'], 200);
        }

        return response()->json($events, 200);
    }

    public function getAllEventName(Request $request)
    {
        $pegawai = $request->get('pegawai');
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $events = Event::select('events.id', 'events.nama_event')
            ->join('events_pegawai', 'events.id', '=', 'events_pegawai.events_id')
            ->where('events_pegawai.pegawai_id', $pegawai->pegawai->id);

        $events = $events->get();
        if ($events->isEmpty()) {
            return response()->json(['message' => 'Belum ada event'], 200);
        }
        return response()->json($events, 200);
    }


    public function getHistoryPresensiByEventPegawai(Request $request, $eventId)
    {
        $pegawai = $request->get('pegawai');
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $no_ktp = $pegawai->no_ktp ?? ($pegawai->pegawai->no_ktp ?? null);
        if (!$no_ktp) {
            return response()->json(['message' => 'Data KTP pegawai tidak ditemukan'], 400);
        }

        $history = PresensiEvent::with('event:id,nama_event')
            ->where('no_ktp', $no_ktp)
            ->where('events_id', $eventId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $tipeEvent = Event::find($eventId)->tipe_event;

        if ($history->isEmpty()) {
            return response()->json(['message' => 'Belum ada riwayat presensi event'], 200);
        }

        $event = $history->first()->event;

        return response()->json([
            'nama_event'       => $event->nama_event,
            'tipe_event'       => $tipeEvent,
            'jumlah_presensi'  => $history->count(),
            'data'             => $history->map(function ($item) {
                return [
                    'id'              => $item->id,
                    'status_presensi' => $item->status_presensi,
                    'tanggal'         => $item->tanggal ?? $item->created_at->format('Y-m-d'),
                    'jam'             => $item->waktu_masuk ?? $item->created_at->format('H:i:s'),
                    'jam_pulang'      => $item->waktu_pulang ?? null,
                ];
            })
        ], 200);
    }

}
