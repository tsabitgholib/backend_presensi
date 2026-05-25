<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MsPegawai extends Model
{
    use HasFactory;

    protected $connection = 'mysql_sdi';
    protected $table = 'sdi.ms_pegawai';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_orang',
        'id_user',
        'id_unit',
        'status',
        'nip_unit',
        'presensi_shift_detail_id',
        'presensi_ms_unit_detail_id'
    ];

    public function shiftDetail()
{
    return $this->belongsTo(\App\Models\ShiftDetail::class, 'presensi_shift_detail_id', 'id');
}


    public function orang()
    {
        return $this->belongsTo(MsOrang::class, 'id_orang', 'id');
    }


    public function unit()
    {
        return $this->belongsTo(Unit::class, 'id_unit', 'id');

    }
    public function unitDetailPresensi()
    {
        return $this->belongsTo(\App\Models\UnitDetail::class, 'presensi_ms_unit_detail_id', 'id');
    }

}