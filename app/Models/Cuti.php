<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuti extends Model
{
    use HasFactory;

    protected $table = 'cuti';

    protected $fillable = ['jenis'];

    public function pengajuan()
    {
        return $this->hasMany(PengajuanCuti::class, 'cuti_id');
    }
}
