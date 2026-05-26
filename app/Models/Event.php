<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $table = 'events';

    protected $fillable = [
        'ms_unit_id',
        'nama_event',
        'deskripsi',
        'tipe_event',
        'tanggal_mulai',
        'tanggal_selesai',
        'waktu_mulai',
        'waktu_selesai',
        'nama_tempat',
        'lokasi',
        'is_active',
        'waktu_masuk_mulai',
        'waktu_masuk_selesai',
        'waktu_pulang_mulai',
        'waktu_pulang_selesai',
        'hari_mingguan',
        'lokasi2',
        'lokasi3'
    ];

    protected $casts = [
        'lokasi' => 'array',
        'lokasi2' => 'array',
        'lokasi3' => 'array',
        'is_active' => 'boolean',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'ms_unit_id');
    }

    public function pegawais()
    {
        return $this->belongsToMany(Pegawai::class, 'events_pegawai', 'events_id', 'pegawai_id');
    }

    public function presensi()
    {
        return $this->hasMany(PresensiEvent::class, 'events_id');
    }
}
