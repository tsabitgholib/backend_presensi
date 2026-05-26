<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    use HasFactory;

    protected $table = 'pegawai';

    protected $fillable = [
        'id_orang',
        'id_user',
        'nama',
        'no_ktp',
        'nip_unit',
        'unit_id',
        'presensi_shift_detail_id',
        'presensi_ms_unit_detail_id',
        'status'
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function orang()
    {
        return $this->belongsTo(MsOrang::class, 'id_orang');
    }

    public function shiftDetail()
    {
        return $this->belongsTo(ShiftDetail::class, 'presensi_shift_detail_id');
    }

    public function unitDetail()
    {
        return $this->belongsTo(UnitDetail::class, 'presensi_ms_unit_detail_id');
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
