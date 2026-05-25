<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresensiEvent extends Model
{
    protected $table = 'presensi_event';

    protected $fillable = [
        'no_ktp',
        'events_id',
        'status_presensi',
        'status_masuk',
        'status_pulang',
        'waktu_masuk',
        'waktu_pulang',
        'waktu_pulang',
        'lokasi_masuk',
        'lokasi_pulang',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'events_id');
    }

    public function orang()
    {
        return $this->belongsTo(MsOrang::class, 'no_ktp', 'no_ktp');
    }
}