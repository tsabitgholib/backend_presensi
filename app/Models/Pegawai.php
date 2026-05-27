<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    use HasFactory;

    protected $table = 'pegawai';

    protected $fillable = [
        'nama',
        'no_ktp',
        'nip_unit',
        'unit_id',
        'shift_id',
        'profesi',
        'status',
        'status_lain'
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function shiftDetails()
    {
        return $this->hasManyThrough(
            ShiftDetail::class,
            Shift::class,
            'id',
            'shift_id',
            'shift_id',
            'id'
        );
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'events_pegawai', 'pegawai_id', 'events_id');
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'pegawai_id');
    }

    public function pengajuanCuti()
    {
        return $this->hasMany(PengajuanCuti::class, 'pegawai_id');
    }

    public function pengajuanIzin()
    {
        return $this->hasMany(PengajuanIzin::class, 'pegawai_id');
    }

    public function pengajuanSakit()
    {
        return $this->hasMany(PengajuanSakit::class, 'pegawai_id');
    }
}
