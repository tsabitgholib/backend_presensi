<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pegawai;

class PegawaiSeeder extends Seeder
{
    public function run(): void
    {
        $pegawais = [
            [
                'nama' => 'Budi Santoso',
                'no_ktp' => '320101199001010001',
                'nip_unit' => 'PEG-001',
                'unit_id' => 1,
                'shift_id' => null,
                'profesi' => 'Guru',
                'status' => 'aktif'
            ],
            [
                'nama' => 'Siti Aminah',
                'no_ktp' => '320101199102020002',
                'nip_unit' => 'PEG-002',
                'unit_id' => 1,
                'shift_id' => null,
                'profesi' => 'Staff Tata Usaha',
                'status' => 'aktif'
            ],
            [
                'nama' => 'Rahmat Hidayat',
                'no_ktp' => '320101198803030003',
                'nip_unit' => 'PEG-003',
                'unit_id' => 1,
                'shift_id' => null,
                'profesi' => 'Kepala Sekolah',
                'status' => 'aktif'
            ],
            [
                'nama' => 'Dewi Lestari',
                'no_ktp' => '320101199204040004',
                'nip_unit' => 'PEG-004',
                'unit_id' => 2,
                'shift_id' => null,
                'profesi' => 'Guru',
                'status' => 'aktif'
            ],
            [
                'nama' => 'Agus Supriyanto',
                'no_ktp' => '320101198705050005',
                'nip_unit' => 'PEG-005',
                'unit_id' => 2,
                'shift_id' => null,
                'profesi' => 'Driver',
                'status' => 'aktif'
            ]
        ];

        foreach ($pegawais as $pegawai) {
            Pegawai::firstOrCreate(
                ['no_ktp' => $pegawai['no_ktp']],
                $pegawai
            );
        }
    }
}
