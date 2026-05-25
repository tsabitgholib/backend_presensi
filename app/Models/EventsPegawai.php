<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Eventspegawai extends Model
{
    protected $connection = 'mysql';

    protected $table = 'sdi_presensi.events_pegawai';

    protected $fillable = [
        'pegawai_id',
        'events_id',
        'created_at',
        'updated_at'
    ];

    public function pegawai()
    {
        return $this->belongsTo(MsPegawai::class, 'id');
    }
    public function event()
    {
        return $this->belongsTo(Event::class, 'id');
    }
}


