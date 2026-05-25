<?php

namespace App\Http\Controllers;

use App\Helpers\AdminUnitHelper;
use App\Models\Event;
use App\Models\PresensiEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{

    public function index(Request $request)
    {
        $admin = $request->get('admin');
        $active = $request->query('is_active');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        if ($request->filled('search')) {
            $search = $request->input('search');
            $events = Event::where('ms_unit_id', $unitId)
                ->where(function ($query) use ($search) {
                    $query->where('nama_event', 'like', "%$search%")
                        ->orWhere('deskripsi', 'like', "%$search%")
                        ->orWhere('tipe_event', 'like', "%$search%");
                });
            if ($active !== null) {
                $events->where('is_active', $active);
            }
            $events = $events->get();
            if (!$events || $events->isEmpty()) {
                return response()->json(['message' => 'Event tidak ditemukan'], 404);
            }
            return response()->json($events, 200);
        }
        if ($active === null) {
            $events = Event::where('ms_unit_id', $unitId)
                ->get();
        } else {
            $events = Event::where('ms_unit_id', $unitId)
                ->where('is_active', $active)
                ->get();
        }

        if (!$events || $events->isEmpty()) {
            return response()->json(['message' => 'Belum ada event'], 200);
        }
        return response()->json($events, 200);
    }


    public function show($id)
    {
        $event = Event::where('id', $id)
            ->first();

        if (!$event) {
            return response()->json(['message' => 'Event tidak ditemukan'], 404);
        }

        return response()->json($event, 200);
    }


    public function store(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

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

        if($request->tipe_event != 'Sholat Fardhu' && $request->tipe_event != 'Event & Kegiatan Islam') {
            return response()->json(['message' => 'Tipe Event tidak tersedia'], 422);
        }

        $event = Event::create([
            'ms_unit_id' => $unitId,
            'nama_event' => $request->nama_event ?? 'Event ' . date('Y-m-d H:i:s'),
            'deskripsi' => $request->deskripsi ?? $request->nama_event,
            'tipe_event' => $request->tipe_event,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'waktu_mulai' => $request->waktu_mulai ?? $request->waktu_masuk_mulai,
            'waktu_selesai' => $request->waktu_selesai ?? $request->waktu_pulang_selesai,
            'waktu_masuk_mulai' => $request->waktu_masuk_mulai,
            'waktu_masuk_selesai' => $request->waktu_masuk_selesai,
            'waktu_pulang_mulai' => $request->waktu_pulang_mulai,
            'waktu_pulang_selesai' => $request->waktu_pulang_selesai,
            // 'hari_mingguan' => $request->hari_mingguan,
            'nama_tempat' => $request->nama_tempat,
            'lokasi' => $request->lokasi ? json_encode($request->lokasi) : null,
            'lokasi2' => $request->lokasi2 ? json_encode($request->lokasi2) : null,
            'lokasi3' => $request->lokasi3 ? json_encode($request->lokasi3) : null,
            'is_active' => true,
        ]);

        if (!$event) {
            return response()->json(['message' => 'Gagal membuat event', 'error' => $event], 500);
        }
        return response()->json(['message' => 'Event berhasil dibuat', 'data' => $event], 200);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $event = Event::find($id);
        if (!$event) {
            return response()->json(['message' => 'Event tidak ditemukan'], 404);
        }

        // $access = AdminUnitHelper::validateUnitAccess($request, $event->ms_unit_id);
        // if (!$access['valid']) {
        //     return response()->json(['message' => $access['error']], 403);
        // }

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

        $data = [];

        if ($request->has('nama_event')) {
            $data['nama_event'] = $request->nama_event;
        }
        if ($request->has('deskripsi')) {
            $data['deskripsi'] = $request->deskripsi;
        }
        if ($request->has('tipe_event')) {
            $data['tipe_event'] = $request->tipe_event;
        }
        if ($request->has('tanggal_mulai')) {
            $data['tanggal_mulai'] = $request->tanggal_mulai;
        }
        if ($request->has('tanggal_selesai')) {
            $data['tanggal_selesai'] = $request->tanggal_selesai;
        }
        if ($request->has('waktu_mulai')) {
            $data['waktu_mulai'] = $request->waktu_mulai;
        }
        if ($request->has('waktu_selesai')) {
            $data['waktu_selesai'] = $request->waktu_selesai;
        }
        if ($request->has('waktu_masuk_mulai')) {
            $data['waktu_masuk_mulai'] = $request->waktu_masuk_mulai;
        }
        if ($request->has('waktu_masuk_selesai')) {
            $data['waktu_masuk_selesai'] = $request->waktu_masuk_selesai;
        }
        if ($request->has('waktu_pulang_mulai')) {
            $data['waktu_pulang_mulai'] = $request->waktu_pulang_mulai;
        }
        if ($request->has('waktu_pulang_selesai')) {
            $data['waktu_pulang_selesai'] = $request->waktu_pulang_selesai;
        }
        // if ($request->has('hari_mingguan')) {
        //     $data['hari_mingguan'] = $request->hari_mingguan;
        // }
        if ($request->has('nama_tempat')) {
            $data['nama_tempat'] = $request->nama_tempat;
        }
        if ($request->has('lokasi')) {
            $data['lokasi'] = $request->lokasi ? json_encode($request->lokasi) : null;
        }
        if ($request->has('lokasi2')) {
            $data['lokasi2'] = $request->lokasi2 ? json_encode($request->lokasi2) : null;
        }
        if ($request->has('lokasi3')) {
            $data['lokasi3'] = $request->lokasi3 ? json_encode($request->lokasi3) : null;
        }
        if ($request->has('is_active')) {
            $data['is_active'] = $request->is_active;
        }

        if (!empty($data)) {
            $event->update($data);
        }

        return response()->json(['message' => 'Event berhasil diperbarui', 'data' => $event]);
    }

    public function addPegawaiToEvent(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }
        $request->validate([
            'events_id' => 'required',
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:mysql_sdi.ms_orang,id',
        ]);

        $now = now();

        $pegawaiIdAsli = DB::select("SELECT id FROM sdi.v_pegawai WHERE id_orang IN (" . implode(',', $request->pegawai_ids) . ")");
        $pegawaiIdAsli = collect($pegawaiIdAsli)->pluck('id')->toArray();

        $data = collect($pegawaiIdAsli)->map(fn($id) => [
            'events_id' => $request->events_id,
            'pegawai_id' => $id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('events_pegawai')->insertOrIgnore($data->toArray());

        return response()->json(['message' => 'Pegawai ditambahkan']);
    }

    public function removePegawaiFromEvent(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }
        $request->validate([
            'events_id' => 'required',
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:mysql_sdi.ms_orang,id',
        ]);

        DB::table('events_pegawai')
            ->where('events_id', $request->events_id)
            ->whereIn('pegawai_id', $request->pegawai_ids)
            ->delete();

        return response()->json(['message' => 'Pegawai dihapus dari event']);
    }

    public function getListPegawaiByEvent(Request $request, $eventId)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $event = DB::table('events')
            ->where('id', $eventId)
            ->select('nama_event')
            ->first();

        if (!$event) {
            return response()->json(['message' => 'Event tidak ditemukan'], 404);
        }


        $pegawaiIds = DB::table('events_pegawai')
            ->where('events_id', $eventId)
            ->pluck('pegawai_id');

        if ($pegawaiIds->isEmpty()) {
            return response()->json([
                "message" => 'Tidak ada pegawai yang terdaftar pada event ini'
            ], 200);
        }


        $pegawaiList = DB::select("
            SELECT 
                p.id,
                TRIM(
                    CONCAT_WS(' ',
                        p.gelar_depan,
                        p.nama,
                        p.gelar_belakang
                    )
                ) AS nama_lengkap,
                p.no_ktp
            FROM sdi.v_pegawai p
            WHERE p.id IN (" . implode(',', $pegawaiIds->toArray()) . ")
            ORDER BY p.id ASC
        ");


        if (empty($pegawaiList)) {
            return response()->json(['message' => 'Tidak ada pegawai yang terdaftar pada event ini'], 404);
        }

        return response()->json([
            "nama_event" => $event->nama_event,
            "data" => $pegawaiList
        ], 200);
    }

    public function getHistoryPresensiEvent(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $tanggal = $request->query('tanggal');
        $tipeEvent = $request->query('tipe_event');
        $eventId = $request->query('events_id');

        $historyQuery = PresensiEvent::with('event:id,nama_event,ms_unit_id,tipe_event,waktu_mulai,waktu_masuk_mulai,waktu_masuk_selesai,waktu_pulang_mulai,waktu_pulang_selesai')
            ->join('events', 'presensi_event.events_id', '=', 'events.id')
            ->join('sdi.v_pegawai as p', 'p.no_ktp', '=', 'presensi_event.no_ktp')
            ->where('events.ms_unit_id', $unitId);

        if ($eventId) {
            $historyQuery->where('presensi_event.events_id', $eventId);
        }

        if ($tanggal) {
            $historyQuery->whereDate('presensi_event.created_at', $tanggal);
        }

        if ($tipeEvent) {
            $historyQuery->where('events.tipe_event', $tipeEvent);
        }

        $history = $historyQuery
            ->orderBy('presensi_event.created_at', 'desc')
            ->select(
                'presensi_event.*',
                'p.gelar_depan',
                'p.nama',
                'p.gelar_belakang'
            )
            ->get();

        $formatted = $history->map(function ($item) {
            $event = $item->event;

            $eventData = [
                'id' => $event->id,
                'nama_event' => $event->nama_event,
                'tipe_event' => $event->tipe_event,
            ];

            if ($event->tipe_event === 'Event & Kegiatan Islam') {
                $eventData['waktu_masuk_mulai'] = $event->waktu_masuk_mulai;
                $eventData['waktu_masuk_selesai'] = $event->waktu_masuk_selesai;
                $eventData['waktu_pulang_mulai'] = $event->waktu_pulang_mulai;
                $eventData['waktu_pulang_selesai'] = $event->waktu_pulang_selesai;
            } else {
                $eventData['waktu_mulai'] = $event->waktu_mulai;
            }

            $namaLengkap = trim(
                ($item->gelar_depan ? $item->gelar_depan . ' ' : '') .
                $item->nama .
                ($item->gelar_belakang ? ' ' . $item->gelar_belakang : '')
            );

            return [
                'id' => $item->id,
                'events_id' => $item->events_id,
                'no_ktp' => $item->no_ktp,
                'nama_pegawai' => $namaLengkap,
                'status_presensi' => $item->status_presensi,
                'status_masuk' => $item->status_masuk,
                'status_pulang' => $item->status_pulang,
                'tanggal' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                'jam' => $item->waktu_masuk,
                'jam_pulang' => $item->waktu_pulang,
                'event' => $eventData,
            ];
        });

        return response()->json($formatted, 200);
    }


    public function rekapPresensiEventPegawai(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $request->validate([
            'pegawai_id'    => 'required|integer',
            'events_id'     => 'required',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        $pegawaiId = $request->input('pegawai_id');
        $eventsInput = $request->input('events_id');
        $eventIds = is_array($eventsInput)
            ? $eventsInput
            : array_filter(array_map('intval', explode(',', (string) $eventsInput)));
        $tanggalMulai = $request->input('tanggal_mulai');
        $tanggalSelesai = $request->input('tanggal_selesai');

        $pegawai = DB::connection('mysql_sdi')->table('v_pegawai')
            ->where('id_orang', $pegawaiId)
            ->first();

        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 404);
        }

        // if ($pegawai->id_unit != $unitId && !($unitId == 1 && $pegawai->terbantukan == 1)) {
        //     return response()->json(['message' => 'Pegawai tidak termasuk dalam unit ini'], 403);
        // }

        $noKtp = $pegawai->no_ktp;

        $events = DB::table('events')
            ->whereIn('id', $eventIds)
            ->where('ms_unit_id', $unitId)
            ->select('id', 'nama_event', 'tanggal_mulai', 'tanggal_selesai')
            ->get()
            ->keyBy('id');

        if ($events->isEmpty()) {
            return response()->json([
                'pegawai' => [
                    'id' => $pegawai->id,
                    'nama' => $pegawai->nama,
                    'no_ktp' => $pegawai->no_ktp,
                ],
                'periode' => [
                    'tanggal_mulai' => $tanggalMulai,
                    'tanggal_selesai' => $tanggalSelesai,
                ],
                'events' => [],
                'summary' => [
                    'total_event_berlangsung' => 0,
                    'total_hadir' => 0,
                    'total_tidak_hadir' => 0,
                    'persentase_hadir' => 0,
                    'persentase_tidak_hadir' => 0,
                ]
            ], 200);
        }

        $presensiRows = DB::table('presensi_event')
            ->where('no_ktp', $noKtp)
            ->whereIn('events_id', $events->keys())
            ->whereBetween(DB::raw('DATE(created_at)'), [$tanggalMulai, $tanggalSelesai])
            ->whereNotIn('status_presensi', ['libur'])
            ->select(
                'events_id',
                'status_presensi',
                DB::raw('DATE(created_at) as tanggal')
            )
            ->get();

        $presensiMap = [];
        foreach ($presensiRows as $row) {
            $presensiMap[$row->events_id][$row->tanggal][] = $row;
        }

        $resultPerEvent = [];
        $grandTotal = 0;
        $grandHadir = 0;
        $grandTidakHadir = 0;

        foreach ($events as $eventId => $event) {
            $eventStart = $event->tanggal_mulai ?: $tanggalMulai;
            $eventEnd = $event->tanggal_selesai ?: $tanggalSelesai;

            if ($eventStart < $tanggalMulai) {
                $eventStart = $tanggalMulai;
            }
            if ($eventEnd > $tanggalSelesai) {
                $eventEnd = $tanggalSelesai;
            }

            if ($eventStart > $eventEnd) {
                continue;
            }

            $date = \Carbon\Carbon::parse($eventStart);
            $endDate = \Carbon\Carbon::parse($eventEnd);

            $totalEventHari = 0;
            $hadir = 0;
            $tidakHadir = 0;

            while ($date->lte($endDate)) {
                $tanggal = $date->format('Y-m-d');
                $totalEventHari++;

                $rowsTanggal = $presensiMap[$eventId][$tanggal] ?? [];

                $adaHadir = false;
                $adaTidakHadir = false;

                foreach ($rowsTanggal as $r) {
                    if ($r->status_presensi === 'hadir') {
                        $adaHadir = true;
                    } elseif ($r->status_presensi === 'tidak_hadir') {
                        $adaTidakHadir = true;
                    }
                }

                if ($adaHadir) {
                    $hadir++;
                } elseif ($adaTidakHadir || empty($rowsTanggal)) {
                    $tidakHadir++;
                }

                $date->addDay();
            }

            if ($totalEventHari === 0) {
                continue;
            }

            $grandTotal += $totalEventHari;
            $grandHadir += $hadir;
            $grandTidakHadir += $tidakHadir;

            $persenHadir = $totalEventHari > 0 ? round(($hadir / $totalEventHari) * 100, 2) : 0;
            $persenTidakHadir = $totalEventHari > 0 ? round(($tidakHadir / $totalEventHari) * 100, 2) : 0;

            $resultPerEvent[] = [
                'event_id' => $eventId,
                'nama_event' => $event->nama_event,
                'total_event_berlangsung' => $totalEventHari,
                'total_hadir' => $hadir,
                'total_tidak_hadir' => $tidakHadir,
                'persentase_hadir' => $persenHadir,
                'persentase_tidak_hadir' => $persenTidakHadir,
            ];
        }

        $overallPersenHadir = $grandTotal > 0 ? round(($grandHadir / $grandTotal) * 100, 2) : 0;
        $overallPersenTidakHadir = $grandTotal > 0 ? round(($grandTidakHadir / $grandTotal) * 100, 2) : 0;

        return response()->json([
            'pegawai' => [
                'id' => $pegawai->id,
                'nama' => $pegawai->nama,
                'no_ktp' => $pegawai->no_ktp,
            ],
            'periode' => [
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
            ],
            'events' => $resultPerEvent,
            'summary' => [
                'total_event_berlangsung' => $grandTotal,
                'total_hadir' => $grandHadir,
                'total_tidak_hadir' => $grandTidakHadir,
                'persentase_hadir' => $overallPersenHadir,
                'persentase_tidak_hadir' => $overallPersenTidakHadir,
            ]
        ], 200);
    }
}
