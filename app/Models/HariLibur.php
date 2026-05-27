<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HariLibur extends Model
{
    use HasFactory;

    protected $table = 'hari_libur';

    protected $fillable = [
        'unit_id',
        'tanggal',
        'keterangan',
        'admin_unit_id'
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public static function isHariLibur($unitId, $tanggal)
    {
        return self::where('unit_id', $unitId)
            ->where('tanggal', $tanggal)
            ->exists();
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_unit_id');
    }
}
