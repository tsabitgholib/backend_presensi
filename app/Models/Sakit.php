<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sakit extends Model
{
    use HasFactory;

    protected $table = 'sakit';

    protected $fillable = ['jenis'];

    public function pengajuan()
    {
        return $this->hasMany(PengajuanSakit::class, 'sakit_id');
    }
}
