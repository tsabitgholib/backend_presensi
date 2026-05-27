<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresensiEvent extends Model
{
    use HasFactory;

    protected $table = 'presensi_event';

    protected $fillable = [
        'no_ktp',
        'events_id',
        'status_presensi',
        'waktu_masuk',
        'lokasi_masuk',
        'status_masuk',
        'status_pulang',
        'waktu_pulang',
        'lokasi_pulang'
    ];

    protected $casts = [
        'lokasi_masuk' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'events_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'no_ktp', 'no_ktp');
    }
}
