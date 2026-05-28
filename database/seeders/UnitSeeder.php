<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $root = Unit::updateOrCreate(
            ['alias' => 'YBW'],
            [
                'nama_unit' => 'Yayasan Budi Warga',
                'parent_id' => null,
                'level' => 1,
                'lokasi' => [
                    [-6.1750, 106.8250],
                    [-6.1760, 106.8260],
                    [-6.1770, 106.8250],
                    [-6.1760, 106.8240],
                ],
            ]
        );

        Unit::updateOrCreate(
            ['alias' => 'SDN 1 Jakarta'],
            [
                'nama_unit' => 'SD Negeri 1 Jakarta',
                'parent_id' => $root->id,
                'level' => 2,
                'lokasi' => [
                    [-6.2000, 106.8000],
                    [-6.2010, 106.8010],
                    [-6.2020, 106.8000],
                    [-6.2010, 106.7990],
                ],
            ]
        );

        Unit::updateOrCreate(
            ['alias' => 'SMPN 1 Jakarta'],
            [
                'nama_unit' => 'SMP Negeri 1 Jakarta',
                'parent_id' => $root->id,
                'level' => 2,
                'lokasi' => [
                    [-6.2500, 106.8500],
                    [-6.2510, 106.8510],
                    [-6.2520, 106.8500],
                    [-6.2510, 106.8490],
                ],
            ]
        );
    }
}
