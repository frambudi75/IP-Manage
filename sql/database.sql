-- IPManager Pro Database Schema

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','user','viewer') NOT NULL DEFAULT 'viewer',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Sections table (e.g. Campus, Data Center)
CREATE TABLE IF NOT EXISTS `sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- VLANs table
CREATE TABLE IF NOT EXISTS `vlans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `number` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `number` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Subnets table
CREATE TABLE IF NOT EXISTS `subnets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subnet` varchar(45) NOT NULL,
  `mask` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `vlan_id` int(11) DEFAULT NULL,
  `master_subnet` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `section_id` (`section_id`),
  KEY `vlan_id` (`vlan_id`),
  CONSTRAINT `subnets_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subnets_ibfk_2` FOREIGN KEY (`vlan_id`) REFERENCES `vlans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- IP Addresses table
CREATE TABLE IF NOT EXISTS `ip_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subnet_id` int(11) NOT NULL,
  `ip_addr` varchar(45) NOT NULL,
  `description` text DEFAULT NULL,
  `hostname` varchar(100) DEFAULT NULL,
  `mac_addr` varchar(20) DEFAULT NULL,
  `vendor` varchar(100) DEFAULT NULL,
  `confidence_score` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `data_sources` varchar(100) DEFAULT NULL,
  `state` enum('active','reserved','offline','dhcp') NOT NULL DEFAULT 'active',
  `last_seen` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `subnet_id` (`subnet_id`),
  UNIQUE KEY `uniq_subnet_ip` (`subnet_id`,`ip_addr`),
  CONSTRAINT `ip_addresses_ibfk_1` FOREIGN KEY (`subnet_id`) REFERENCES `subnets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default admin (password: admin123)
INSERT INTO `users` (`username`, `password`, `role`) VALUES ('admin', '$2y$12$VTOR60IMUSAbECXvErSxbuHQ32VVEj5farrfEv1im/EV.k5xGRt5e', 'admin');

-- Insert default section
INSERT INTO `sections` (`name`, `description`) VALUES ('Default Section', 'Automatically created default section');
