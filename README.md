# TURIMA FRAM — Aplikasi Penjadwalan Kerja

Rebuild penuh dari prototipe HTML sebelumnya, sekarang berupa aplikasi **full-stack**:

- **Backend:** Laravel 10 (REST API + Laravel Sanctum untuk autentikasi token)
- **Frontend:** Vue 3 (Composition API) + Vite + Pinia + Vue Router + Tailwind CSS
- **Database:** MySQL

> ⚠️ **Catatan jujur:** Kode ini ditulis manual mengikuti struktur & konvensi standar Laravel/Vue, **belum dijalankan/diuji langsung** di server sungguhan (lingkungan pembuatan kode ini tidak punya akses PHP/MySQL/Composer). Kemungkinan besar berjalan lancar jika prasyaratnya benar, tapi wajar bila ada penyesuaian kecil dibutuhkan saat instalasi pertama kali (versi paket, dsb). Ikuti langkah di bawah dengan teliti.

---

## 1. Prasyarat

- PHP >= 8.4 dengan ekstensi umum (pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath)
- Composer 2
- Node.js >= 18 & npm
- MySQL 8 (atau MariaDB 10.6+)

## 2. Instalasi

```bash
# 1. Masuk ke folder proyek
cd turima-fram

# 2. Install dependensi PHP
composer install

# 3. Salin file environment & generate application key
cp .env.example .env
php artisan key:generate

# 4. Buat database MySQL kosong, lalu sesuaikan .env:
#    DB_DATABASE=turima_fram
#    DB_USERNAME=root
#    DB_PASSWORD=(sesuai punya Anda)

# 5. Jalankan migrasi + seed data contoh (akun demo, shift, template tugas)
php artisan migrate --seed

# 6. Buat symlink storage (WAJIB, supaya foto absen/tugas bisa diakses browser)
php artisan storage:link

# 7. Install dependensi frontend
npm install
```

## 3. Menjalankan (mode development)

Butuh **2 terminal** berjalan bersamaan:

```bash
# Terminal 1 - server Laravel
php artisan serve

# Terminal 2 - Vite dev server (hot reload frontend)
npm run dev
```

Buka **http://localhost:8000** di browser.

## 4. Build untuk production

```bash
npm run build
php artisan config:cache
php artisan route:cache
```

Lalu arahkan web server (Nginx/Apache) ke folder `public/` seperti proyek Laravel pada umumnya.

---

## Akun Demo (dari seeder)

| Peran | Username | Password |
|---|---|---|
| Manajer | `manajer` | `admin123` |
| Admin (akses Jadwal & Tugas saja) | `admin` | `admin123` |
| Karyawan | `dewi`, `budi`, `rina`, `andi` | `12345` |

*Andi Pratama* sudah diset punya akses "Jadwal Tim & Tugas Tim" (`can_manage_schedule = true`) sebagai contoh Supervisor.

---

## Ringkasan Fitur yang Sudah Dibangun

- Login berbasis role (Manajer / Karyawan), token via Laravel Sanctum
- Role **Admin** terpisah: hanya bisa akses menu Jadwal & Tugas Harian
- Karyawan bisa diberi akses tambahan "Jadwal Tim & Tugas Tim" (seperti Supervisor)
- Jadwal mingguan: assign shift per hari, deteksi cuti otomatis, publish/unpublish, ekspor CSV (dengan status Hadir/Tidak Hadir/Cuti)
- Manajemen Karyawan & Template Shift (CRUD, reset sandi, toggle akses)
- Cuti & Tukar Shift: pengajuan, persetujuan rekan kerja → manajer
- Absensi: absen masuk/pulang **wajib unggah foto**, tersimpan di server (bukan di browser)
- Tugas Harian: daftar tugas permanen (checklist), penugasan massal, **karyawan bebas memilih urutan pengerjaan**, wajib foto bukti
- Laporan rekap jam kerja mingguan (terjadwal vs. aktual)
- Notifikasi aktivitas (lonceng)

## Yang Belum/Bisa Dikembangkan Lagi

- Halaman lupa password / reset password
- Paginasi untuk data yang sangat banyak (saat ini mengambil semua data sekaligus)
- Export PDF/Excel untuk daftar tugas (di prototipe HTML ada; di versi ini export CSV baru untuk jadwal & laporan)
- Real-time update (WebSocket/Pusher) — saat ini perlu refresh manual untuk melihat perubahan dari pengguna lain
- Dialog konfirmasi kustom "Ya/Tidak" (saat ini pakai dialog bawaan browser `confirm()` untuk aksi hapus — komponen `ConfirmModal.vue` sudah disediakan bila ingin diseragamkan)

## Struktur Folder Penting

```
app/Http/Controllers/Api/   → semua controller API
app/Models/                 → model Eloquent
database/migrations/        → struktur tabel database
database/seeders/           → data contoh awal
routes/api.php              → seluruh endpoint API + middleware role
resources/js/               → aplikasi Vue 3 (views, components, store, router)
resources/js/router/index.js→ pengaturan halaman & proteksi akses per role
```

## Keamanan yang Sudah Diperhatikan

- Setiap endpoint API divalidasi lewat middleware `auth:sanctum` + middleware `role:...` kustom
- Karyawan biasa **tidak bisa** melihat data absensi/tugas/foto karyawan lain lewat API (sudah difilter di server, bukan hanya disembunyikan di tampilan)
- Password di-hash dengan bcrypt (`Hash::make`)
- Upload foto divalidasi tipe file (`image`) & ukuran maksimum 5MB

---

Kalau ada error saat instalasi (versi paket tidak cocok, dsb.), itu wajar untuk kode yang ditulis manual tanpa lingkungan uji langsung — beri tahu pesan errornya, nanti dibantu perbaiki.
