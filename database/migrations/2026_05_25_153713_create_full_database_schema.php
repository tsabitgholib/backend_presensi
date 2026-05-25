<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Units Table (Tree Hierarchy)
        Schema::create('unit', function (Blueprint $table) {
            $table->id();
            $table->string('nama_unit');
            $table->string('alias')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('level')->default(1);
            $table->timestamps();
            $table->foreign('parent_id')->references('id')->on('unit')->onDelete('cascade');
        });

        // 2. Admin Table
        Schema::create('admin', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['super_admin', 'admin_unit', 'monitoring'])->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
            $table->foreign('unit_id')->references('id')->on('unit')->onDelete('set null');
        });

        // 3. Pegawai Table
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nik')->unique();
            $table->string('nip')->unique();
            $table->string('jabatan')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->unsignedBigInteger('unit_id');
            $table->date('tgl_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->timestamps();
            $table->foreign('unit_id')->references('id')->on('unit')->onDelete('cascade');
        });

        // 4. Shift & Details
        Schema::create('shift', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('unit_id');
            $table->timestamps();
            $table->foreign('unit_id')->references('id')->on('unit')->onDelete('cascade');
        });

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

        // 5. Presensi Related
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
            $table->boolean('overtime')->nullable();
            $table->timestamps();
            $table->foreign('shift_id')->references('id')->on('shift')->onDelete('cascade');
            $table->foreign('shift_detail_id')->references('id')->on('shift_detail')->onDelete('cascade');
        });

        // 6. Master tables (Cuti, Izin, Sakit)
        Schema::create('', function (Blueprint $table) {
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

        // 7. Pengajuan Tables
        Schema::create('pengajuan_cuti', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pegawai_id');
            $table->unsignedBigInteger('cuti_id');
            $table->unsignedBigInteger('admin_unit_id')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('dokumen')->nullable();
            $table->enum('status', ['pending', 'diterima', 'ditolak'])->default('pending');
            $table->timestamps();
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->onDelete('cascade');
            $table->foreign('cuti_id')->references('id')->on('cuti')->onDelete('cascade');
            $table->foreign('admin_unit_id')->references('id')->on('admin')->onDelete('set null');
        });

        Schema::create('pengajuan_izin', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pegawai_id');
            $table->unsignedBigInteger('izin_id');
            $table->unsignedBigInteger('admin_unit_id')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('dokumen')->nullable();
            $table->enum('status', ['pending', 'diterima', 'ditolak'])->default('pending');
            $table->timestamps();
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->onDelete('cascade');
            $table->foreign('izin_id')->references('id')->on('izin')->onDelete('cascade');
            $table->foreign('admin_unit_id')->references('id')->on('admin')->onDelete('set null');
        });

        Schema::create('pengajuan_sakit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pegawai_id');
            $table->unsignedBigInteger('sakit_id');
            $table->unsignedBigInteger('admin_unit_id')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('dokumen')->nullable();
            $table->enum('status', ['pending', 'diterima', 'ditolak'])->default('pending');
            $table->timestamps();
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->onDelete('cascade');
            $table->foreign('sakit_id')->references('id')->on('sakit')->onDelete('cascade');
            $table->foreign('admin_unit_id')->references('id')->on('admin')->onDelete('set null');
        });

        // 8. Other Tables
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ms_unit_id');
            $table->string('nama_event');
            $table->timestamps();
            $table->foreign('ms_unit_id')->references('id')->on('unit')->onDelete('cascade');
        });

        Schema::create('admin_monitoring_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('unit_id');
            $table->timestamps();
            $table->foreign('admin_id')->references('id')->on('admin')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('unit')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_monitoring_units');
        Schema::dropIfExists('events');
        Schema::dropIfExists('pengajuan_sakit');
        Schema::dropIfExists('pengajuan_izin');
        Schema::dropIfExists('pengajuan_cuti');
        Schema::dropIfExists('sakit');
        Schema::dropIfExists('izin');
        Schema::dropIfExists('cuti');
        Schema::dropIfExists('presensi');
        Schema::dropIfExists('shift_detail');
        Schema::dropIfExists('shift');
        Schema::dropIfExists('pegawai');
        Schema::dropIfExists('admin');
        Schema::dropIfExists('unit');
    }
};
