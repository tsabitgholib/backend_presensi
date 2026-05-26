<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Presensi extends Model
{
    use HasFactory;

    protected $table = 'presensi';

    protected $fillable = [
        'no_ktp',
        'shift_id',
        'shift_detail_id',
        'status_presensi',
        'waktu_masuk',
        'waktu_pulang',
        'status_masuk',
        'status_pulang',
        'lokasi_masuk',
        'lokasi_pulang',
        'keterangan_masuk',
        'keterangan_pulang',
        'overtime'
    ];

    protected $casts = [
        'lokasi_masuk' => 'array',
        'lokasi_pulang' => 'array',
        'waktu_masuk' => 'datetime',
        'waktu_pulang' => 'datetime',
        'overtime' => 'boolean'
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function shiftDetail()
    {
        return $this->belongsTo(ShiftDetail::class, 'shift_detail_id');
    }
}
