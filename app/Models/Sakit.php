<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sakit extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sdi_presensi.sakit';

    protected $fillable = ['jenis'];
}
