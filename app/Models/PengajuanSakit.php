<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanSakit extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_sakit';

    protected $fillable = [
        'pegawai_id',
        'sakit_id',
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

    public function sakit()
    {
        return $this->belongsTo(Sakit::class, 'sakit_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_unit_id');
    }
}
