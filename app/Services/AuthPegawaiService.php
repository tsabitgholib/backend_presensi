<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Helpers\JWT;
use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;

class AuthPegawaiService
{
    public function login(Request $request)
    {
        $pegawai = Pegawai::where('no_ktp', $request->no_ktp)->first();

        if (!$pegawai || $request->password !== $pegawai->no_ktp) {
            return response()->json(['message' => 'NIK atau password salah'], 401);
        }

        $payload = [
            'sub' => $pegawai->id,
            'no_ktp' => $pegawai->no_ktp,
            'role' => 'pegawai',
            'tenant_schema' => env('CLIENT_SCHEMA'),
            'iat' => time(),
            'exp' => time() + 86400
        ];

        $token = JWT::encode($payload, env('JWT_SECRET'));

        return response()->json([
            'message' => 'Login berhasil',
            'token' => $token
        ]);
    }


    public function me(Request $request)
    {
        $pegawai = $request->get('pegawai');

        $pegawai->load([
            'shift.details',
            'unit'
        ]);

        $namaLengkap = $pegawai->nama;

        $unit = $pegawai->unit;
        $shift = $pegawai->shift;
        $shiftDetail = $shift?->details->first();

        $lokasi_presensi = [];

        if ($unit) {
            // lokasi utama
            if (!empty($unit->lokasi) && is_array($unit->lokasi) && count($unit->lokasi) > 0) {
                $lokasi_presensi[] = [
                    'unit_id' => $unit->id,
                    'nama_lokasi' => $unit->nama_unit ?? null,
                    'polygon_lokasi' => $unit->lokasi,
                    'unit_name' => $unit->nama_unit ?? null,
                ];
            }

            // lokasi 2
            if (!empty($unit->lokasi2) && is_array($unit->lokasi2) && count($unit->lokasi2) > 0) {
                $lokasi_presensi[] = [
                    'unit_id' => $unit->id,
                    'nama_lokasi' => ($unit->nama_unit ?? 'Unit') . ' - Area 2',
                    'polygon_lokasi' => $unit->lokasi2,
                    'unit_name' => $unit->nama_unit ?? null,
                ];
            }

            // lokasi 3
            if (!empty($unit->lokasi3) && is_array($unit->lokasi3) && count($unit->lokasi3) > 0) {
                $lokasi_presensi[] = [
                    'unit_id' => $unit->id,
                    'nama_lokasi' => ($unit->nama_unit ?? 'Unit') . ' - Area 3',
                    'polygon_lokasi' => $unit->lokasi3,
                    'unit_name' => $unit->nama_unit ?? null,
                ];
            }
        }

        $kepala_unit = $pegawai->profesi == 'Kepala Sekolah';

        $response = [
            'id' => $pegawai->id,
            'no_ktp' => $pegawai->no_ktp,
            'nama' => $pegawai->nama,
            'tmpt_lahir' => null,
            'tgl_lahir' => null,
            'jenis_kelamin' => null,
            'no_hp' => null,
            'jabatan' => $pegawai->profesi,
            'kepala_unit' => $kepala_unit,
            'shift_id' => $pegawai->shift_id,
            'shift_detail' => $shiftDetail ? [
                'id' => $shiftDetail->id,
                'shift_id' => $shiftDetail->shift_id,
                'senin_masuk' => $shiftDetail->senin_masuk,
                'senin_pulang' => $shiftDetail->senin_pulang,
                'selasa_masuk' => $shiftDetail->selasa_masuk,
                'selasa_pulang' => $shiftDetail->selasa_pulang,
                'rabu_masuk' => $shiftDetail->rabu_masuk,
                'rabu_pulang' => $shiftDetail->rabu_pulang,
                'kamis_masuk' => $shiftDetail->kamis_masuk,
                'kamis_pulang' => $shiftDetail->kamis_pulang,
                'jumat_masuk' => $shiftDetail->jumat_masuk,
                'jumat_pulang' => $shiftDetail->jumat_pulang,
                'sabtu_masuk' => $shiftDetail->sabtu_masuk,
                'sabtu_pulang' => $shiftDetail->sabtu_pulang,
                'minggu_masuk' => $shiftDetail->minggu_masuk,
                'minggu_pulang' => $shiftDetail->minggu_pulang,
                'toleransi_terlambat' => $shiftDetail->toleransi_terlambat,
                'toleransi_pulang' => $shiftDetail->toleransi_pulang,
                'created_at' => $shiftDetail->created_at,
                'updated_at' => $shiftDetail->updated_at,
                'shift' => $shift ? [
                    'id' => $shift->id,
                    'nama' => $shift->nama,
                    'unit_id' => $shift->unit_id,
                    'created_at' => $shift->created_at,
                    'updated_at' => $shift->updated_at
                ] : null
            ] : null,
            'lokasi_presensi' => $lokasi_presensi,
        ];

        return response()->json($response);
    }


    public function checkDevice(Request $request)
    {
        $pegawai = $request->get('pegawai');

        $pegawai->load([
            'shift.details',
            'unit'
        ]);

        $deviceId = $request->unique_device_id;

        $userDevice = DB::table('user_device')
            ->where('pegawai_id', $pegawai->id)
            ->first();

        if (!$userDevice) {
            DB::table('user_device')->insert([
                'pegawai_id' => $pegawai->id,
                'unique_device_id' => $deviceId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device berhasil didaftarkan'
            ]);
        }

        if ($userDevice->unique_device_id == $deviceId) {
            return response()->json([
                'success' => true,
                'message' => 'Device sesuai'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Device berbeda'
        ], 403);
    }
}
