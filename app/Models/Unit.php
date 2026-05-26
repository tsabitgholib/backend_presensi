<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $table = 'unit';

    protected $fillable = [
        'nama_unit',
        'alias',
        'parent_id',
        'level',
        'lokasi',
        'lokasi2',
        'lokasi3'
    ];

    protected $casts = [
        'lokasi' => 'array',
        'lokasi2' => 'array',
        'lokasi3' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }

    public function admins()
    {
        return $this->hasMany(Admin::class, 'unit_id');
    }

    public function unitDetails()
    {
        return $this->hasMany(UnitDetail::class, 'ms_unit_id');
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class, 'unit_id');
    }

    public function laukPauk()
    {
        return $this->hasOne(LaukPaukUnit::class, 'unit_id');
    }
}
