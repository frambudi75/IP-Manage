# IPManager Pro 🚀

A high-performance, professional IP Address Management (IPAM) and Network Monitoring system. Designed with a premium dark-mode UI and robust backend discovery.

## 🌟 Key Features
- **Parallel Scanning**: High-speed discovery using cross-platform worker pools.
- **SNMP Health Monitoring**: Real-time CPU, RAM, and Uptime tracking for Cisco/MikroTik.
- **L3 ARP Discovery**: Automatically maps IP addresses to physical switch ports.
- **Network Toolbox**: Integrated Ping, Traceroute, and OUI Lookup.
- **Audit Logs**: Detailed history of all network changes.

---

## 📋 Prerequisites
Before installation, ensure your environment meets these requirements:
- **PHP 8.1+** (with `pdo_mysql`, `snmp`, `curl`, `mbstring` extensions)
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Network Tools**: `nmap` and `traceroute` (highly recommended for better discovery accuracy)

---

## 🐳 Installation (Docker - Recommended for Linux/Pro)
The fastest way to deploy IPManager Pro with all dependencies pre-configured.

1. Clone this repository.
2. Run the deployment:
   ```bash
   docker-compose up -d
   ```
3. Access at `http://localhost:8080`.
4. Login: **admin** / **admin123**.

---

## 🐧 Installation (Linux Bare Metal - Ubuntu/Debian)
For VPS or dedicated Linux servers.

1. **Install Dependencies**:
   ```bash
   sudo apt update
   sudo apt install apache2 mariadb-server php php-mysql php-snmp php-curl nmap traceroute
   ```
2. **Setup Database**:
   ```bash
   mysql -u root -e "CREATE DATABASE ipmanage;"
   mysql -u root ipmanage < sql/database.sql
   ```
3. **Configure Permissions**:
   ```bash
   sudo chown -R www-data:www-data /var/www/html/ipmanage
   ```
4. **Cron Job**: Add `*/5 * * * * php /var/www/html/ipmanage/cron_scanner.php` to crontab.

---

## 🪟 Installation (XAMPP - Windows)
Perfect for local testing or Windows-based environments.

1. **Copy Files**: Move the project to `C:\xampp\htdocs\ipmanage`.
2. **Enable PHP Extensions**:
   - Open `C:\xampp\php\php.ini` in a text editor.
   - Remove `;` from `extension=snmp` and `extension=curl`.
   - Restart Apache via XAMPP Control Panel.
3. **Setup Database**:
   - Open [phpMyAdmin](http://localhost/phpmyadmin) and create `ipmanage` database.
   - Import `sql/database.sql`.
4. **Access**: [http://localhost/ipmanage](http://localhost/ipmanage).

---

## 🤖 Automation (Background Tasks)
To keep your network map updated, set up these tasks:

### Windows (Task Scheduler)
Create a task to run `C:\xampp\php\php.exe C:\xampp\htdocs\ipmanage\cron_scanner.php` every 30 minutes.

### Linux (Systemd/Crontab)
The Docker version handles this automatically. For Bare Metal, use:
```bash
*/15 * * * * /usr/bin/php /var/www/html/ipmanage/cron_switch_poll.php
```

---

## 🛠️ Configuration
Custom settings (Database, App URL) can be modified in `includes/config.php`.

## 🎨 UI Aesthetics
Powered by **Vanilla CSS**, **Lucide Icons**, and **Chart.js** for high-performance visual analytics.
