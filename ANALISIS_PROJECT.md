# Analisis Proyek: Backend Presensi

Proyek ini adalah sistem backend untuk manajemen presensi pegawai yang dibangun menggunakan kerangka kerja **Laravel 10** dengan PHP 8.1.

## Struktur Direktori Utama
- `app/Http/Controllers`: Berisi logika bisnis aplikasi, termasuk manajemen presensi, cuti, izin, dan data pegawai.
- `app/Models`: Representasi skema database menggunakan Eloquent ORM.
- `app/Helpers`: Helper class (JWT, AdminUnitHelper)
- `database/migrations`: Skema database untuk entitas seperti `presensi`, `pegawai`, `shift`, `unit`, `cuti`, `sakit`, `izin`, dan `events`.
- `routes/api.php`: Definisi endpoint API yang dilindungi oleh middleware `auth.jwt`.

## Alur Kerja Aplikasi (Flow)

### 1. Autentikasi
Aplikasi mendukung dua jenis user: Admin dan Pegawai.
- `AuthAdminController`: Menangani login dan profil admin.
- `AuthPegawaiController`: Menangani login dan profil pegawai, termasuk `check-device` untuk verifikasi perangkat.

### 2. Admin Roles & Manajemen Admin
Aplikasi memiliki 3 jenis role admin:
- **super_admin**: Dapat mengakses semua unit dan fitur
- **admin_unit**: Dibatasi hanya pada unit tertentu
- **monitoring**: Dapat memantau beberapa unit (dikonfigurasi via `AdminMonitoringUnit`)
- `AdminController`: Mengelola CRUD admin dan monitoring units

### 3. Manajemen Data
- **Unit & Pegawai**: Mengelola struktur organisasi (`Unit`, `UnitDetail`) dan data pegawai (`MsPegawai`, `MsOrang`). Mendukung kepala unit (kepala unit) dengan fitur khusus.
- **Shift**: Mengatur jadwal kerja (`Shift`, `ShiftDetail`) yang dikaitkan dengan unit dan pegawai.
- **Pengajuan**: Mengelola izin, cuti, dan sakit melalui alur pengajuan yang perlu disetujui (`approve`) oleh admin. Pegawai dapat melihat riwayat pengajuan mereka.

### 4. Presensi
`PresensiController` adalah komponen inti yang menangani:
- **Masuk**: Mencatat waktu masuk, validasi lokasi (Point-in-Polygon), pengecekan jadwal dinas, dan status masuk (tepat waktu/terlambat).
- **Pulang**: Mencatat waktu pulang dan menentukan status (`absen_pulang`, `pulang_awal`).
- **Status Akhir**: Menentukan status kehadiran final (hadir, dinas, cuti, sakit, terlambat, dll) berdasarkan data masuk dan keluar.
- **Overtime (Lembur)**: Mendukung pencatatan lembur pegawai.
- **Rekap**: Menyediakan fitur rekap harian, bulanan, dan tahunan untuk admin dan pegawai. Admin juga bisa melakukan presensi manual untuk pegawai.
- **Generate Absent**: `TidakAbsenController` otomatis membuat data presensi "tidak hadir" untuk pegawai yang tidak melakukan presensi pada hari kerja (termasuk pengecekan hari libur dan pengajuan yang disetujui).

### 5. Presensi Event & Dinas
- **Presensi Dinas**: Mendukung presensi untuk pegawai yang sedang dinas luar.
- **Presensi Event**: Mendukung presensi untuk acara tertentu dengan lokasi yang fleksibel (event bisa memiliki multiple lokasi: lokasi, lokasi2, lokasi3).

### 6. Dashboard
`DashboardController` menyediakan ringkasan komprehensif untuk admin:
- Ringkasan total pegawai dan presensi
- Data presensi harian untuk chart
- Ringkasan pengajuan cuti/izin/sakit
- Aktivitas terbaru
- Trend bulanan
- Performa unit (untuk super admin)

### 7. Fitur Tambahan
- **Hari Libur**: Mengelola hari libur unit untuk menghindari perhitungan presensi pada hari tersebut.
- **Lauk Pauk**: Menghitung tunjangan lauk pauk unit yang dapat dikenakan penalti.

## Teknologi & Arsitektur
- **Framework**: Laravel 10
- **Database**: MySQL dengan **multiple koneksi database** (menggunakan `mysql` dan `mysql_sdi` - database eksternal untuk data master pegawai)
- **Autentikasi**: JWT (Custom implementation di `app/Helpers/JWT.php`) dan Laravel Sanctum.
- **Helpers**: `AdminUnitHelper` untuk validasi akses unit
- **Dependensi**: Guzzle (HTTP client), Carbon (manipulasi waktu).
