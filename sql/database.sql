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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `audit_logs`
--

-- --------------------------------------------------------

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
  `data_sources` varchar(100) DEFAULT NULL,
  `fail_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `ip_addresses`
--

-- --------------------------------------------------------

-- --------------------------------------------------------

--
-- Struktur dari tabel `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES
(1, 'telegram_enabled', '0', '2026-03-29 06:00:00'),
(2, 'telegram_bot_token', '', '2026-03-29 06:00:00'),
(3, 'telegram_chat_id', '', '2026-03-29 06:00:00'),
(4, 'email_enabled', '0', '2026-03-29 06:00:00'),
(5, 'admin_email', '', '2026-03-29 06:00:00'),
(6, 'nmap_enabled', '0', '2026-03-29 06:00:00'),
(7, 'discovery_aggressive', '1', '2026-03-31 07:30:00'),
(41, 'subnet_limit_threshold', '80', '2026-03-31 07:30:00'),
(42, 'offline_fail_threshold', '3', '2026-03-31 07:30:00'),
(43, 'discord_enabled', '0', NOW()),
(44, 'slack_enabled', '0', NOW()),
(45, 'custom_netwatch_template', '', NOW());

-- --------------------------------------------------------

-- --------------------------------------------------------

--
-- Struktur dari tabel `stats_history`
--

CREATE TABLE `stats_history` (
  `id` int(11) NOT NULL,
  `snapshot_date` date NOT NULL,
  `total_active` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `subnets`
--

-- --------------------------------------------------------

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `switches`
--

-- --------------------------------------------------------

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `switch_port_map`
--

-- --------------------------------------------------------

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$iC1CpjbPVLpFx1BcbSTUsOZ52qhELYqHrKyADN/z9DF2UArhZEnPK', NULL, 'admin', '2026-03-27 04:17:59');

-- --------------------------------------------------------

-- --------------------------------------------------------

--
-- Struktur dari tabel `vlans`
--

CREATE TABLE `vlans` (
  `id` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `vlans`
--

-- --------------------------------------------------------

-- --------------------------------------------------------

--
-- Struktur dari tabel `netwatch`
--

CREATE TABLE `netwatch` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `host` varchar(100) NOT NULL,
  `ping_interval` int(11) NOT NULL DEFAULT 60,
  `status` enum('up', 'down', 'unknown') NOT NULL DEFAULT 'unknown',
  `fail_count` int(11) NOT NULL DEFAULT 0,
  `fail_threshold` int(11) NOT NULL DEFAULT 3,
  `last_up` timestamp NULL DEFAULT NULL,
  `last_down` timestamp NULL DEFAULT NULL,
  `last_check` timestamp NULL DEFAULT NULL,
  `maintenance_until` datetime DEFAULT NULL,
  `notify` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `netwatch_history`
--

CREATE TABLE `netwatch_history` (
  `id` int(11) NOT NULL,
  `netwatch_id` int(11) NOT NULL,
  `latency` float DEFAULT 0,
  `status` enum('up', 'down', 'unknown') DEFAULT 'unknown',
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

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
-- Indeks untuk tabel `netwatch`
--
ALTER TABLE `netwatch`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `netwatch_history`
--
ALTER TABLE `netwatch_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_netwatch_time` (`netwatch_id`,`recorded_at`);

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
-- AUTO_INCREMENT untuk tabel `netwatch`
--
ALTER TABLE `netwatch`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `netwatch_history`
--
ALTER TABLE `netwatch_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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

--
-- Ketidakleluasaan untuk tabel `netwatch_history`
--
ALTER TABLE `netwatch_history`
  ADD CONSTRAINT `netwatch_history_ibfk_1` FOREIGN KEY (`netwatch_id`) REFERENCES `netwatch` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
