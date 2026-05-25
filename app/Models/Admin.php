<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Unit;

class Admin extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'sdi_presensi.admin';
    protected $fillable = [
        'name', 'email', 'password', 'role', 'unit_id', 'status'
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function monitoringUnits()
    {
        return $this->belongsToMany(
            Unit::class,
            'admin_monitoring_units',
            'admin_id',
            'unit_id'
        );
    }
} 
