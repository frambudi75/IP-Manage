# IPManager Pro: Development & Update History

All major functional changes, enhancements, and critical fixes are documented here.

---

## [2.11.1] - 2026-04-02
### Added
- **Standalone Setup Guide**: Panduan instalasi mendalam (`STANDALONE_INSTALL.md`) untuk XAMPP (Windows) dan server Linux (Apache/MySQL/PHP).
- **Tata cara konfigurasi**: Dokumentasi khusus untuk modul Apache, penjadwalan Cron Job, dan optimasi PHP.

---

## [2.11.0] - 2026-04-02
### Added
- **Block-based Subnet Pagination**: Implementasi sistem navigasi blok `/24` (256 IP) untuk subnet besar seperti `/21` agar tetap ringan dan responsif.
- **Global Subnet Stats**: Bar utilisasi IP kini menghitung seluruh kapasitas subnet ($2.048$ host untuk `/21`) meskipun sedang melihat satu blok tertentu.
- **Smart Chunked Scanning**: Fungsi pemindaian otomatis kini menyesuaikan dengan blok yang sedang dibuka untuk meminimalkan beban server.

### Fixed
- **IP Display Limit**: Perbaikan bug di mana subnet yang lebih besar dari `/24` hanya menampilkan 256 IP pertama.

---

## [2.10.0] - 2026-04-01
### Added
- **Manual Network Topology Manager**: Antarmuka terpusat baru untuk mendefinisikan koneksi fisik antar switch dan switch-ke-subnet secara eksplisit.
- **Hirarki Visual Pintar**: Visualisasi peta jaringan kini mengikuti alur **Switch -> VLAN -> Subnet** yang lebih logis dan rapi.
- **Polished UI rendering**: Implementasi *loading screen* dan pencegahan *flicker* kode Mermaid.js untuk pengalaman pengguna yang lebih premium.
- **Asset Externalization**: Pemindahan logika filter ke file JS eksternal (`assets/js/topo-manager.js`) untuk mematuhi kebijakan keamanan browser (CSP).

### Fixed
- **Self-linking Logic**: Pencegahan pemilihan switch yang sama sebagai sumber dan tujuan koneksi melalui filter *real-time*.

---

## [2.9.0] - 2026-03-31
### Added
- **Smart Offline Detection (Fail Counter)**: Mekanisme baru yang mencegah IP ditandai sebagai *offline* secara instan. Menggunakan kolom `fail_count` untuk melacak kegagalan scan berturut-turut.
- **Intensive Verification Probe**: Saat IP menghilang, sistem otomatis menjalankan verifikasi mendalam (Multi-ping, Deep Port Scan, forced ARP refresh, dan Nmap fallback) sebelum menaikkan angka kegagalan.
- **Customizable Fail Threshold**: Pengaturan ambang batas kegagalan scan (default: 3) yang dapat dikonfigurasi melalui menu UI Settings.

### Fixed
- **Subdirectory URL Routing**: Perbaikan file `.htaccess` untuk mendukung Clean URL (`/login`, `/index`) secara stabil saat aplikasi diinstal di sub-folder (seperti `/ipmanage/`).

### Removed
- **Time-based Bulk Cleanup**: Penghapusan logika pembersihan massal berbasis waktu (12 jam) yang tidak akurat, digantikan sepenuhnya oleh logika per-IP yang lebih cerdas.

---

## [2.8.0] - 2026-03-29
### Added
- **PHP Opcache Optimization**: Aktivasi dan tuning Opcache di Docker untuk mengurangi lag eksekusi PHP secara drastis (2-3x lebih responsif).
- **Redis Infrastructure**: Penambahan container Redis 7 dan ekstensi `php-redis` untuk dukungan caching session dan data berkinerja tinggi.
- **Browser Favicon**: Penambahan logo SVG pada header agar muncul di tab browser (favicon).
- **Developer Profile Photo**: Integrasi foto profil pengembang dari Google Drive pada halaman About.

### Fixed
- **Fatal Error (AuditLogHelper)**: Perbaikan bug "Class not found" pada `subnet-details.php` saat melakukan alokasi IP.
- **Database Sanitization**: Pembersihan seluruh token sensitif (Telegram, SMTP) dan data user pribadi dari skema publik `sql/database.sql`.

---

## [2.7.0] - 2026-03-29
### Added
- **Realtime CPU & Memory Monitoring**: Implementasi Server-Sent Events (SSE) pada halaman Switch Details untuk streaming data CPU dan RAM langsung dari SNMP setiap 5 detik — tanpa perlu refresh halaman.
- **Live Status Badge**: Indikator badge `LIVE` / `OFFLINE` di header "Hardware Health" untuk menampilkan status koneksi SSE secara visual.
- **Performance History Charts**: Dua grafik Chart.js riwayat CPU dan Memory di bawah tabel port mapping, dengan filter periode 1h / 6h / 24h / 48h.
- **Period Summary Card**: Kartu statistik yang menampilkan jumlah Active Interfaces, Mapped Devices, Avg CPU, dan Peak CPU selama periode yang dipilih.
- **switch_health_history Table**: Tabel database baru untuk menyimpan snapshot CPU & Memory tiap polling (auto-migrate, retensi 48 jam).
- **API Endpoints Baru**: `api/switch-health-stream.php` (SSE stream SNMP live) dan `api/switch-history.php` (data riwayat untuk Chart.js).

### Enhanced
- **Smooth Bar Animation**: Progress bar CPU dan Memory kini memiliki transisi animasi halus saat nilai berubah.
- **cron_switch_poll.php**: Setiap siklus polling kini otomatis menyimpan snapshot ke tabel history dan membersihkan data lama (>48 jam).

---

## [2.6.0] - 2026-03-29
### Added
- **Docker Support**: Full production-ready Docker Compose setup dengan dua kontainer (app + db).
- **Dual Config System**: Pemisahan konfigurasi otomatis antara lingkungan Docker (`config.docker.php`) dan XAMPP (`config.php`), dideteksi via variabel `DOCKER_ENV`.
- **Docker Volume Mount**: Source code di-mount langsung ke kontainer sehingga perubahan kode tidak memerlukan rebuild image.
- **DOCKER_INSTALL.md**: Panduan instalasi Docker lengkap dalam Bahasa Indonesia.

### Fixed
- **Docker Healthcheck**: Mengganti `healthcheck.sh` dengan `mysqladmin ping` agar kompatibel dengan semua varian image MariaDB di Linux.
- **Entrypoint Permission**: Dockerfile kini memanggil `bash entrypoint.sh` secara eksplisit, mengatasi error `permission denied` akibat perbedaan permission file antara Windows dan Linux.
- **Duplicate Constant**: Hapus definisi ganda `APP_URL` yang menyebabkan error `Constant already defined` dan menggagalkan `session_start()`.
- **Database Encoding**: Sinkronisasi `sql/database.sql` ke encoding UTF-8 tanpa BOM dari backup XAMPP, agar MariaDB di Docker bisa mengimpornya dengan benar.
- **Port Conflict**: Port host database dipindah ke `3307` untuk menghindari tabrakan dengan XAMPP/MySQL lokal yang menggunakan port 3306.
- **Robust Migration**: Skrip `db.php` kini memeriksa keberadaan tabel sebelum menjalankan migrasi, mencegah crash saat database baru diinisialisasi.

---


## [2.5.0] - 2026-03-28
### Added
- **L3 ARP Discovery**: Active polling of switch ARP caches to automatically pair IP addresses with physical ports.
- **Dynamic Subnet Lookup**: Automatic discovery association with the correct IPAM subnet, satisfying database integrity.
### Enhanced
- **Robust SNMP Engine**: Switched to plain value retrieval mode for universal hardware compatibility.
- **MikroTik Fine-Tuning**: Precise OID mapping for RouterOS health vitals.
### Fixed
- **Accuracy Fix**: CPU Load calculation now correctly identifies processor load instead of frequency (no more 680% readings).
- **SQL Integrity**: Resolved foreign key constraint violations during the discovery phase.

---

## [2.4.0] - 2026-03-28
### Added
- **Switch Health Monitoring**: Real-time dashboard for CPU usage, memory utilization, and system uptime.
- **Switch Details Module**: Dedicated deep-dive view for individual switches showcasing physical port mappings.
- **Enhanced Poller**: Background SNMP engine for recurring infrastructure checks.

---

## [2.3.0] - 2026-03-28
### Added
- **Parallel Discovery Engine**: Implementation of IPC worker pools (`proc_open`) for high-speed concurrent network scanning.
### Performance
- **Database Indexing**: Optimized `mac_addr` and `hostname` columns for high-speed device filtering.

---

## [2.2.0] - 2026-03-28
### Added
- **Network Toolbox**: Native integration of Ping, Traceroute, and MAC OUI Lookups.
### Enhanced
- **Discovery Signals**: Multi-probe methodology (Ping, Nmap, TCP Ports, ARP) for near 100% accuracy.

---

## [2.1.0] - 2026-03-28
### Added
- **Audit Logs**: Comprehensive activity tracking for both users and discovery engines.
- **Chart.js Analytics**: Visual trend reporting for network utilization and subnet density.

---

## [2.0.0] - 2026-03-27
### Changed
- **Premium Core**: Initial deployment of the high-performance IPAM v2 platform.
- **UI Redesign**: Complete transformation to professional dark-mode aesthetics.
- **Multi-Platform Core**: Native support for Docker (Linux) and XAMPP (Windows).

---
