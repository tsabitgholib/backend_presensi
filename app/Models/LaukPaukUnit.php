<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaukPaukUnit extends Model
{
    protected $connection = 'mysql';
    protected $table = 'sdi_presensi.lauk_pauk_unit';
    protected $fillable = [
        'unit_id', 
        'nominal',
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

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}