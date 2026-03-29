# IPManager Pro: Development & Update History

All major functional changes, enhancements, and critical fixes are documented here.

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
