<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Unit Table
        Schema::create('unit', function (Blueprint $table) {
            $table->id();
            $table->string('nama_unit');
            $table->string('alias')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('level')->default(1);
            $table->longText('lokasi')->nullable();
            $table->longText('lokasi2')->nullable();
            $table->longText('lokasi3')->nullable();
            $table->timestamps();
        });

        // 2. Admin Table
        Schema::create('admin', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['super_admin', 'admin_unit', 'monitoring'])->nullable();
            $table->unsignedInteger('unit_id')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
            
            $table->index('unit_id', 'admin_unit_id_foreign');
        });

        // 3. Pegawai Table (Base entity)
        Schema::create('pegawai', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('no_ktp', 50)->nullable();
            $table->string('nip_unit', 50)->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->string('profesi')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->string('status_lain')->nullable();
            $table->timestamps();
            
            $table->foreign('shift_id')->references('id')->on('shift')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('unit')->onDelete('set null');
        });

        // 4. Cuti, Izin, Sakit Tables
        Schema::create('cuti', function (Blueprint $table) {
            $table->id();
            $table->string('jenis');
            $table->timestamps();
        });

        Schema::create('izin', function (Blueprint $table) {
            $table->id();
            $table->string('jenis');
            $table->timestamps();
        });

        Schema::create('sakit', function (Blueprint $table) {
            $table->id();
            $table->string('jenis');
            $table->timestamps();
        });

        // 5. Events Table
        Schema::create('events', function (Blueprint $table) {
            $table->increments('id'); // int(11)
            $table->integer('ms_unit_id');
            $table->string('nama_event');
            $table->text('deskripsi')->nullable();
            $table->string('tipe_event', 100);
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->time('waktu_mulai')->nullable();
            $table->time('waktu_selesai')->nullable();
            $table->string('nama_tempat', 150)->nullable();
            $table->longText('lokasi')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->time('waktu_masuk_mulai')->nullable();
            $table->time('waktu_masuk_selesai')->nullable();
            $table->time('waktu_pulang_mulai')->nullable();
            $table->time('waktu_pulang_selesai')->nullable();
            $table->string('hari_mingguan', 20)->nullable();
            $table->longText('lokasi2')->nullable();
            $table->longText('lokasi3')->nullable();
            $table->timestamps();
        });

        // 6. Shift Table
        Schema::create('shift', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->unsignedBigInteger('unit_id');
            $table->timestamps();
            $table->index('unit_id', 'shift_unit_id_foreign');
        });

        // 7. Events Pegawai Table
        Schema::create('events_pegawai', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pegawai_id');
            $table->unsignedInteger('events_id');
            $table->timestamps();
            $table->unique(['events_id', 'pegawai_id'], 'uniq_events_pegawai');
        });

        // 8. Lauk Pauk Unit Table
        Schema::create('lauk_pauk_unit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->bigInteger('nominal');
            $table->bigInteger('pot_izin_pribadi')->default(50000);
            $table->bigInteger('pot_tanpa_izin')->default(100000);
            $table->bigInteger('pot_sakit')->default(10000);
            $table->bigInteger('pot_pulang_awal_beralasan')->default(20000);
            $table->bigInteger('pot_pulang_awal_tanpa_beralasan')->default(30000);
            $table->bigInteger('pot_terlambat_0806_0900')->default(20000);
            $table->bigInteger('pot_terlambat_0901_1000')->default(30000);
            $table->bigInteger('pot_terlambat_setelah_1000')->default(40000);
            $table->bigInteger('nom_lembur_permenit')->nullable();
            $table->bigInteger('nom_lembur_permenit_weekend')->nullable();
            $table->bigInteger('pot_tidak_absen_masuk')->nullable();
            $table->bigInteger('pot_tidak_absen_pulang')->nullable();
            $table->timestamps();
            $table->index('unit_id', 'lauk_pauk_unit_unit_id_foreign');
        });

        // 9. Personal Access Tokens
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
        });

        // 10. Presensi Event Table
        Schema::create('presensi_event', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_ktp', 50);
            $table->unsignedBigInteger('events_id');
            $table->string('status_presensi', 20)->nullable();
            $table->time('waktu_masuk');
            $table->longText('lokasi_masuk')->nullable();
            $table->string('status_masuk', 100)->nullable();
            $table->string('status_pulang', 100)->nullable();
            $table->time('waktu_pulang')->nullable();
            $table->string('lokasi_pulang', 100)->nullable();
            $table->timestamps();
        });

        // 11. Presensi Jadwal Dinas Table
        Schema::create('presensi_jadwal_dinas', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('keterangan');
            $table->longText('pegawai_ids');
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('created_by');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        // 12. User Device Table
        Schema::create('user_device', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pegawai_id');
            $table->string('unique_device_id', 255)->nullable()->unique();
            $table->timestamps();
        });

        // 13. Hari Libur Table
        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->date('tanggal');
            $table->string('keterangan');
            $table->unsignedBigInteger('admin_unit_id');
            $table->timestamps();
            $table->unique(['unit_id', 'tanggal'], 'hari_libur_unit_id_tanggal_unique');
            $table->foreign('admin_unit_id')->references('id')->on('admin')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('unit')->onDelete('cascade');
        });

        // 14. Pengajuan Cuti Table
        Schema::create('pengajuan_cuti', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pegawai_id');
            $table->unsignedBigInteger('cuti_id');
            $table->unsignedBigInteger('admin_unit_id')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('dokumen', 255)->nullable();
            $table->enum('status', ['pending', 'diterima', 'ditolak'])->default('pending');
            $table->text('keterangan_admin')->nullable();
            $table->timestamps();
            $table->index(['pegawai_id', 'tanggal_mulai'], 'idx_cuti_pegawai_tanggal');
            $table->foreign('cuti_id')->references('id')->on('cuti')->onDelete('cascade');
            $table->foreign('admin_unit_id')->references('id')->on('admin')->onDelete('set null');
        });

        // 15. Pengajuan Izin Table
        Schema::create('pengajuan_izin', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pegawai_id');
            $table->unsignedBigInteger('izin_id');
            $table->unsignedBigInteger('admin_unit_id')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('dokumen', 255)->nullable();
            $table->enum('status', ['pending', 'diterima', 'ditolak'])->default('pending');
            $table->text('keterangan_admin')->nullable();
            $table->timestamps();
            $table->index(['pegawai_id', 'tanggal_mulai'], 'idx_izin_pegawai_tanggal');
            $table->foreign('izin_id')->references('id')->on('izin')->onDelete('cascade');
            $table->foreign('admin_unit_id')->references('id')->on('admin')->onDelete('set null');
        });

        // 16. Pengajuan Sakit Table
        Schema::create('pengajuan_sakit', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pegawai_id');
            $table->unsignedBigInteger('sakit_id');
            $table->unsignedBigInteger('admin_unit_id')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('dokumen', 255)->nullable();
            $table->enum('status', ['pending', 'diterima', 'ditolak'])->default('pending');
            $table->text('keterangan_admin')->nullable();
            $table->timestamps();
            $table->index(['pegawai_id', 'tanggal_mulai'], 'idx_sakit_pegawai_tanggal');
            $table->foreign('sakit_id')->references('id')->on('sakit')->onDelete('cascade');
            $table->foreign('admin_unit_id')->references('id')->on('admin')->onDelete('set null');
        });

        // 17. Shift Detail Table
        Schema::create('shift_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_id');
            $table->string('senin_masuk')->nullable();
            $table->string('senin_pulang')->nullable();
            $table->string('selasa_masuk')->nullable();
            $table->string('selasa_pulang')->nullable();
            $table->string('rabu_masuk')->nullable();
            $table->string('rabu_pulang')->nullable();
            $table->string('kamis_masuk')->nullable();
            $table->string('kamis_pulang')->nullable();
            $table->string('jumat_masuk')->nullable();
            $table->string('jumat_pulang')->nullable();
            $table->string('sabtu_masuk')->nullable();
            $table->string('sabtu_pulang')->nullable();
            $table->string('minggu_masuk')->nullable();
            $table->string('minggu_pulang')->nullable();
            $table->integer('toleransi_terlambat')->default(0);
            $table->integer('toleransi_pulang')->default(0);
            $table->timestamps();
            $table->foreign('shift_id')->references('id')->on('shift')->onDelete('cascade');
        });

        // 18. Presensi Table
        Schema::create('presensi', function (Blueprint $table) {
            $table->id();
            $table->string('no_ktp', 50)->nullable();
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('shift_detail_id');
            $table->string('status_presensi')->nullable();
            $table->timestamp('waktu_masuk')->nullable();
            $table->timestamp('waktu_pulang')->nullable();
            $table->string('status_masuk', 100)->nullable();
            $table->string('status_pulang', 100)->nullable();
            $table->longText('lokasi_masuk')->nullable();
            $table->longText('lokasi_pulang')->nullable();
            $table->string('keterangan_masuk')->nullable();
            $table->string('keterangan_pulang')->nullable();
            $table->tinyInteger('overtime')->nullable();
            $table->timestamps();
            $table->index(['no_ktp', 'waktu_masuk'], 'idx_presensi_ktp_waktu');
            $table->index('waktu_masuk', 'idx_presensi_waktu');
            $table->foreign('shift_id')->references('id')->on('shift')->onDelete('cascade');
            $table->foreign('shift_detail_id')->references('id')->on('shift_detail')->onDelete('cascade');
        });

        // 19. Admin Monitoring Units Table
        Schema::create('admin_monitoring_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('unit_id');
            $table->timestamps();
            $table->unique(['admin_id', 'unit_id'], 'admin_monitoring_units_admin_id_unit_id_unique');
            $table->foreign('admin_id')->references('id')->on('admin')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('unit')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Drop dengan urutan terbalik dari penciptaan
        Schema::dropIfExists('admin_monitoring_units');
        Schema::dropIfExists('presensi');
        Schema::dropIfExists('shift_detail');
        Schema::dropIfExists('pengajuan_sakit');
        Schema::dropIfExists('pengajuan_izin');
        Schema::dropIfExists('pengajuan_cuti');
        Schema::dropIfExists('hari_libur');
        Schema::dropIfExists('user_device');
        Schema::dropIfExists('presensi_jadwal_dinas');
        Schema::dropIfExists('presensi_event');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('lauk_pauk_unit');
        Schema::dropIfExists('events_pegawai');
        Schema::dropIfExists('shift');
        Schema::dropIfExists('events');
        Schema::dropIfExists('sakit');
        Schema::dropIfExists('izin');
        Schema::dropIfExists('cuti');
        Schema::dropIfExists('pegawai');
        Schema::dropIfExists('admin');
        Schema::dropIfExists('unit');
    }
};
