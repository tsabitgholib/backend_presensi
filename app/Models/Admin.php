<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'unit_id',
        'status'
    ];

    protected $hidden = [
        'password',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function monitoringUnits()
    {
        return $this->belongsToMany(Unit::class, 'admin_monitoring_units', 'admin_id', 'unit_id');
    }
}
