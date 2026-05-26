<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanCuti extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_cuti';

    protected $fillable = [
        'pegawai_id',
        'cuti_id',
        'admin_unit_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'alasan',
        'dokumen',
        'status',
        'keterangan_admin'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function cuti()
    {
        return $this->belongsTo(Cuti::class, 'cuti_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_unit_id');
    }
}
