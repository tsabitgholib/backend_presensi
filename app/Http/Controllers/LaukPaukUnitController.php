<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LaukPaukUnit;
use App\Helpers\AdminUnitHelper;

class LaukPaukUnitController extends Controller
{
    public function index()
    {
        return response()->json(LaukPaukUnit::with('unit')->get());
    }

    public function show($id)
    {
        $data = LaukPaukUnit::with('unit')->find($id);
        if (!$data) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        return response()->json($data);
    }

    public function showByAdminUnit(Request $request)
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

        $laukPauk = \App\Models\LaukPaukUnit::where('unit_id', $unitId)->first();
        // if (!$laukPauk) {
        //     return response()->json(['unit_id' => $unitId, 'nominal' => 0]);
        // }
        return response()->json($laukPauk);
    }

    public function store(Request $request)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);

        $request->validate(array_merge([
            'nominal' => 'required|numeric|min:0',
            'pot_izin_pribadi' => 'nullable|numeric|min:0',
            'pot_tanpa_izin' => 'nullable|numeric|min:0',
            'pot_sakit' => 'nullable|numeric|min:0',
            'pot_pulang_awal_beralasan' => 'nullable|numeric|min:0',
            'pot_pulang_awal_tanpa_beralasan' => 'nullable|numeric|min:0',
            'pot_terlambat_0806_0900' => 'nullable|numeric|min:0',
            'pot_terlambat_0901_1000' => 'nullable|numeric|min:0',
            'pot_terlambat_setelah_1000' => 'nullable|numeric|min:0',
            'nom_lembur_permenit' => 'nullable|numeric|min:0',
            'nom_lembur_permenit_weekend' => 'nullable|numeric|min:0',
            'pot_tidak_absen_masuk' => 'nullable|numeric|min:0',
            'pot_tidak_absen_pulang' => 'nullable|numeric|min:0'
        ], $unitValidationRules));


        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }
        $unitId = $unitResult['unit_id'];

        $data = \App\Models\LaukPaukUnit::updateOrCreate(
            ['unit_id' => $unitId],
            [
                'nominal' => $request->nominal,
                'pot_izin_pribadi' => $request->pot_izin_pribadi ?? 0,
                'pot_tanpa_izin' => $request->pot_tanpa_izin ?? 0,
                'pot_sakit' => $request->pot_sakit ?? 0,
                'pot_pulang_awal_beralasan' => $request->pot_pulang_awal_beralasan ?? 0,
                'pot_pulang_awal_tanpa_beralasan' => $request->pot_pulang_awal_tanpa_beralasan ?? 0,
                'pot_terlambat_0806_0900' => $request->pot_terlambat_0806_0900 ?? 0,
                'pot_terlambat_0901_1000' => $request->pot_terlambat_0901_1000 ?? 0,
                'pot_terlambat_setelah_1000' => $request->pot_terlambat_setelah_1000 ?? 0,
                'nom_lembur_permenit' => $request->nom_lembur_permenit ?? 0,
                'nom_lembur_permenit_weekend' => $request->nom_lembur_permenit_weekend ?? 0,
                'pot_tidak_absen_masuk' => $request->pot_tidak_absen_masuk ?? 0,
                'pot_tidak_absen_pulang' => $request->pot_tidak_absen_pulang ?? 0,
            ]
        );
        return response()->json($data);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->get('admin');
        if (!$admin) {
            return response()->json(['message' => 'Admin tidak ditemukan'], 401);
        }

        $unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);

        $request->validate(array_merge([
            'nominal' => 'required|numeric|min:0',

            'pot_izin_pribadi' => 'nullable|numeric|min:0',
            'pot_tanpa_izin' => 'nullable|numeric|min:0',
            'pot_sakit' => 'nullable|numeric|min:0',
            'pot_pulang_awal_beralasan' => 'nullable|numeric|min:0',
            'pot_pulang_awal_tanpa_beralasan' => 'nullable|numeric|min:0',
            'pot_terlambat_0806_0900' => 'nullable|numeric|min:0',
            'pot_terlambat_0901_1000' => 'nullable|numeric|min:0',
            'pot_terlambat_setelah_1000' => 'nullable|numeric|min:0',
            'nom_lembur_permenit' => 'nullable|numeric|min:0',
            'nom_lembur_permenit_weekend' => 'nullable|numeric|min:0',
            'pot_tidak_absen_masuk' => 'nullable|numeric|min:0',
            'pot_tidak_absen_pulang' => 'nullable|numeric|min:0',

        ], $unitValidationRules));

        $unitResult = AdminUnitHelper::getUnitId($request);
        if ($unitResult['error']) {
            return response()->json(['message' => $unitResult['error']], 400);
        }

        $unitId = $unitResult['unit_id'];

        $data = \App\Models\LaukPaukUnit::where('unit_id', $unitId)->first();

        if (!$data) {
            $data = \App\Models\LaukPaukUnit::create([
                'unit_id' => $unitId,
                'nominal' => $request->nominal,
                'pot_izin_pribadi' => $request->pot_izin_pribadi ?? 0,
                'pot_tanpa_izin' => $request->pot_tanpa_izin ?? 0,
                'pot_sakit' => $request->pot_sakit ?? 0,
                'pot_pulang_awal_beralasan' => $request->pot_pulang_awal_beralasan ?? 0,
                'pot_pulang_awal_tanpa_beralasan' => $request->pot_pulang_awal_tanpa_beralasan ?? 0,
                'pot_terlambat_0806_0900' => $request->pot_terlambat_0806_0900 ?? 0,
                'pot_terlambat_0901_1000' => $request->pot_terlambat_0901_1000 ?? 0,
                'pot_terlambat_setelah_1000' => $request->pot_terlambat_setelah_1000 ?? 0,
                'nom_lembur_permenit' => $request->nom_lembur_permenit ?? 0,
                'nom_lembur_permenit_weekend' => $request->nom_lembur_permenit_weekend ?? 0,
                'pot_tidak_absen_masuk' => $request->pot_tidak_absen_masuk ?? 0,
                'pot_tidak_absen_pulang' => $request->pot_tidak_absen_pulang ?? 0,
            ]);
        } else {
            $updateData = ['nominal' => $request->nominal];

            $optionalFields = [
                'pot_izin_pribadi',
                'pot_tanpa_izin',
                'pot_sakit',
                'pot_pulang_awal_beralasan',
                'pot_pulang_awal_tanpa_beralasan',
                'pot_terlambat_0806_0900',
                'pot_terlambat_0901_1000',
                'pot_terlambat_setelah_1000',
                'nom_lembur_permenit',
                'nom_lembur_permenit_weekend',
                'pot_tidak_absen_masuk',
                'pot_tidak_absen_pulang',
            ];

            foreach ($optionalFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }

            $data->update($updateData);
        }

        return response()->json($data);
    }


    public function destroy($id)
    {
        $data = LaukPaukUnit::find($id);
        if (!$data) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        $data->delete();
        return response()->json(['message' => 'Berhasil dihapus']);
    }
}
