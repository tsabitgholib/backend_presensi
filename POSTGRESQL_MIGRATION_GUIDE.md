# Panduan Migrasi dari MySQL ke PostgreSQL

Dokumen ini berisi langkah-langkah dan perubahan yang diperlukan untuk memigrasikan database proyek **Backend Presensi** dari MySQL ke PostgreSQL.

## 1. Konfigurasi Lingkungan (`.env`)

Ubah konfigurasi database di file `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nama_db_postgres
DB_USERNAME=username_postgres
DB_PASSWORD=password_postgres
DB_SCHEMA=public
TENANT_SCHEMA_WHITELIST=client_a,client_b
TENANCY_FALLBACK_TO_DEFAULT=true
```

`tenant_schema` dibaca dari JWT saat request dan dipakai untuk mengatur `search_path`.

## 2. Perubahan pada Query Mentah (Raw Queries)

PostgreSQL memiliki sintaks yang sedikit berbeda untuk beberapa fungsi dibandingkan MySQL. Berikut adalah temuan dalam kode Anda dan perubahannya:

### A. Fungsi JSON (`JSON_CONTAINS`)
Pada file `app\Models\PresensiJadwalDinas.php`:
*   **MySQL:** `->whereRaw('JSON_CONTAINS(pegawai_ids, ?)', [$pegawaiId])`
*   **PostgreSQL:** `->whereRaw('pegawai_ids::jsonb @> ?', [json_encode([(string)$pegawaiId])])`
    *   *Catatan: Kolom `pegawai_ids` sebaiknya diubah tipenya menjadi `jsonb` di migrasi.*

### B. Ekstraksi Tanggal
Pada file `app\Services\DashboardService.php` dan `app\Services\EventService.php`:
*   **MySQL:** `DB::raw('DATE(waktu_masuk)')` atau `DB::raw('DATE(created_at)')`
*   **PostgreSQL:** `DB::raw('waktu_masuk::date')` atau `DB::raw('CAST(waktu_masuk AS DATE)')`

### C. Case Sensitivity
PostgreSQL bersifat **case-sensitive** untuk operator `LIKE`. Jika Anda ingin pencarian yang tidak peka huruf besar/kecil (case-insensitive):
*   **MySQL:** `->where('nama', 'LIKE', '%keyword%')` (Biasanya case-insensitive di MySQL)
*   **PostgreSQL:** `->where('nama', 'ILIKE', '%keyword%')` (Gunakan `ILIKE`)

### D. Pengurutan NULL
PostgreSQL menempatkan `NULL` di akhir secara default pada `ASC` dan di awal pada `DESC`.
*   Jika ingin mengatur secara spesifik: `ORDER BY kolom ASC NULLS LAST`

### E. Aturan GROUP BY yang Ketat
PostgreSQL mengharuskan semua kolom yang ada di `SELECT` (yang bukan merupakan fungsi agregat seperti `SUM`, `COUNT`) untuk dicantumkan di dalam `GROUP BY`.
*   **MySQL:** Terkadang mengizinkan `SELECT id, nama, COUNT(*) GROUP BY id`
*   **PostgreSQL:** Harus `SELECT id, nama, COUNT(*) GROUP BY id, nama`

## 3. Penyesuaian Migrasi (`database/migrations`)

Secara umum, Laravel Schema Builder menangani perbedaan tipe data, namun ada beberapa hal yang perlu diperhatikan:

### A. Kolom JSON
Ubah `longText('pegawai_ids')` menjadi `json('pegawai_ids')` atau `jsonb('pegawai_ids')` (direkomendasikan `jsonb` untuk performa pencarian).

**Contoh di `presensi_jadwal_dinas`:**
```php
// Sebelum
$table->longText('pegawai_ids');

// Sesudah
$table->jsonb('pegawai_ids');
```

### B. Tipe Boolean
MySQL menggunakan `tinyInteger(1)` untuk boolean. PostgreSQL memiliki tipe `boolean` murni. Laravel secara otomatis memetakan `$table->boolean()` ke tipe yang tepat, tetapi jika menggunakan `tinyInteger` secara manual, pertimbangkan untuk beralih ke `boolean`.

## 4. Instalasi Ekstensi PHP
Pastikan ekstensi PHP `pdo_pgsql` dan `pgsql` sudah terinstal dan aktif di server Anda.

## 5. Ringkasan Perintah Migrasi
Setelah melakukan perubahan di atas, jalankan ulang migrasi:

```bash
php artisan migrate:fresh --seed
```

## 6. Provisioning Schema Tenant Baru

Untuk onboarding client baru dalam database yang sama:

```bash
php scripts/provision-tenant-schema.php client_a --seed
```

Script akan:
- Membuat schema jika belum ada
- Set `search_path` ke `<tenant_schema>,public`
- Menjalankan migration (dan seed jika `--seed`)

---
*Dibuat oleh Gemini CLI pada 28 Mei 2026*
