-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 29 Mar 2026 pada 06.08
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ipmanage`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `created_at`) VALUES
(1, 3, 'add_switch', 'switch', 1, 'Added switch router utama (192.168.5.1)', '2026-03-28 07:13:18'),
(2, 3, 'add_switch', 'switch', 2, 'Added switch router utama (192.168.5.1)', '2026-03-28 07:13:41'),
(3, 3, 'delete_switch', 'switch', 1, 'Deleted switch ID 1', '2026-03-28 07:13:48'),
(4, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:38:27'),
(5, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:38:29'),
(6, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:38:30'),
(7, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:38:48'),
(8, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:38:50'),
(9, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:40:03'),
(10, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:45:31'),
(11, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:46:49'),
(12, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:47:11'),
(13, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:48:33'),
(14, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:48:35'),
(15, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:50:20'),
(16, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:50:49'),
(17, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:51:55'),
(18, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:51:56'),
(19, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:51:56'),
(20, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:52:32'),
(21, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:52:34'),
(22, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:52:51'),
(23, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:52:54'),
(24, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:52:56'),
(25, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:53:13'),
(26, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:54:18'),
(27, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:55:14'),
(28, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:55:16'),
(29, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:57:15'),
(30, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:57:25'),
(31, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:57:30'),
(32, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:57:49'),
(33, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:58:30'),
(34, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:58:32'),
(35, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:58:41'),
(36, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:58:46'),
(37, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:59:06'),
(38, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:59:24'),
(39, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:59:27'),
(40, NULL, 'poll_switch', 'switch', 2, 'Discovered 6 mappings on router utama', '2026-03-28 07:59:29');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ip_addresses`
--

CREATE TABLE `ip_addresses` (
  `id` int(11) NOT NULL,
  `subnet_id` int(11) NOT NULL,
  `ip_addr` varchar(45) NOT NULL,
  `description` text DEFAULT NULL,
  `hostname` varchar(100) DEFAULT NULL,
  `state` enum('active','reserved','offline','dhcp') NOT NULL DEFAULT 'active',
  `last_seen` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `mac_addr` varchar(20) DEFAULT NULL,
  `vendor` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `conflict_detected` tinyint(1) NOT NULL DEFAULT 0,
  `confidence_score` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `data_sources` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `ip_addresses`
--

INSERT INTO `ip_addresses` (`id`, `subnet_id`, `ip_addr`, `description`, `hostname`, `state`, `last_seen`, `created_at`, `mac_addr`, `vendor`, `os`, `conflict_detected`, `confidence_score`, `data_sources`) VALUES
(34, 3, '192.168.1.2', NULL, '', 'active', '2026-03-27 10:27:16', '2026-03-27 08:30:14', NULL, NULL, NULL, 0, 20, 'ping'),
(35, 3, '192.168.1.1', '', 'pfsense-test.frambudi', 'active', '2026-03-28 07:59:29', '2026-03-27 08:35:55', '50:5B:1D:34:65:33', 'Generic / Unknown', NULL, 0, 65, 'arp,ping,port,dns,snmp_arp'),
(36, 3, '192.168.1.156', '', 'desktop-t7mj6kt', 'active', '2026-03-27 10:32:56', '2026-03-27 08:38:32', '18:C0:4D:B4:7D:C8', 'Generic / Unknown', NULL, 0, 65, 'arp,ping,port,dns'),
(37, 3, '192.168.1.158', '', 'desktop-u6nbn32.frambudi', 'active', '2026-03-27 10:33:00', '2026-03-27 08:38:33', NULL, NULL, NULL, 0, 35, 'ping,port,dns'),
(38, 3, '192.168.1.182', '', '', 'active', '2026-03-27 08:52:23', '2026-03-27 08:39:17', 'C0:3F:D5:61:35:FC', 'Generic / Unknown', NULL, 0, 60, 'arp,ping,port'),
(39, 3, '192.168.1.255', '', '', 'active', '2026-03-27 10:35:39', '2026-03-27 08:40:18', 'FF:FF:FF:FF:FF:FF', 'Generic / Unknown', NULL, 0, 30, 'arp'),
(51, 3, '192.168.1.3', '', '', 'active', '2026-03-27 10:27:37', '2026-03-27 10:27:37', NULL, NULL, NULL, 0, 20, 'ping'),
(54, 3, '192.168.1.159', '', '', 'active', '2026-03-27 10:33:21', '2026-03-27 10:33:21', NULL, NULL, NULL, 0, 20, 'ping'),
(55, 3, '192.168.1.160', '', '', 'active', '2026-03-27 10:33:43', '2026-03-27 10:33:43', NULL, NULL, NULL, 0, 20, 'ping'),
(120, 9, '192.168.5.1', '', '', 'active', '2026-03-28 06:29:08', '2026-03-28 06:29:08', '4C:5E:0C:E6:51:89', 'MikroTik', 'Linux 2.6.32 - 3.10', 0, 60, 'arp,ping,port'),
(121, 9, '192.168.5.252', '', 'desktop-u6nbn32', 'active', '2026-03-28 07:59:29', '2026-03-28 06:36:35', '54:E1:AD:56:40:82', NULL, 'Microsoft Windows 10 1809 - 21H2', 0, 35, 'ping,port,dns,snmp_arp'),
(122, 10, '10.10.0.5', '', '', 'active', '2026-03-28 07:59:29', '2026-03-28 07:33:50', '4C:F5:DC:5E:E9:D2', NULL, 'Linux 3.2 - 4.14', 0, 30, 'ping,port,snmp_arp');

-- --------------------------------------------------------

--
-- Struktur dari tabel `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `sections`
--

INSERT INTO `sections` (`id`, `name`, `description`) VALUES
(1, 'Default Section', 'Automatically created default section');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(50) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES
(1, 'telegram_enabled', '1', '2026-03-28 05:11:17'),
(2, 'telegram_bot_token', '8746546081:AAFZ9rWFksJqaaAY6Atenp8bkv4nNlwQO3Y', '2026-03-28 05:11:17'),
(3, 'telegram_chat_id', '6244477500', '2026-03-28 05:11:17'),
(4, 'email_enabled', '1', '2026-03-28 05:11:17'),
(5, 'admin_email', 'habib@omit.avi.id', '2026-03-28 05:11:17'),
(6, 'nmap_enabled', '1', '2026-03-28 05:11:17'),
(7, 'discovery_aggressive', '1', '2026-03-28 05:09:41'),
(34, 'smtp_host', 'mail.avi.id', '2026-03-28 05:18:23'),
(35, 'smtp_port', '465', '2026-03-28 05:18:23'),
(36, 'smtp_user', 'habib@omit.avi.id', '2026-03-28 05:18:23'),
(37, 'smtp_pass', 'Aviasi2023', '2026-03-28 05:18:23'),
(38, 'mail_from', 'habib@omit.avi.id', '2026-03-28 05:18:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `stats_history`
--

CREATE TABLE `stats_history` (
  `id` int(11) NOT NULL,
  `snapshot_date` date NOT NULL,
  `total_active` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `subnets`
--

CREATE TABLE `subnets` (
  `id` int(11) NOT NULL,
  `subnet` varchar(45) NOT NULL,
  `mask` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `vlan_id` int(11) DEFAULT NULL,
  `master_subnet` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scan_interval` int(11) DEFAULT 0,
  `last_scan` timestamp NULL DEFAULT NULL,
  `last_limit_alert` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `subnets`
--

INSERT INTO `subnets` (`id`, `subnet`, `mask`, `description`, `section_id`, `vlan_id`, `master_subnet`, `created_at`, `scan_interval`, `last_scan`, `last_limit_alert`) VALUES
(3, '192.168.1.1', 24, 'firewall habib', 1, NULL, NULL, '2026-03-27 08:29:13', 30, NULL, NULL),
(9, '192.168.5.1', 24, 'inet-lan', 1, 2, NULL, '2026-03-28 06:28:41', 30, NULL, NULL),
(10, '10.10.0.1', 24, 'cctv', 1, 3, NULL, '2026-03-28 07:07:39', 30, NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `switches`
--

CREATE TABLE `switches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `ip_addr` varchar(45) NOT NULL,
  `community` varchar(100) DEFAULT 'public',
  `snmp_version` enum('1','2c','3') DEFAULT '2c',
  `description` text DEFAULT NULL,
  `last_poll` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `model` varchar(100) DEFAULT NULL,
  `uptime` varchar(100) DEFAULT NULL,
  `cpu_usage` int(11) DEFAULT 0,
  `memory_usage` int(11) DEFAULT 0,
  `system_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `switches`
--

INSERT INTO `switches` (`id`, `name`, `ip_addr`, `community`, `snmp_version`, `description`, `last_poll`, `created_at`, `model`, `uptime`, `cpu_usage`, `memory_usage`, `system_info`) VALUES
(2, 'router utama', '192.168.5.1', 'public', '2c', NULL, '2026-03-28 07:59:29', '2026-03-28 07:13:41', 'MikroTik', '15192200', 0, 0, 'RouterOS RB450G');

-- --------------------------------------------------------

--
-- Struktur dari tabel `switch_port_map`
--

CREATE TABLE `switch_port_map` (
  `id` int(11) NOT NULL,
  `mac_addr` varchar(100) NOT NULL,
  `switch_id` int(11) NOT NULL,
  `port_name` varchar(100) NOT NULL,
  `vlan_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `switch_port_map`
--

INSERT INTO `switch_port_map` (`id`, `mac_addr`, `switch_id`, `port_name`, `vlan_id`, `updated_at`) VALUES
(1, '4C:5E:0C:E6:51:88', 2, 'Port 0', NULL, '2026-03-28 07:59:29'),
(2, '4C:5E:0C:E6:51:89', 2, 'Port 0', NULL, '2026-03-28 07:59:29'),
(3, '4C:5E:0C:E6:51:8B', 2, 'ether4-to-cctv', NULL, '2026-03-28 07:59:29'),
(4, '4C:F5:DC:5E:E9:D2', 2, 'ether4-to-cctv', NULL, '2026-03-28 07:59:29'),
(5, '50:5B:1D:34:65:33', 2, 'ether1-inet', NULL, '2026-03-28 07:59:29'),
(6, '54:E1:AD:56:40:82', 2, 'ether2-pc', NULL, '2026-03-28 07:59:29');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','user','viewer') NOT NULL DEFAULT 'viewer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$iC1CpjbPVLpFx1BcbSTUsOZ52qhELYqHrKyADN/z9DF2UArhZEnPK', NULL, 'admin', '2026-03-27 04:17:59'),
(2, 'viewer', '$2y$10$QNO/v3lGp9GDZ/DDhBO3HuSJ6SMXaUaeD5C1xkFX1p3V6ZiW5qHD6', NULL, 'viewer', '2026-03-27 08:20:33'),
(3, 'habib', '$2y$10$Qc3pPSejizGU/b6CitJ3auy0TtFR923YvRwA4bZsSqgiyv33/bnxu', NULL, 'admin', '2026-03-27 08:22:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `vlans`
--

CREATE TABLE `vlans` (
  `id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `vlans`
--

INSERT INTO `vlans` (`id`, `number`, `name`, `description`) VALUES
(1, 99, 'vlan-management', ''),
(2, 100, 'lan-inet', ''),
(3, 200, 'cctv', '');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `ip_addresses`
--
ALTER TABLE `ip_addresses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_subnet_ip` (`subnet_id`,`ip_addr`),
  ADD KEY `subnet_id` (`subnet_id`),
  ADD KEY `idx_mac` (`mac_addr`),
  ADD KEY `idx_host` (`hostname`);

--
-- Indeks untuk tabel `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indeks untuk tabel `stats_history`
--
ALTER TABLE `stats_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`snapshot_date`);

--
-- Indeks untuk tabel `subnets`
--
ALTER TABLE `subnets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `vlan_id` (`vlan_id`);

--
-- Indeks untuk tabel `switches`
--
ALTER TABLE `switches`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `switch_port_map`
--
ALTER TABLE `switch_port_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mac_switch` (`mac_addr`,`switch_id`),
  ADD KEY `switch_id` (`switch_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `vlans`
--
ALTER TABLE `vlans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `number` (`number`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT untuk tabel `ip_addresses`
--
ALTER TABLE `ip_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT untuk tabel `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT untuk tabel `stats_history`
--
ALTER TABLE `stats_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `subnets`
--
ALTER TABLE `subnets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `switches`
--
ALTER TABLE `switches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `switch_port_map`
--
ALTER TABLE `switch_port_map`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=223;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `vlans`
--
ALTER TABLE `vlans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `ip_addresses`
--
ALTER TABLE `ip_addresses`
  ADD CONSTRAINT `ip_addresses_ibfk_1` FOREIGN KEY (`subnet_id`) REFERENCES `subnets` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `subnets`
--
ALTER TABLE `subnets`
  ADD CONSTRAINT `subnets_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `subnets_ibfk_2` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `switch_port_map`
--
ALTER TABLE `switch_port_map`
  ADD CONSTRAINT `switch_port_map_ibfk_1` FOREIGN KEY (`switch_id`) REFERENCES `switches` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

