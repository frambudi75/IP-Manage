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

## Troubleshooting

### Error: `php_network_getaddresses: getaddrinfo for db failed`
Pesan ini menunjukkan bahwa aplikasi PHP tidak dapat menemukan server dengan nama `db`. Hal ini biasanya terjadi karena beberapa alasan:

**1. Anda menjalankan aplikasi menggunakan XAMPP (Bukan Docker)**
Jika Anda membuka aplikasi melalui `http://localhost/ipmanage` (URL XAMPP), maka PHP yang digunakan adalah PHP milik XAMPP di Windows, bukan PHP di dalam Docker. PHP XAMPP tidak mengenali hostname `db`.
*   **Solusi:** Gunakan URL `http://localhost:8080` untuk mengakses aplikasi yang berjalan di dalam Docker.
*   **Alternatif (jika ingin tetap pakai XAMPP):** Ubah `DB_HOST` di file `includes/config.php` dari `db` menjadi `127.0.0.1`.

**2. Kontainer database belum siap atau gagal dijalankan**
Meskipun kontainer `ipmanager_app` sudah berjalan, servis database di dalam kontainer `ipmanager_db` mungkin masih dalam proses inisialisasi.
*   **Solusi:** File `docker-compose.yml` terbaru sudah menyertakan `healthcheck` agar aplikasi PHP menunggu sampai database benar-benar aktif. Jika masih error, coba jalankan `docker compose restart`.

**3. Masalah cache DNS di sistem**
Terkadang Docker mengalami masalah resolusi nama internal.
*   **Solusi:** Hentikan dan jalankan ulang seluruh layanan:
   ```bash
   docker compose down
   docker compose up -d
   ```

### Error: `failed to connect to the docker API at unix:///var/run/docker.sock`
Jika Anda melihat pesan error di atas, itu berarti layanan Docker (Docker Daemon) belum berjalan di sistem Anda.

**Solusi (Linux/WSL):**
1. Jalankan layanan Docker:
   ```bash
   sudo systemctl start docker
   ```
2. Pastikan Docker berjalan saat boot:
   ```bash
   sudo systemctl enable docker
   ```
3. Jika Anda menggunakan WSL, pastikan Docker Desktop sudah berjalan di Windows dan opsi "Use the WSL 2 based engine" serta integrasi dengan distro Anda sudah aktif di pengaturan Docker Desktop.

