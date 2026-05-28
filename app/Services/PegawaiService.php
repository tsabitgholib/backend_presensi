<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Helpers\AdminUnitHelper;

class PegawaiService
{
    public function index(Request $request)
    {
        $query = Pegawai::with(['unit', 'shift.details']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%$search%")
                    ->orWhere('no_ktp', 'like', "%$search%")
                    ->orWhereHas('unit', function ($q) use ($search) {
                        $q->where('nama_unit', 'like', "%$search%");
                    })
                    ->orWhereHas('shift', function ($q) use ($search) {
                        $q->where('nama', 'like', "%$search%");
                    });
            });
        } else {
            if ($request->filled('nama')) {
                $query->where('nama', 'like', '%' . $request->nama . '%');
            }
            if ($request->filled('nik')) {
                $query->where('no_ktp', 'like', '%' . $request->nik . '%');
            }
            if ($request->filled('unit')) {
                $query->whereHas('unit', function ($q) use ($request) {
                    $q->where('nama_unit', 'like', '%' . $request->unit . '%');
                });
            }
            if ($request->filled('shift')) {
                $query->whereHas('shift', function ($q) use ($request) {
                    $q->where('nama', 'like', '%' . $request->shift . '%');
                });
            }
        }

        $pegawais = $query->paginate(20);
        return response()->json($pegawais);
    }

    public function getByUnitIdPresensi(Request $request)
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

        $unitIds = [$unitId];

        $query = Pegawai::with(['unit', 'shift.details'])
            ->whereIn('unit_id', $unitIds);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%$search%")
                    ->orWhere('no_ktp', 'like', "%$search%")
                    ->orWhereHas('unit', function ($q) use ($search) {
                        $q->where('nama_unit', 'like', "%$search%");
                    })
                    ->orWhereHas('shift', function ($q) use ($search) {
                        $q->where('nama', 'like', "%$search%");
                    });
            });
        } else {
            if ($request->filled('nama')) {
                $query->where('nama', 'like', '%' . $request->nama . '%');
            }
            if ($request->filled('nik')) {
                $query->where('no_ktp', 'like', '%' . $request->nik . '%');
            }
            if ($request->filled('unit')) {
                $query->whereHas('unit', function ($q) use ($request) {
                    $q->where('nama_unit', 'like', '%' . $request->unit . '%');
                });
            }
            if ($request->filled('shift')) {
                $query->whereHas('shift', function ($q) use ($request) {
                    $q->where('nama', 'like', '%' . $request->shift . '%');
                });
            }
        }

        $pegawais = $query->paginate(20);
        return response()->json($pegawais);
    }

    public function getLokasiPresensi(Request $request)
    {
        $pegawai = $request->get('pegawai');
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $pegawai->load([
            'shift.details',
            'unit'
        ]);

        if (!$pegawai->unit) {
            return response()->json(['message' => 'Unit tidak ditemukan untuk pegawai ini'], 404);
        }

        $namaLengkap = $pegawai->nama;

        $unit = $pegawai->unit;
        $shift = $pegawai->shift;
        $shiftDetail = $shift?->details->first();

        $lokasi_presensi = [];

        if ($unit) {
            if (!empty($unit->lokasi) && is_array($unit->lokasi) && count($unit->lokasi) > 0) {
                $lokasi_presensi[] = [
                    'unit_id' => $unit->id,
                    'nama_lokasi' => $unit->nama_unit ?? null,
                    'polygon_lokasi' => $unit->lokasi,
                    'unit_name' => $unit->nama_unit ?? null,
                ];
            }

            if (!empty($unit->lokasi2) && is_array($unit->lokasi2) && count($unit->lokasi2) > 0) {
                $lokasi_presensi[] = [
                    'unit_id' => $unit->id,
                    'nama_lokasi' => ($unit->nama_unit ?? 'Unit') . ' - Area 2',
                    'polygon_lokasi' => $unit->lokasi2,
                    'unit_name' => $unit->nama_unit ?? null,
                ];
            }

            if (!empty($unit->lokasi3) && is_array($unit->lokasi3) && count($unit->lokasi3) > 0) {
                $lokasi_presensi[] = [
                    'unit_id' => $unit->id,
                    'nama_lokasi' => ($unit->nama_unit ?? 'Unit') . ' - Area 3',
                    'polygon_lokasi' => $unit->lokasi3,
                    'unit_name' => $unit->nama_unit ?? null,
                ];
            }
        }

        return response()->json([
            'pegawai_id' => $pegawai->id,
            'no_ktp' => $pegawai->no_ktp,
            'nama' => $namaLengkap,
            'lokasi_presensi' => $lokasi_presensi,
            'shift_info' => $shiftDetail ? [
                'shift_detail_id' => $shiftDetail->id,
                'shift_name' => $shift?->nama ?? null,
                'jam_kerja' => [
                    'senin' => [
                        'masuk' => $shiftDetail->senin_masuk,
                        'pulang' => $shiftDetail->senin_pulang
                    ],
                    'selasa' => [
                        'masuk' => $shiftDetail->selasa_masuk,
                        'pulang' => $shiftDetail->selasa_pulang
                    ],
                    'rabu' => [
                        'masuk' => $shiftDetail->rabu_masuk,
                        'pulang' => $shiftDetail->rabu_pulang
                    ],
                    'kamis' => [
                        'masuk' => $shiftDetail->kamis_masuk,
                        'pulang' => $shiftDetail->kamis_pulang
                    ],
                    'jumat' => [
                        'masuk' => $shiftDetail->jumat_masuk,
                        'pulang' => $shiftDetail->jumat_pulang
                    ],
                    'sabtu' => [
                        'masuk' => $shiftDetail->sabtu_masuk,
                        'pulang' => $shiftDetail->sabtu_pulang
                    ],
                    'minggu' => [
                        'masuk' => $shiftDetail->minggu_masuk,
                        'pulang' => $shiftDetail->minggu_pulang
                    ]
                ],
                'toleransi' => [
                    'terlambat' => $shiftDetail->toleransi_terlambat ?? 0,
                    'pulang' => $shiftDetail->toleransi_pulang ?? 0
                ]
            ] : null
        ]);
    }

    public function cekHariLibur(Request $request)
    {
        $pegawai = $request->get('pegawai');
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        $pegawai->load(['unit']);

        if (!$pegawai->unit) {
            return response()->json(['message' => 'Unit tidak ditemukan'], 404);
        }

        $today = \Carbon\Carbon::now('Asia/Jakarta')->toDateString();
        $bulan = $request->query('bulan', \Carbon\Carbon::now()->month);
        $tahun = $request->query('tahun', \Carbon\Carbon::now()->year);

        $isHariLibur = \App\Models\HariLibur::isHariLibur($pegawai->unit_id, $today);

        $listHariLibur = \App\Models\HariLibur::where('unit_id', $pegawai->unit_id)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->orderBy('tanggal')
            ->get();

        $response = [
            'is_hari_libur' => $isHariLibur,
            'tanggal_hari_ini' => $today,
            'unit' => [
                'id' => $pegawai->unit->id,
                'nama_unit' => $pegawai->unit->nama_unit
            ],
            'list_hari_libur' => $listHariLibur->map(function ($hariLibur) {
                return [
                    'id' => $hariLibur->id,
                    'tanggal' => $hariLibur->tanggal->format('Y-m-d'),
                    'keterangan' => $hariLibur->keterangan,
                    'created_at' => $hariLibur->created_at->format('Y-m-d H:i:s')
                ];
            })
        ];

        if ($isHariLibur) {
            $hariLiburHariIni = $listHariLibur->where('tanggal', $today)->first();
            $response['keterangan_hari_ini'] = $hariLiburHariIni->keterangan ?? 'Hari Libur';
        }

        return response()->json($response);
    }

    public function getByKepalaUnit(Request $request)
    {
        $pegawai = $request->get('pegawai');

        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
        }

        if ($pegawai->profesi !== 'Kepala Sekolah') {
            return response()->json([
                'message' => 'Anda bukan kepala unit!'
            ]);
        }

        $unitId = $pegawai->unit_id;

        $query = Pegawai::with(['unit', 'shift.details'])
            ->where('unit_id', $unitId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%$search%")
                    ->orWhere('no_ktp', 'like', "%$search%")
                    ->orWhereHas('unit', function ($q) use ($search) {
                        $q->where('nama_unit', 'like', "%$search%");
                    })
                    ->orWhereHas('shift', function ($q) use ($search) {
                        $q->where('nama', 'like', "%$search%");
                    });
            });
        } else {
            if ($request->filled('nama')) {
                $query->where('nama', 'like', '%' . $request->nama . '%');
            }
            if ($request->filled('nik')) {
                $query->where('no_ktp', 'like', '%' . $request->nik . '%');
            }
            if ($request->filled('unit')) {
                $query->whereHas('unit', function ($q) use ($request) {
                    $q->where('nama_unit', 'like', '%' . $request->unit . '%');
                });
            }
            if ($request->filled('shift')) {
                $query->whereHas('shift', function ($q) use ($request) {
                    $q->where('nama', 'like', '%' . $request->shift . '%');
                });
            }
        }

        $pegawais = $query->paginate(100);
        return response()->json($pegawais);
    }

    public function store(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        try {
            $pegawai = Pegawai::create($request->only([
                'nama',
                'no_ktp',
                'nip_unit',
                'unit_id',
                'shift_id',
                'profesi',
                'status',
                'status_lain',
            ]));
            return response()->json($pegawai, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function show($id)
    {
        $pegawai = Pegawai::with(['unit', 'shift.details'])->find($id);
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 404);
        }
        return response()->json($pegawai);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $pegawai = Pegawai::find($id);
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 404);
        }

        try {
            $pegawai->update($request->only([
                'nama',
                'no_ktp',
                'nip_unit',
                'unit_id',
                'shift_id',
                'profesi',
                'status',
                'status_lain',
            ]));
            return response()->json($pegawai);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy($id)
    {
        $admin = request()->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $pegawai = Pegawai::find($id);
        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan'], 404);
        }

        try {
            $pegawai->delete();
            return response()->json(['message' => 'Pegawai deleted']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
