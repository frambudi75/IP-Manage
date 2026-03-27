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

## 🛠️ Configuration
Edit `includes/config.php` to change database credentials or application settings.

## 📚 Documentation
Complete technical documentation is available in:

- `docs/README.md`

## 🎨 UI & Aesthetics
Powered by Vanilla CSS with **Lucide Icons** and **Google Fonts (Outfit)** for a premium corporate look.
