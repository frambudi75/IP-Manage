# Changelog - IPManager Pro

All notable changes to this project will be documented in this file.

## [2026-03-28] - System Optimization & Security Update

### Added
- **Dynamic System Settings Dashboard**: New administrative menu to manage Telegram bot, Discovery modes, and SMTP Email configuration without editing `config.php`.
- **Manual SMTP Native Sender**: Robust standalone SMTP client implementation supporting AUTH LOGIN and SSL/TLS (Port 465/587) for reliable notifications.
- **Auto-Migration Engine**: Real-time database schema checking that automatically adds missing tables and columns (e.g., `settings` table, `os` column, `conflict_detected` flag).
- **Visual Conflict Indicators**: Red-glowing grid items and "CONFLICT" badges in the detailed table to highlight MAC address mismatches on the same IP.
- **Notification Test Suite**: Test buttons in System Settings to verify Telegram and Email connectivity instantly.
- **Tabbed Configuration UI**: Clean redesigned interface with "UMUM", "NOTIFIKASI", and "EMAIL" tabs for better organization.

### Fixed
- **Ghost IP Prevention**: Significantly improved host detection accuracy by requiring a valid ARP/MAC signature. Hosts that respond to ping but have no MAC Address (ghost IPs) are now correctly ignored.
- **Auto-Pruning Offline Hosts**: During scanning, IPs that were previously active but are no longer detected are automatically marked as "Offline" to keep the dashboard clean.
- **Responsive Layout Improvements**: Fixed mobile sidebar behavior and Visual IP Grid scaling for smaller screens.

### Technical Changes
- Added `ipmanage.settings` table for persistent configuration.
- Added `conflict_detected` column to `ip_addresses` table.
- Added `includes/settings.helper.php` for centralized settings management.
- Refactored `NotificationHelper` to support dynamic database-driven credentials.
- Integrated `run_auto_migrations()` in `includes/db.php` for zero-effort database updates.

---
*Created by Habib Frambudi & AI Team*
