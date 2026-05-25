<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminMonitoringUnit extends Model
{
    protected $connection = 'mysql';
    protected $table = 'sdi_presensi.admin_monitoring_units';
    protected $fillable = [
        'admin_id',
        'unit_id',
    ];
}

