# Changelog — 27 Mei 2026

**Proyek:** `backend_presensi`  
**Tanggal:** 27 Mei 2026

---

## Ringkasan Perubahan

1. **Perubahan Struktur Database**
   - Menambah kolom `profesi` di tabel `pegawai`
   - Mengganti `presensi_shift_detail_id` menjadi `shift_id` di tabel `pegawai`
   - Menghapus kolom `presensi_ms_unit_detail_id` di tabel `pegawai`
   - Menghapus kolom `id_orang` dan `id_user` di tabel `pegawai`
   - Menghapus `unit_detail_id` dan mengganti dengan `unit_id` di tabel `hari_libur`
   - **Menghapus tabel `presensi_ms_unit_detail` dan `UnitDetail`** (tidak digunakan lagi)
   - **Menghapus referensi database `sdi` dan multi koneksi** (semua menggunakan database utama saja)
   - **Mengganti kolom `name` menjadi `nama` di tabel `shift`

2. **Perubahan Model**
   - Update relasi di `Pegawai` model (menghapus unitDetail, unitDetailPresensi, dan orang)
   - Update `HariLibur` model untuk menggunakan `unit_id`
   - Update `Shift` model untuk mengganti `name` menjadi `nama`
   - **Menghapus model `UnitDetail.php`**
   - Menghapus relasi `unitDetails()` di `Unit` model

3. **Perubahan Service**
   - Total rewrite `AuthPegawaiService` untuk struktur baru (tidak menggunakan MsOrang dan database sdi)
   - Update `PegawaiService` untuk menggunakan struktur baru (hanya unit, tidak ada unit detail)
   - Update `PresensiService` untuk menggunakan shift dan unit saja
   - Rewrite `HariLiburService` untuk menggunakan `unit_id` dan Eloquent
   - Update `ShiftService` untuk mengubah method menjadi `assignPegawaiToShift` (tidak shift detail)
   - **Menghapus `UnitDetailService.php`**
   - **Total rewrite `UnitService` untuk menghilangkan referensi UnitDetail dan menambah CRUD
   - **Menambah CRUD lengkap di `PegawaiService`** (store, show, update, destroy)
   - **Menambah CRUD lengkap di `UnitService`** (store, update, destroy)

4. **Perubahan Middleware**
   - Update `AuthJWT` untuk menggunakan model Pegawai langsung, bukan MsOrang

5. **Perubahan Controller**
   - Update `HariLiburController` untuk validasi yang sesuai dengan struktur baru
   - **Menghapus `UnitDetailController.php`**
   - Update `AdminController`, `EventController`, dan `DinasController` untuk menghapus referensi `mysql_sdi` dan `ms_*`
   - Update `ShiftController` untuk mengubah method menjadi `assignPegawaiToShift` dan validasi menjadi `nama` bukan `name`
   - **Menambah CRUD di `UnitController`** (store, update, destroy)
   - **Menambah CRUD di `PegawaiController`** (store, show, update, destroy)

6. **Perubahan Helpers**
   - Update `AdminUnitHelper` untuk menghapus referensi UnitDetail dan `mysql_sdi`
   - Menghapus method `getUnitDetailIds` dan `validateUnitDetailAccess`

7. **Perubahan Routes**
   - Menghapus semua route yang terkait dengan `UnitDetail` di `routes/api.php`
   - Mengubah route `shift-detail/add-pegawai-to-shift-detail` menjadi `shift-detail/add-pegawai-to-shift`

8. **Perubahan Seeder**
   - Membuat `AdminSeeder` - Seeder admin dengan role super_admin, admin_unit, dan monitoring
   - Membuat `UnitSeeder` - Seeder unit dengan 3 contoh unit
   - Membuat `PegawaiSeeder` - Seeder pegawai dengan 5 contoh pegawai
   - Menghapus `ShiftSeeder` dan mengupdate `DatabaseSeeder`

---

## Detail Perubahan

### 1. Migration File (`database/migrations/2026_05_25_153713_create_full_database_schema.php`)

#### Tabel `pegawai`:
- Menambah kolom: `profesi` (string, nullable)
- Mengganti: `presensi_shift_detail_id` → `shift_id` (unsignedBigInteger, nullable)
- Menghapus: `presensi_ms_unit_detail_id`
- Menambah foreign key: `shift_id` → `shift.id` dengan `onDelete('set null')`
- Menambah foreign key: `unit_id` → `unit.id` dengan `onDelete('set null')`

#### Tabel `shift`:
- Mengganti: `name` → `nama` (string)

#### Tabel `hari_libur`:
- Menghapus: `unit_detail_id`
- Menambah: `unit_id` (unsignedBigInteger)
- Mengubah constraint unique: `unit_id, tanggal`
- Mengubah foreign key: `unit_id` → `unit.id` dengan `onDelete('cascade')`

---

### 2. Model `Pegawai` (`app/Models/Pegawai.php`)

- Menambah `profesi` ke `$fillable`
- Menghapus `presensi_ms_unit_detail_id` dari `$fillable`
- Menghapus relasi `shiftDetail()`
- Menghapus relasi `unitDetail()` dan `unitDetailPresensi()`
- Menambah relasi `shift()` → belongsTo Shift
- Menambah relasi `shiftDetails()` → hasManyThrough ShiftDetail via Shift

---

### 3. Model `Shift` (`app/Models/Shift.php`)

- Mengganti `name` → `nama` di `$fillable`

---

### 4. Model `HariLibur` (`app/Models/HariLibur.php`)

- Mengganti `unit_detail_id` dengan `unit_id` di `$fillable`
- Update method `isHariLibur()` untuk menerima dan menggunakan `$unitId`
- Menghapus relasi `unitDetail()`
- Menambah relasi `unit()` → belongsTo Unit
- Menambah cast `tanggal` → `date`

---

### 5. `AuthPegawaiService` (`app/Services/AuthPegawaiService.php`)

- **Total rewrite:**
  - Method `login()`:
    - Cek atau buat pegawai di tabel pegawai lokal
    - Payload JWT menggunakan `$pegawai->id`
  - Method `me()`:
    - Loading relasi `['shift.details', 'unit']`
    - Menggunakan `$pegawai->unit` untuk lokasi presensi
    - Menampilkan `unit_id` di lokasi_presensi
    - Update `shift.name` menjadi `shift.nama`
    - Kembalikan response sesuai struktur baru
  - Method `checkDevice()`:
    - Menggunakan `$pegawai->id` untuk user_device

---

### 6. `PegawaiService` (`app/Services/PegawaiService.php`)

#### Method `index()` dan `getByUnitIdPresensi()`:
- Memperbaiki referensi `shift.name` menjadi `shift.nama` di filter

#### Method `getLokasiPresensi()`:
- Update loading relasi: `['shift.details', 'unit']`
- Mendapatkan `$shift` dari `$pegawai->shift`
- Mendapatkan `$shiftDetail` dari `$shift?->details->first()`
- Mendapatkan lokasi dari `$pegawai->unit`
- Menampilkan `unit_id` di lokasi_presensi
- Update `shift_info` untuk menggunakan `shift.nama`

#### Method `cekHariLibur()`:
- Update response `unit.name` menjadi `unit.nama_unit`

#### Method `getByKepalaUnit()`:
- Memperbaiki referensi `shift.name` menjadi `shift.nama` di filter

#### **Menambah CRUD:**
- `store(Request $request)`: Membuat pegawai baru dengan validasi lengkap
- `show($id)`: Menampilkan detail pegawai beserta relasi
- `update(Request $request, $id)`: Mengupdate pegawai dengan validasi unique no_ktp
- `destroy($id)`: Menghapus pegawai

---

### 7. `PresensiService` (`app/Services/PresensiService.php`)

#### Method `store()`:
- Update loading relasi: `['shift.details', 'unit']`
- Mendapatkan `$shift` dari `$pegawai->shift`
- Mendapatkan `$shiftDetail` dari `$shift->details->first()`
- Cek hari libur menggunakan `$pegawai->unit_id`
- Validasi lokasi menggunakan `$unit->lokasi`
- Update `shift.name` menjadi `shift.nama`

#### Method `handlePresensiMasuk()`:
- Kembalikan pengecekan `$pegawai->profesi == 'driver'`
- Kembalikan logic untuk driver yang otomatis absen pulang

---

### 8. `HariLiburService` (`app/Services/HariLiburService.php`)

- Rewrite semua method untuk menggunakan `unit_id` bukan `unit_detail_id`
- Menghapus ketergantungan pada `UnitDetail` model
- Menggunakan Eloquent alih-alih raw SQL untuk query
- Semua method menerima dan memproses `unit_id`

---

### 9. `UnitService` (`app/Services/UnitService.php`)

- **Total rewrite untuk menghilangkan referensi UnitDetail dan database sdi
- Menghilangkan referensi `sdi.ms_unit` dan mengganti dengan model Unit
- Menghilangkan referensi UnitDetail dan unitDetails
- Memperbaiki referensi `nama` menjadi `nama_unit`
- **Menambah CRUD lengkap:**
  - `store(Request $request)`: Membuat unit baru dengan validasi
  - `update(Request $request, $id)`: Mengupdate unit
  - `destroy($id)`: Menghapus unit

---

### 10. `ShiftService` (`app/Services/ShiftService.php`)

- Update semua referensi `name` menjadi `nama`
- Update validasi dan response untuk menggunakan `nama`

---

### 11. `ShiftController` (`app/Http/Controllers/ShiftController.php`)

- Update validasi dari `name` menjadi `nama`

---

### 12. `UnitController` (`app/Http/Controllers/UnitController.php`)

- Menambah method `store(Request $request)`
- Menambah method `update(Request $request, $id)`
- Menambah method `destroy($id)`

---

### 13. `PegawaiController` (`app/Http/Controllers/PegawaiController.php`)

- Menambah method `store(Request $request)`
- Menambah method `show($id)`
- Menambah method `update(Request $request, $id)`
- Menambah method `destroy($id)`

---

### 14. Seeder

#### `AdminSeeder` (`database/seeders/AdminSeeder.php`)

- Membuat 3 admin dengan role berbeda:
  - superadmin@example.com / password123
  - adminunit1@example.com / password123
  - monitoring@example.com / password123

#### `UnitSeeder` (`database/seeders/UnitSeeder.php`)

- Membuat 3 contoh unit:
  - Yayasan Budi Warga (Level 1)
  - SD Negeri 1 Jakarta (Level 2)
  - SMP Negeri 1 Jakarta (Level 2)

#### `PegawaiSeeder` (`database/seeders/PegawaiSeeder.php`)

- Membuat 5 contoh pegawai dengan profesi berbeda:
  - Guru, Staff Tata Usaha, Kepala Sekolah, dll

---

## Arsitektur Baru

### Relasi Utama:
```
Pegawai (belongsTo) → Unit
Pegawai (belongsTo) → Shift (hasMany) → ShiftDetail
```

- `Pegawai` memiliki satu `Unit` (melalui `unit_id`)
- `Pegawai` memiliki satu `Shift` (melalui `shift_id`)
- `Shift` memiliki banyak `ShiftDetail` (melalui `shift_id`)
- Untuk mendapatkan shift detail yang aktif, kita ambil `$shift->details->first()`

### Relasi Hari Libur:
```
HariLibur (belongsTo) → Unit
```

- `HariLibur` sekarang langsung terkait dengan `Unit`, bukan `UnitDetail`

### Lokasi Presensi:
Lokasi presensi sekarang diambil dari tabel `unit` (kolom `lokasi`, `lokasi2`, `lokasi3`), bukan dari `presensi_ms_unit_detail`.

---

## Yang Tidak Berubah

- Semua route API tetap sama
- Model `Shift` dan `ShiftDetail` tidak diubah (hanya kolom `name` diganti `nama`)
- Logic presensi utama tetap sama
- Helper `AdminUnitHelper` dan `JWT` tidak diubah
- Multi koneksi database dihapus, semua menggunakan database utama

---

## Catatan Penting

1. **Kolom `profesi`**: Digunakan untuk identifikasi peran khusus seperti `driver` dan `Kepala Sekolah`
2. **Kolom `nama` di Shift**: Semua referensi `name` diganti menjadi `nama`
3. **Shift Detail**: Karena satu shift bisa memiliki banyak shift detail, kita ambil shift detail pertama untuk saat ini
4. **Hari Libur**: Sekarang berlaku per unit, bukan per unit detail
5. **Lokasi Presensi**: Sekarang diambil dari tabel `unit` saja
6. **Database Satu**: Tidak ada lagi multi koneksi, semua menggunakan database utama

---

## Langkah Selanjutnya (Opsional)

1. Menentukan logic untuk memilih shift detail yang tepat (bukan hanya first())
2. Menambah validasi dan error handling untuk shift detail yang tidak ditemukan
3. Membuat Form Request Classes untuk validasi yang lebih rapi
4. Menambah unit tests untuk service yang baru diupdate
5. Menambah route API untuk CRUD Unit dan Pegawai di `routes/api.php`
