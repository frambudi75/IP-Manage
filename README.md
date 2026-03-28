# IPManager Pro

A premium, modern IP Address Management (IPAM) system similar to phpipam, designed for XAMPP and Docker.

## Features
- **Modern UI**: Dark-mode-first dashboard with glassmorphism effects.
- **Subnet Management**: Organize network prefixes and CIDR blocks.
- **IP Tracking**: Manage allocation, hostnames, and status of every IP.
- **VLAN Tracking**: Keep track of VLAN IDs and descriptions.
- **Multi-Environment**: Runs seamlessly on XAMPP (Windows) and Docker (Linux).

---

## 🚀 Installation (XAMPP)

1. **Clone/Copy** this project into your `htdocs` folder: `C:\xampp\htdocs\ipmanage`.
2. **Start MySQL** via XAMPP Control Panel.
3. **Import Database**:
   - Open [phpMyAdmin](http://localhost/phpmyadmin).
   - Create a new database named `ipmanage`.
   - Import the file located at `sql/database.sql`.
4. **Access the App**:
   - Open [http://localhost/ipmanage](http://localhost/ipmanage).
   - Login with: **admin** / **admin123**.

---

## 🐳 Installation (Docker)

1. Make sure you have **Docker Desktop** installed.
2. Open terminal in the project directory.
3. Run the following command:
   ```bash
   docker-compose up -d
   ```
4. Access the App:
   - Open [http://localhost:8080](http://localhost:8080).
   - Login with: **admin** / **admin123**.

---

## 🤖 Automation (Cron Scanner)

To enable automatic background scanning without manual intervention, follow these steps:

### Windows (Task Scheduler - Recommended for XAMPP)
1.  **Open Task Scheduler** (Search for it in Windows Start).
2.  Select **Create Basic Task** from the Actions menu.
3.  Name the task: `IPManager Scanner`.
4.  Trigger: Select **Daily**, then set it to repeat every **30 minutes** (configurable).
5.  Action: Select **Start a Program**.
    - **Program/script**: `C:\xampp\php\php.exe` (Adjust if XAMPP is installed elsewhere).
    - **Add arguments**: `C:\xampp\htdocs\ipmanage\cron_scanner.php`
6.  Click **Finish**.

### Linux (Crontab - Recommended for Docker/VPS)
Add the following line to your crontab (`crontab -e`):
```bash
*/30 * * * * /usr/bin/php /var/www/html/cron_scanner.php >> /var/log/ipmanage_scan.log 2>&1
```

### Manual CLI Run
You can always trigger a manual scan via terminal for debugging:
```bash
php cron_scanner.php
```

---

## 🛠️ Configuration
Edit `includes/config.php` to change database credentials or application settings.

## 📚 Documentation
Complete technical documentation is available in:

- `docs/README.md`

## 🎨 UI & Aesthetics
Powered by Vanilla CSS with **Lucide Icons** and **Google Fonts (Outfit)** for a premium corporate look.
