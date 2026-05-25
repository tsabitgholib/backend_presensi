<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Izin extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sdi_presensi.izin';
    protected $fillable = ['jenis'];
}
