<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HariLibur extends Model
{
    use HasFactory;

    protected $table = 'hari_libur';

    protected $fillable = [
        'unit_detail_id',
        'tanggal',
        'keterangan',
        'admin_unit_id'
    ];

    public function unitDetail()
    {
        return $this->belongsTo(UnitDetail::class, 'unit_detail_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_unit_id');
    }
}
