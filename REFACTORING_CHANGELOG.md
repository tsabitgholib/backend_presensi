# Dokumentasi Perubahan — Refactor Service Layer

**Proyek:** `backend_presensi`  
**Tanggal:** 26 Mei 2026  
**Ringkasan:** Pemisahan logika bisnis dari Controller ke Service Layer, validasi dipindah ke Controller, serta perbaikan bug yang ditemukan saat audit.

---

## Daftar Isi

1. [Latar Belakang](#1-latar-belakang)
2. [Arsitektur Sebelum vs Sesudah](#2-arsitektur-sebelum-vs-sesudah)
3. [File Baru](#3-file-baru)
4. [Daftar Service Layer](#4-daftar-service-layer)
5. [Pola Controller Baru](#5-pola-controller-baru)
6. [Pemindahan Validasi ke Controller](#6-pemindahan-validasi-ke-controller)
7. [Otorisasi Super Admin](#7-otorisasi-super-admin)
8. [Integrasi Antar Service](#8-integrasi-antar-service)
9. [Perbaikan Bug](#9-perbaikan-bug)
10. [Yang Tidak Berubah](#10-yang-tidak-berubah)
11. [Struktur Folder](#11-struktur-folder)
12. [Cara Kerja Dependency Injection](#12-cara-kerja-dependency-injection)
13. [Langkah Pengembangan Selanjutnya](#13-langkah-pengembangan-selanjutnya)
14. [Catatan untuk Developer](#14-catatan-untuk-developer)

---

## 1. Latar Belakang

Sebelum refactor, seluruh logika bisnis (query database, validasi, perhitungan presensi, response JSON) berada di dalam **Controller**. Controller terbesar adalah `PresensiController` (~3.500 baris), yang menyulitkan pemeliharaan dan pengujian.

Tujuan refactor:

- Memisahkan **concern** HTTP dari logika bisnis
- Membuat Controller tipis (thin controller)
- Menempatkan logika bisnis di **Service Layer** (`app/Services/`)
- Menempatkan **validasi input** di Controller (sesuai konvensi Laravel)
- Mempertahankan perilaku API yang sama (route tidak diubah)

---

## 2. Arsitektur Sebelum vs Sesudah

### Sebelum

```
Request → Controller (validasi + logika + response JSON) → Model/DB
```

### Sesudah

```
Request → Controller (validasi + otorisasi HTTP)
        → Service (logika bisnis + query + response JSON)
        → Model/DB
```

| Layer | Tanggung Jawab |
|--------|----------------|
| **Controller** | Validasi `$request->validate()`, otorisasi role (jika perlu), delegasi ke service |
| **Service** | Logika bisnis, query, perhitungan, pembuatan response JSON |
| **Model** | Eloquent, relasi, accessor (tidak diubah) |
| **Helper** | `JWT`, `AdminUnitHelper` (tidak diubah) |
| **Middleware** | `AuthJWT` (diperbaiki import duplikat) |

---

## 3. File Baru

### 3.1 Service Layer (`app/Services/`)

Total **21 file service** baru:

| File Service | Controller Pasangan |
|--------------|---------------------|
| `AdminService.php` | `AdminController` |
| `AuthAdminService.php` | `AuthAdminController` |
| `AuthPegawaiService.php` | `AuthPegawaiController` |
| `CutiService.php` | `CutiController` |
| `DashboardService.php` | `DashboardController` |
| `DinasService.php` | `DinasController` |
| `EventService.php` | `EventController` |
| `HariLiburService.php` | `HariLiburController` |
| `IzinService.php` | `IzinController` |
| `LaukPaukUnitService.php` | `LaukPaukUnitController` |
| `PegawaiService.php` | `PegawaiController` |
| `PengajuanCutiService.php` | `PengajuanCutiController` |
| `PengajuanIzinService.php` | `PengajuanIzinController` |
| `PengajuanSakitService.php` | `PengajuanSakitController` |
| `PresensiService.php` | `PresensiController` |
| `PresensiEventService.php` | `PresensiEventController` |
| `SakitService.php` | `SakitController` |
| `ShiftService.php` | `ShiftController` |
| `TidakAbsenService.php` | `TidakAbsenController` |
| `UnitService.php` | `UnitController` |
| `UnitDetailService.php` | `UnitDetailController` |

### 3.2 Trait Otorisasi

| File | Deskripsi |
|------|-----------|
| `app/Http/Controllers/Concerns/AuthorizesSuperAdmin.php` | Trait untuk mengecek role `super_admin` pada modul master data |

Digunakan oleh: `IzinController`, `CutiController`, `SakitController`.

---

## 4. Daftar Service Layer

### Service terbesar

| Service | Perkiraan baris | Keterangan |
|---------|-----------------|------------|
| `PresensiService.php` | ~3.480 | Logika presensi: absen, rekap, lembur, integrasi pengajuan |
| `DashboardService.php` | ~724 | Agregat dashboard admin |
| `EventService.php` | ~526 | CRUD event & rekap presensi event |
| `PegawaiService.php` | ~476 | Data pegawai, lokasi presensi, hari libur |
| `DinasService.php` | ~439 | Jadwal & presensi dinas |

### Method publik `PresensiService` (24 method)

| Method | Deskripsi singkat |
|--------|-------------------|
| `store` | Absen masuk/pulang pegawai |
| `today` | Presensi hari ini |
| `history` | Riwayat presensi pegawai |
| `rekapPresensiByAdminUnit` | Rekap harian per unit (admin) |
| `rekapHistoryTahunanPegawai` | Rekap tahunan pegawai |
| `historyByAdminUnit` | History per unit |
| `rekapHistoryBulananPegawai` | Rekap bulanan pegawai login |
| `detailHistoryByAdminUnit` | Detail history per pegawai |
| `updatePresensiByAdminUnitBulk` | Koreksi presensi oleh admin |
| `rekapBulananUnitByAdmin` | Rekap bulanan unit |
| `rekapBulananByPegawai` | Rekap bulanan per pegawai |
| `hitungLemburMenit` | Hitung menit lembur |
| `rekapPresensiBulananByAdminUnit` | Rekap bulanan + lauk pauk |
| `integratePengajuanToPresensi` | Sinkron pengajuan disetujui ke presensi |
| `removePengajuanFromPresensi` | Batalkan integrasi pengajuan |
| `getLaporanKehadiranKaryawan` | Laporan kehadiran detail |
| `getOvertimePegawai` | Data lembur pegawai |
| `adminPresensiPegawai` | Presensi manual oleh admin |
| `getSummaryPresensiUnit` | Ringkasan untuk kepala unit |
| `historyByKepalaUnit` | History presensi kepala unit |
| `rekapPresensiHarianBulananByKepalaUnit` | Rekap harian/bulanan kepala unit |
| `historyAll` | History semua (integrasi eksternal) |
| `historyAllAdmin` | History semua untuk admin |

---

## 5. Pola Controller Baru

Setiap controller (kecuali base `Controller.php`) mengikuti pola:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\NamaService;

class NamaController extends Controller
{
    public function __construct(
        protected NamaService $namaService
    ) {}

    public function index(Request $request)
    {
        return $this->namaService->index($request);
    }
}
```

**Prinsip:**

- Controller **hanya** memanggil service
- Constructor injection — Laravel resolve otomatis, **tidak perlu** registrasi manual di `AppServiceProvider`
- Nama property service: `lcfirst` dari nama class (contoh: `PresensiService` → `$presensiService`)

### Contoh dengan validasi (`AdminController`)

```php
public function store(Request $request)
{
    $request->validate([
        'name' => 'required',
        'email' => 'required|email|unique:admin,email',
        'password' => 'required|min:6',
        'role' => 'required|in:super_admin,admin_unit',
        'unit_id' => 'nullable|exists:mysql_sdi.ms_unit,id',
        'status' => 'required|in:aktif,nonaktif',
    ]);

    return $this->adminService->store($request);
}
```

---

## 6. Pemindahan Validasi ke Controller

### Alasan

Di Laravel, validasi input HTTP (`$request->validate()`) secara konvensi berada di:

- **Controller**, atau
- **Form Request** (`app/Http/Requests/`) — opsi yang lebih rapi untuk ke depan

Service seharusnya fokus pada **logika bisnis**, bukan validasi format request.

### Cakupan pemindahan

Validasi dipindah dari service ke controller pada **17 service** berikut:

| Service | Method yang validasinya dipindah |
|---------|----------------------------------|
| `AdminService` | `store`, `storeMonitoring`, `update`, `updateMonitoring` |
| `AuthAdminService` | `login` |
| `AuthPegawaiService` | `login`, `checkDevice` |
| `CutiService` | `store`, `update` |
| `DinasService` | `store`, `update` |
| `EventService` | `store`, `update`, `addPegawaiToEvent`, `removePegawaiFromEvent`, `rekapPresensiEventPegawai` |
| `HariLiburService` | `store`, `storeMultiple`, `updateMultiple`, `deleteMultiple` |
| `IzinService` | `store`, `update` |
| `LaukPaukUnitService` | `store`, `update` |
| `PengajuanCutiService` | `store`, `approve` |
| `PengajuanIzinService` | `store`, `approve` |
| `PengajuanSakitService` | `store`, `approve` |
| `PresensiEventService` | `store` |
| `PresensiService` | `store`, `adminPresensiPegawai` |
| `SakitService` | `store`, `update` |
| `ShiftService` | `store`, `update`, `storeShiftDetail`, `updateShiftDetail`, `assignPegawaiToShiftDetail` |
| `UnitDetailService` | `updateLocation`, `assignPegawai` |

### Validasi dengan `AdminUnitHelper`

Beberapa controller membutuhkan rules dinamis per unit admin. Contoh di `ShiftController::store`:

```php
$unitValidationRules = AdminUnitHelper::getUnitIdValidationRules($request);

$request->validate(array_merge([
    'name' => 'required',
], $unitValidationRules));
```

Controller yang memakai `AdminUnitHelper` untuk validasi:

- `ShiftController`
- `DinasController`
- `HariLiburController`
- `LaukPaukUnitController`
- `EventController` (sebagian method)

### Status validasi di Service

- **Tidak ada** `$request->validate()` aktif di service
- Hanya tersisa validasi **yang di-comment** di `PegawaiService` (method `store`/`update`/`destroy` yang memang tidak dipakai di route)

---

## 7. Otorisasi Super Admin

### Sebelum

`authorizeSuperAdmin()` berupa **private method di dalam Service** (`IzinService`, `CutiService`, `SakitService`).

### Sesudah

- Logic otorisasi dipindah ke trait `AuthorizesSuperAdmin`
- Dipanggil di **Controller** sebelum memanggil service

```php
// app/Http/Controllers/Concerns/AuthorizesSuperAdmin.php
protected function authorizeSuperAdmin(Request $request): void
{
    $admin = $request->get('admin');
    if (!$admin || $admin->role !== 'super_admin') {
        abort(403, 'Hanya super admin yang boleh mengakses.');
    }
}
```

```php
// Contoh: IzinController
public function store(Request $request)
{
    $this->authorizeSuperAdmin($request);
    $request->validate(['jenis' => 'required|string']);

    return $this->izinService->store($request);
}
```

**Catatan:** Method `index()` pada Izin/Cuti/Sakit tetap **tanpa** otorisasi super admin (sama seperti sebelum refactor).

---

## 8. Integrasi Antar Service

### Pengajuan → Presensi

**Sebelum:** `PengajuanCutiService`, `PengajuanIzinService`, `PengajuanSakitService` memanggil:

```php
$presensiController = new \App\Http\Controllers\PresensiController();
$presensiController->integratePengajuanToPresensi(...);
```

**Sesudah:** Dependency injection ke `PresensiService`:

```php
class PengajuanCutiService
{
    public function __construct(
        protected PresensiService $presensiService
    ) {}

    // pada approve():
    $this->presensiService->integratePengajuanToPresensi(...);
    // atau:
    $this->presensiService->removePengajuanFromPresensi(...);
}
```

Ini menghindari instansiasi controller dari dalam service (anti-pattern).

---

## 9. Perbaikan Bug

Bug berikut ditemukan saat audit refactor dan telah diperbaiki:

### 9.1 Route `getPresensiByEventId` tidak pernah diimplementasi

| Item | Detail |
|------|--------|
| **Route** | `GET api/presensi-events/rekap/{id}` |
| **Masalah** | Route ada sejak commit pertama, tetapi method controller tidak pernah dibuat |
| **Perbaikan** | Ditambahkan `getPresensiByEventId()` di `PresensiEventService` dan `PresensiEventController` |
| **Fungsi** | Rekap presensi event untuk admin (filter per unit admin) |

### 9.2 Bug variabel `$unitId` di `ShiftService::index`

| Item | Detail |
|------|--------|
| **Masalah** | `$unitId` dipakai di closure `map()` tanpa `use ($unitId)` dan tidak diinisialisasi saat bukan `admin_unit` |
| **Perbaikan** | `$unitId = null` di awal method + `use ($unitId)` pada closure |

### 9.3 Import duplikat di `AuthJWT` middleware

| Item | Detail |
|------|--------|
| **File** | `app/Http/Middleware/AuthJWT.php` |
| **Masalah** | `use App\Models\Pegawai;` terduplikasi → fatal error saat boot |
| **Perbaikan** | Salah satu import dihapus |

### 9.4 `PresensiController` kehilangan method setelah generate otomatis

| Item | Detail |
|------|--------|
| **Masalah** | Script generate controller tipis hanya menangkap sebagian method `PresensiController` |
| **Perbaikan** | Controller diregenerasi ulang dengan semua **23 method** publik |

### 9.5 Import tidak terpakai di `routes/api.php`

| Item | Detail |
|------|--------|
| **Masalah** | `use App\Http\Controllers\PresensiLokasiController` — file controller tidak ada |
| **Perbaikan** | Import dihapus |

---

## 10. Yang Tidak Berubah

| Aspek | Keterangan |
|--------|------------|
| **Route API** | Semua endpoint di `routes/api.php` tetap sama (kecuali perbaikan method yang sudah broken) |
| **Middleware** | `auth.jwt` tetap dipakai |
| **Model** | Tidak ada perubahan struktur model |
| **Migration** | Tidak ada migration database baru |
| **Helper** | `JWT.php`, `AdminUnitHelper.php` tidak diubah |
| **Perilaku API** | Response JSON dan logika bisnis dipertahankan |
| **Route Pegawai CRUD** | `store`/`update`/`destroy` pegawai tetap di-comment di `api.php` |

---

## 11. Struktur Folder

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Concerns/
│   │   │   └── AuthorizesSuperAdmin.php    ← BARU
│   │   ├── AdminController.php             ← diubah (tipis + validasi)
│   │   ├── PresensiController.php          ← diubah (tipis + validasi)
│   │   └── ... (21 controller domain)
│   └── Middleware/
│       └── AuthJWT.php                     ← diperbaiki import
├── Services/                               ← BARU (folder)
│   ├── AdminService.php
│   ├── PresensiService.php
│   └── ... (21 service)
├── Models/                                 ← tidak diubah
└── Helpers/                                ← tidak diubah
```

---

## 12. Cara Kerja Dependency Injection

Laravel otomatis me-resolve dependency lewat constructor:

```php
// Controller
public function __construct(protected PresensiService $presensiService) {}

// Service (contoh PengajuanCutiService)
public function __construct(protected PresensiService $presensiService) {}
```

**Tidak perlu** menambahkan binding di `AppServiceProvider` karena semua service adalah concrete class.

### Alur request contoh: Approve pengajuan cuti

```
1. POST /api/pengajuan-cuti/approve/{id}
2. Middleware AuthJWT → attach $request->admin
3. PengajuanCutiController::approve()
   └── $request->validate([...])
4. PengajuanCutiService::approve()
   └── update status pengajuan
   └── PresensiService::integratePengajuanToPresensi()
5. Response JSON
```

---

## 13. Langkah Pengembangan Selanjutnya

Rekomendasi opsional untuk memperdalam arsitektur:

### Prioritas tinggi

1. **Form Request Classes**  
   Pindahkan rules validasi dari controller ke `app/Http/Requests/` agar controller lebih bersih.

2. **Pecah `PresensiService`**  
   File ~3.500 baris bisa dipecah, misalnya:
   - `PresensiStoreService` — absen masuk/pulang
   - `PresensiRekapService` — semua method rekap
   - `PresensiIntegrationService` — integrasi pengajuan

### Prioritas menengah

3. **Service return data, Controller format response**  
   Saat ini service masih mengembalikan `response()->json()`. Idealnya service mengembalikan array/DTO dan controller yang membungkus response.

4. **Repository pattern**  
   Untuk query SQL kompleks (`mysql_sdi`, recursive CTE) di `PegawaiService` dan `PresensiService`.

5. **Unit / Feature tests**  
   Test service secara terisolasi tanpa HTTP layer.

---

## 14. Catatan untuk Developer

### Menambah endpoint baru

1. Tambahkan method di **Service** (logika bisnis)
2. Tambahkan method di **Controller** (validasi + delegasi)
3. Daftarkan route di `routes/api.php`

```php
// Controller
public function createSomething(CreateSomethingRequest $request)
{
    $request->validate([...]); // atau gunakan Form Request

    return $this->someService->createSomething($request);
}

// Service
public function createSomething(Request $request)
{
    // logika bisnis saja, tanpa validate()
    ...
    return response()->json($result);
}
```

### Jangan lakukan

- ❌ Memanggil `new SomeController()` dari dalam Service
- ❌ Menaruh `$request->validate()` di Service
- ❌ Menambahkan logika bisnis berat di Controller

### Verifikasi setelah pull

```bash
php artisan route:list
php -l app/Services/PresensiService.php
```

---

## Ringkasan Perubahan per Kategori

| Kategori | Jumlah / Detail |
|----------|-----------------|
| Service baru | 21 file |
| Controller diubah | 21 file (semua tipis) |
| Trait baru | 1 (`AuthorizesSuperAdmin`) |
| Bug diperbaiki | 5 |
| Validasi dipindah | 17 service → controller |
| Route diubah | 0 (hanya perbaikan method yang broken) |
| File middleware diperbaiki | 1 (`AuthJWT.php`) |
| Import route dibersihkan | 1 (`PresensiLokasiController`) |

---

*Dokumen ini dibuat untuk mendokumentasikan refactor service layer pada tanggal 26 Mei 2026. Untuk pertanyaan teknis, merujuk ke file service dan controller pasangannya di folder `app/`.*
