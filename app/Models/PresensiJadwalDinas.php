<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresensiJadwalDinas extends Model
{
    use HasFactory;

    protected $table = 'presensi_jadwal_dinas';

    protected $fillable = [
        'tanggal_mulai',
        'tanggal_selesai',
        'keterangan',
        'pegawai_ids',
        'unit_id',
        'created_by',
        'is_active'
    ];

    protected $casts = [
        'pegawai_ids' => 'array',
        'is_active' => 'boolean',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public static function getJadwalDinasForPegawai($pegawaiId, $tanggal)
    {
        return self::where('is_active', true)
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->whereRaw('JSON_CONTAINS(pegawai_ids, ?)', [$pegawaiId])
            ->first();
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
