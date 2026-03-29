# Panduan Instalasi Docker - IPManager Pro

Dokumentasi ini menjelaskan cara menginstal dan menjalankan **IPManager Pro** menggunakan Docker dan Docker Compose.

## Persyaratan Sistem

Pastikan Anda sudah menginstal perangkat lunak berikut di mesin Anda:
- [Docker Engine](https://docs.docker.com/get-docker/) (v20.10+)
- [Docker Compose](https://docs.docker.com/compose/install/) (v2.0+)

## Struktur Docker

Proyek ini menggunakan dua kontainer utama:
1. **app**: Menjalankan Apache dengan PHP 8.2 dan semua ekstensi yang diperlukan (mysqli, pdo, snmp, curl). Kontainer ini juga menjalankan proses background scanner secara otomatis.
2. **db**: Menjalankan MariaDB 10.11 untuk penyimpanan data.

## Langkah-langkah Instalasi

### 1. Persiapkan File Project
Pastikan Anda berada di direktori utama proyek di mana terdapat file `Dockerfile` dan `docker-compose.yml`.

### 2. Konfigurasi (Opsional)
Anda dapat mengubah konfigurasi database atau port di dalam file `docker-compose.yml`. Secara default:
- Port Aplikasi: `8080`
- Database Root Password: `rootpassword`
- Nama Database: `ipmanage`

### 3. Jalankan Docker Compose
Jalankan perintah berikut di terminal Anda:

```bash
docker-compose up -d
```

Perintah ini akan:
- Membangun image untuk aplikasi PHP.
- Menarik image MariaDB dari Docker Hub.
- Menjalankan kedua kontainer di latar belakang (-d).
- Mengimpor skema database dari `./sql/database.sql` secara otomatis saat pertama kali dijalankan.

### 4. Verifikasi Kontainer
Pastikan kedua kontainer berjalan dengan baik:

```bash
docker ps
```

Anda harus melihat kontainer `ipmanager_app` dan `ipmanager_db` dengan status "Up".

### 5. Akses Aplikasi
Buka browser Anda dan akses:
`http://localhost:8080`

Login default (jika sudah ada di database):
- **Username**: admin
- **Password**: password (atau sesuai isi `database.sql`)

## Informasi Tambahan

### Background Scanner
Aplikasi ini menjalankan `cron_scanner.php` dan `cron_switch_poll.php` secara otomatis setiap 5 menit di dalam kontainer `app`. Anda tidak perlu mengatur crontab manual di host.

### Melihat Log
Untuk melihat aktivitas scanner atau error aplikasi, Anda bisa memantau log kontainer:

```bash
docker logs -f ipmanager_app
```

### Menghentikan Aplikasi
Untuk menghentikan semua layanan:

```bash
docker-compose down
```

Untuk menghapus data database juga (Hati-hati: ini akan menghapus semua data!):

```bash
docker-compose down -v
```
