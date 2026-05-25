<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuti extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sdi_presensi.cuti';
    protected $fillable = ['jenis'];
}