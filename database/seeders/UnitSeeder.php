<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            [
                'nama_unit' => 'Yayasan Budi Warga',
                'alias' => 'YBW',
                'parent_id' => null,
                'level' => 1,
                'lokasi' => [
                    ['lat' => -6.1750, 'lng' => 106.8250],
                    ['lat' => -6.1760, 'lng' => 106.8260],
                    ['lat' => -6.1770, 'lng' => 106.8250],
                    ['lat' => -6.1760, 'lng' => 106.8240]
                ]
            ],
            [
                'nama_unit' => 'SD Negeri 1 Jakarta',
                'alias' => 'SDN 1 Jakarta',
                'parent_id' => 1,
                'level' => 2,
                'lokasi' => [
                    ['lat' => -6.2000, 'lng' => 106.8000],
                    ['lat' => -6.2010, 'lng' => 106.8010],
                    ['lat' => -6.2020, 'lng' => 106.8000],
                    ['lat' => -6.2010, 'lng' => 106.7990]
                ]
            ],
            [
                'nama_unit' => 'SMP Negeri 1 Jakarta',
                'alias' => 'SMPN 1 Jakarta',
                'parent_id' => 1,
                'level' => 2,
                'lokasi' => [
                    ['lat' => -6.2500, 'lng' => 106.8500],
                    ['lat' => -6.2510, 'lng' => 106.8510],
                    ['lat' => -6.2520, 'lng' => 106.8500],
                    ['lat' => -6.2510, 'lng' => 106.8490]
                ]
            ]
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(
                ['nama_unit' => $unit['nama_unit']],
                $unit
            );
        }
    }
}
