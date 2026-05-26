<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanIzin extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_izin';

    protected $fillable = [
        'pegawai_id',
        'izin_id',
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

    public function izin()
    {
        return $this->belongsTo(Izin::class, 'izin_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_unit_id');
    }
}
