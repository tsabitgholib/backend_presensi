<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Izin extends Model
{
    use HasFactory;

    protected $table = 'izin';

    protected $fillable = ['jenis'];

    public function pengajuan()
    {
        return $this->hasMany(PengajuanIzin::class, 'izin_id');
    }
}
