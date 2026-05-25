<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $connection = 'mysql';

    protected $table = 'sdi_presensi.events';

    protected $fillable = [
        'ms_unit_id',
        'nama_event',
        'deskripsi',
        'tipe_event',
        'tanggal_mulai',
        'tanggal_selesai',
        'waktu_mulai',
        'waktu_selesai',
        'waktu_masuk_mulai',
        'waktu_masuk_selesai',
        'waktu_pulang_mulai',
        'waktu_pulang_selesai',
        // 'hari_mingguan',
        'nama_tempat',
        'lokasi',
        'lokasi2',
        'lokasi3',
        'is_active',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'ms_unit_id');
    }

    public function pegawai()
    {
        return $this->belongsToMany(
            MsPegawai::class,
            'event_pegawai',
            'event_id',
            'pegawai_id'
        )->withTimestamps();
    }

}


