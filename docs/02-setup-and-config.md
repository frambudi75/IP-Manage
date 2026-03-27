# Setup dan Konfigurasi

## 1) Setup XAMPP (Windows)

1. Taruh source code di:
   - `C:\xampp\htdocs\ipmanage`
2. Jalankan Apache + MySQL dari XAMPP Control Panel.
3. Buat database `ipmanage`.
4. Import `sql/database.sql`.
5. Buka aplikasi:
   - `http://localhost/ipmanage`

## 2) Setup Docker

Jalankan dari root project:

```bash
docker-compose up -d
```

Lalu akses:

- `http://localhost:8080`

## 3) Konfigurasi Utama

File konfigurasi: `includes/config.php`.

Parameter penting:

- `DB_HOST` (default: `localhost`)
- `DB_NAME` (default: `ipmanage`)
- `DB_USER` (default: `root`)
- `DB_PASS` (default: kosong)
- `OFFLINE_TTL_MINUTES` (default: `30`)

Contoh override via environment:

- `OFFLINE_TTL_MINUTES=45` -> host dianggap offline jika tidak terlihat >= 45 menit.

## 4) Prasyarat Agar Discovery Maksimal

- Jalankan scanner dari host yang punya akses Layer-2/Layer-3 ke subnet target.
- Pastikan firewall host scanner mengizinkan:
  - ICMP outbound;
  - TCP probe outbound (80/443/22/445/3389);
  - SNMP query outbound (umumnya UDP 161).
- Jika pakai SNMP:
  - pastikan extension PHP SNMP aktif;
  - community string pada subnet sudah benar.

## 5) Pengaturan Scan per Subnet

Di `subnet-details.php`:

- `scan_interval = 0` -> manual only.
- `scan_interval > 0` -> ikut scan otomatis via `api/cron.php`.

Rekomendasi awal:

- subnet kecil/stabil: 30-60 menit.
- subnet besar/ramai: 60-360 menit.
