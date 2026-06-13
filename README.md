# <p align="center"> LPPM TSU <br> (Lembaga Penelitian dan Pengabdian kepada Masyarakat) </p>

## 📢 Description

Sistem Informasi Manajemen data Penelitian, Publikasi, HKI (Hak Kekayaan Intelektual), dan Buku untuk STMIK Sinar Nusantara. Aplikasi ini dibangun dengan arsitektur modern menggunakan **Laravel 11 (Backend API)** dan **React (Frontend SPA)** yang di-*bundle* menggunakan Vite.

---

## 🚀 Fitur Utama
*   **Autentikasi Terpusat:** Menggunakan Laravel Sanctum (Cookie-based SPA Authentication) dengan dukungan Role-based Access Control (Superadmin & Admin).
*   **Manajemen Data LPPM:** Pengelolaan terintegrasi untuk Penelitian, Publikasi Ilmiah, HKI, dan Buku.
*   **Visualisasi Data:** Dashboard interaktif dengan grafik (Charts) dan statistik data penelitian berdasarkan Skema, Prodi, dan Tahun.
*   **Master Data:** Manajemen Penulis (Authors), Program Studi, dan Kategori.
*   **Impor & Ekspor Data:** Mendukung fitur unggah dan unduh laporan dalam format Excel/CSV.

---

## 💻 Tech Stack
*   **Backend:** Laravel 11.x, PHP 8.2+
*   **Frontend:** React 18, Vite
*   **Database:** MySQL / MariaDB
*   **Autentikasi:** Laravel Sanctum

---

## 🛠️ Panduan Instalasi (Local Development)

Ikuti langkah-langkah berikut untuk menjalankan proyek ini di *local machine* Anda (direkomendasikan menggunakan **Laragon**).

### 1. Clone Repositori
```bash
git clone https://github.com/pikdi-tsu/tsu-lppm.git tsu-lppm 
cd tsu-lppm
```

### 2. Instalasi Dependensi
Pastikan Anda sudah menginstal PHP, Composer, dan Node.js.
```bash
# Install PHP Dependencies
composer install

# Install Node.js Dependencies
npm install
```

### 3. Konfigurasi Environment (`.env`)
Salin file `.env.example` menjadi `.env`.
```bash
cp .env.example .env
```
Buka file `.env` dan atur konfigurasi database serta Sanctum Anda:
```env
APP_NAME="TSU LPPM"
APP_URL=http://tsu-lppm.test

# Konfigurasi Database (Sesuaikan dengan local Anda)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tsu_lppm
DB_USERNAME=root
DB_PASSWORD=

# Penting untuk React SPA (Sanctum Cookie)
SANCTUM_STATEFUL_DOMAINS=tsu-lppm.test
SESSION_DOMAIN=.tsu-lppm.test
```

### 4. Setup Database & Key
Generate *application key* dan jalankan migrasi beserta *seeder* awal:
```bash
php artisan key:generate
php artisan migrate --seed
```
*(Catatan: Seeder akan membuat akun Superadmin dan Admin standar beserta data dummy awal).*

### 5. Jalankan Aplikasi
Jika Anda menggunakan **Laragon**, Anda cukup membuka `http://lppm-sinus.test` di browser. Namun sebelumnya, pastikan untuk mem-*build* atau menjalankan *server development* untuk React:

```bash
# Menjalankan Vite Dev Server (Hot Module Replacement)
npm run dev

# ATAU, mem-build aset statis untuk Production
npm run build
```

Jika tidak menggunakan Laragon, jalankan PHP server bawaan:
```bash
php artisan serve
```

---

## 🌐 Deployment (CI/CD)

Repositori ini sudah dilengkapi dengan **GitHub Actions** untuk *Continuous Deployment (CD)* otomatis.

### 1. VPS Development (`branch: development`)
*   Script: `.github/workflows/deploy-dev.yml`
*   Target: Server VPS Linux (`/var/www/html/tsu_lppm/`)
*   Metode: Rsync over SSH

### 2. DirectAdmin Production (`branch: main`)
*   Script: `.github/workflows/deploy.yml`
*   Target: Shared Hosting DirectAdmin
*   Metode: FTP Sync & SSH Post-Deploy Script
*   **Struktur Keamanan Tingkat Tinggi:** 
    Aplikasi di-*deploy* menggunakan pemisahan folder *core* (sejajar dengan `public_html`).
    *   Folder *Core* mendarat di: `/domains/lppm.tsu.ac.id/lppm_core/`
    *   Folder Publik (CSS, JS, index.php) mendarat di: `/domains/lppm.tsu.ac.id/public_html/`
    *   File `index.php` akan dimodifikasi jalurnya secara otomatis saat deploy via perintah `sed`.
