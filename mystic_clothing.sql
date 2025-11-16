-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 16, 2025 at 05:38 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mystic_clothing`
--
CREATE DATABASE IF NOT EXISTS `mystic_clothing` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `mystic_clothing`;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `name`, `email`, `role`, `password`) VALUES
(4, 'jainish', 'jainishv12@gmail.com', 'superadmin', '$2y$10$GnGHvzS1ojOuUCunnhN6vO/aAf98dPDtTxdvaj7I8C5/IW65dUaCK');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `billing_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Unpaid',
  `billing_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`billing_id`, `customer_id`, `order_id`, `amount`, `payment_method`, `status`, `billing_date`) VALUES
(1, 6, 1, 1299.00, NULL, 'Unpaid', '2025-08-27'),
(2, 6, 2, 1497.00, NULL, 'Unpaid', '2025-08-27'),
(3, 14, 3, 1299.00, NULL, 'Paid', '2025-09-15'),
(4, 14, 4, 1299.00, NULL, 'Paid', '2025-09-15'),
(5, 17, 5, 899.00, NULL, 'Paid', '2025-10-05'),
(6, 18, 6, 1299.00, NULL, 'Paid', '2025-10-07'),
(7, 18, 7, 2000.00, NULL, 'Paid', '2025-10-07'),
(8, 18, 8, 2000.00, NULL, 'Paid', '2025-10-07'),
(9, 18, 9, 2000.00, NULL, 'Paid', '2025-10-07'),
(10, 18, 10, 2000.00, NULL, 'Paid', '2025-10-11'),
(11, 18, 11, 2000.00, NULL, 'Paid', '2025-10-11'),
(12, 18, 12, 2000.00, NULL, 'Paid', '2025-10-11'),
(13, 18, 13, 899.00, NULL, 'Paid', '2025-10-11'),
(14, 18, 14, 2000.00, NULL, 'Paid', '2025-10-11'),
(15, 18, 15, 2000.00, NULL, 'Paid', '2025-10-11'),
(16, 18, 16, 2000.00, NULL, 'Paid', '2025-10-11'),
(17, 18, 17, 1798.00, NULL, 'Paid', '2025-10-11'),
(18, 18, 21, 2000.00, NULL, 'Paid', '2025-10-12'),
(19, 18, 22, 999.00, NULL, 'Paid', '2025-10-12'),
(20, 18, 23, 999.00, NULL, 'Paid', '2025-10-12'),
(21, 18, 24, 999.00, NULL, 'Paid', '2025-10-12'),
(22, 18, 25, 999.00, NULL, 'Paid', '2025-10-12'),
(23, 18, 26, 999.00, NULL, 'Paid', '2025-10-12'),
(24, 18, 27, 1500.00, NULL, 'Paid', '2025-10-12'),
(25, 18, 28, 1299.00, NULL, 'Paid', '2025-10-12'),
(26, 18, 29, 999.00, NULL, 'Paid', '2025-10-12'),
(27, 18, 30, 1299.00, NULL, 'Paid', '2025-10-12'),
(28, 18, 31, 1299.00, NULL, 'Paid', '2025-10-12'),
(29, 18, 32, 899.00, NULL, 'Paid', '2025-10-12'),
(30, 18, 33, 1500.00, NULL, 'Paid', '2025-10-12'),
(31, 18, 34, 1500.00, NULL, 'Paid', '2025-10-12'),
(32, 18, 35, 999.00, NULL, 'Paid', '2025-10-12'),
(33, 18, 36, 999.00, NULL, 'Paid', '2025-10-12'),
(34, 18, 37, 999.00, NULL, 'Paid', '2025-10-12'),
(35, 18, 38, 1500.00, NULL, 'Paid', '2025-10-12'),
(36, 18, 39, 3000.00, NULL, 'Paid', '2025-10-13'),
(37, 18, 40, 999.00, NULL, 'Paid', '2025-10-13'),
(38, 18, 41, 499.00, NULL, 'Paid', '2025-10-17'),
(39, 20, 42, 999.00, NULL, 'Paid', '2025-11-06'),
(40, 20, 43, 999.00, NULL, 'Paid', '2025-11-06'),
(41, 20, 44, 999.00, NULL, 'Paid', '2025-11-06'),
(42, 20, 45, 999.00, NULL, 'Paid', '2025-11-06'),
(43, 20, 46, 999.00, NULL, 'Paid', '2025-11-06'),
(44, 20, 47, 999.00, NULL, 'Paid', '2025-11-06'),
(45, 20, 48, 999.00, NULL, 'Paid', '2025-11-06'),
(46, 20, 49, 999.00, NULL, 'Paid', '2025-11-06'),
(47, 20, 50, 1500.00, NULL, 'Paid', '2025-11-06'),
(48, 18, 51, 999.00, NULL, 'Paid', '2025-11-07'),
(49, 18, 52, 999.00, NULL, 'Paid', '2025-11-07'),
(50, 18, 53, 999.00, NULL, 'Paid', '2025-11-07'),
(51, 18, 54, 999.00, NULL, 'Paid', '2025-11-07'),
(52, 18, 55, 999.00, NULL, 'Paid', '2025-11-07'),
(53, 20, 56, 999.00, NULL, 'Paid', '2025-11-08'),
(54, 20, 57, 999.00, NULL, 'Paid', '2025-11-08'),
(55, 20, 58, 999.00, NULL, 'Paid', '2025-11-08'),
(56, 20, 59, 999.00, NULL, 'Paid', '2025-11-08'),
(57, 18, 60, 999.00, NULL, 'Paid', '2025-11-08'),
(58, 20, 61, 999.00, NULL, 'Paid', '2025-11-08'),
(59, 18, 62, 1500.00, NULL, 'Paid', '2025-11-08'),
(60, 18, 63, 0.00, NULL, 'Bundle Freebie', '2025-11-08'),
(61, 20, 64, 1500.00, NULL, 'Paid', '2025-11-16');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `name`, `email`, `phone`, `address`, `password`, `created_at`, `reset_token_hash`, `reset_token_expires_at`) VALUES
(1, 'Jainish', 'jainishborichav@email.com', NULL, NULL, '$2y$10$FvCsT1KQJkpfz53pJ.2OvuEGpQu/AldQGPFIUfO5OKFkxBeDU7fqu', '2025-10-07 10:04:30', '69e032da66f2914064247875ecee5f3c181ed8402ab15e61210796b3db22a7a7', '2025-09-25 05:31:42'),
(5, 'Jainish boricha', 'jvb.ombca2023@gmail.com', NULL, NULL, '$2y$10$7N86bW1IN1MypCK7FwBm..Pk7wUAluVZ89tMpKPcS2ikKY.qPKGdG', '2025-10-07 10:04:30', NULL, NULL),
(6, 'boricha jainish vijaybhai', 'jainish@yahoo.com', NULL, NULL, '$2y$10$i9vOkvPk4y9Nsh/UL3q3COaV.w6lcn/bwftfJPkef7QvP6rgAHkLS', '2025-10-07 10:04:30', NULL, NULL),
(7, 'Jain', 'Jainish@gmail.com', NULL, NULL, '$2y$10$MOFCoTS2Lu5RhB7OlPoHfOrj.D.dpQdsn1T81DOtPwrrVtH87.Goe', '2025-10-07 10:04:30', 'f50ed218941ee4f90a7c548c6889c6eedc6c25db2a132dfc8ee70af2cd131d05', '2025-09-25 04:54:50'),
(9, 'Jainish', 'jai@gmail.com', NULL, NULL, '$2y$10$IRscjgJNyP7KKTQd7Wy9NuKV2hIxgubBqN/MMtplzFxCXrc2TPC.G', '2025-10-07 10:04:30', NULL, NULL),
(10, 'jack', 'jack@gmail.com', NULL, NULL, '$2y$10$EV5it9.HjhOdvoijhXQTwe6kALRH2ZY0OkvoxxytXu1HBW/4O5TWe', '2025-10-07 10:04:30', NULL, NULL),
(11, 'Jain', 'Jainish11@gmail.com', NULL, NULL, '$2y$10$7iZ4LeaT59zc/Si1kYomPOfpU9cdQCNfCbOgmLyz8CuMqs0q2ape6', '2025-10-07 10:04:30', NULL, NULL),
(14, 'Jainish vijaybhai boricha', 'jainishv12@gmail.com', NULL, NULL, '$2y$10$oeIMb7OZC3XX07PDCMdvk.oM5zaXkEGqZwGKurq83yRQbAKUlogaG', '2025-10-07 10:04:30', NULL, NULL),
(16, 'jainish', 'borichajainish@gmail.com', NULL, NULL, '$2y$10$SazmrwK43ZEi5M2BdxZ6G.h7qAWWZTqxM1mKgsaqf9u6OlU4KH6.m', '2025-10-07 10:04:30', NULL, NULL),
(17, 'JAINISH boricha', 'jainishboricha111v@gmail.com', NULL, NULL, '$2y$10$pEl3lFQH3xoxSB4K.M1YjuTPvSYWd99T8hUzaOk0VKQL83sHtGIRi', '2025-10-07 10:04:30', NULL, NULL),
(18, 'jainish', 'Jainish111@gmail.com', '+919081460664', 'bahadurgadh,morbi\r\nvavdi,morbi', '$2y$10$CrlQbMlnRog72Ms/KNwfSebKOfkr0xeQI5E6Fq732TPoTsh6FBn3y', '2025-10-07 10:10:32', NULL, NULL),
(20, 'Jainish vijaybhai boricha', 'jainishborichav@gmail.com', '+919081460664', 'bahadurgadh,morbi\r\nvavdi,morbi', '$2y$10$8QeosgBc0qhlSLmlyIT/jupBBE5NnDBGFexcijcUZ3frVXRM2WZny', '2025-11-06 13:00:33', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customer_feedback`
--

CREATE TABLE `customer_feedback` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `feedback_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_feedback`
--

INSERT INTO `customer_feedback` (`id`, `order_id`, `rating`, `feedback_text`, `created_at`) VALUES
(1, 25, 5, 'nice tshirt and nice application but loved more if it has more options', '2025-10-12 11:30:02'),
(2, 24, 5, 'nice', '2025-10-13 17:29:31'),
(3, 42, 4, 'nice plain t-shirt what i like eactly', '2025-11-16 04:31:22');

-- --------------------------------------------------------

--
-- Table structure for table `custom_designs`
--

CREATE TABLE `custom_designs` (
  `design_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `front_preview_url` varchar(255) NOT NULL,
  `back_preview_url` varchar(255) NOT NULL,
  `left_preview_url` varchar(255) DEFAULT NULL,
  `right_preview_url` varchar(255) DEFAULT NULL,
  `texture_map_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `design_json` longtext DEFAULT NULL,
  `apparel_type` varchar(32) DEFAULT NULL,
  `base_color` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `custom_designs`
--

INSERT INTO `custom_designs` (`design_id`, `customer_id`, `product_name`, `price`, `front_preview_url`, `back_preview_url`, `left_preview_url`, `right_preview_url`, `texture_map_url`, `created_at`, `design_json`, `apparel_type`, `base_color`) VALUES
(1, 5, 'Custom 3D Designed T-Shirt', 1299.00, 'uploads/designs/68d4b6a1903a2.png', 'uploads/designs/68d4b6a190922.png', NULL, NULL, 'uploads/designs/68d4b6a190f0d.png', '2025-09-25 03:27:29', NULL, NULL, NULL),
(2, 18, 'Custom 3D Designed T-Shirt', 1299.00, 'uploads/designs/68e4ea204a6c0.png', 'uploads/designs/68e4ea204ae55.png', NULL, NULL, 'uploads/designs/68e4ea204b411.png', '2025-10-07 10:23:28', NULL, NULL, NULL),
(3, 18, 'Custom 3D Designed T-Shirt', 1299.00, 'uploads/designs/68e4ea50a3681.png', 'uploads/designs/68e4ea50a4211.png', NULL, NULL, 'uploads/designs/68e4ea50a45d3.png', '2025-10-07 10:24:16', NULL, NULL, NULL),
(4, 18, 'Custom Tshirt', 999.00, 'uploads/designs/front.png', 'uploads/designs/back.png', NULL, NULL, 'uploads/designs/texture.png', '2025-10-07 10:49:28', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#a72a2a\"}', 'tshirt', '#a72a2a'),
(5, 18, 'Custom Custom Apparel', 999.00, 'uploads/designs/design_68e8f173f32f9_front.png', '', NULL, NULL, '', '2025-10-10 11:43:48', NULL, NULL, NULL),
(6, 18, 'Custom Custom Apparel', 999.00, 'uploads/designs/design_68e9b3fb15b7d_front.png', '', NULL, NULL, '', '2025-10-11 01:33:47', NULL, NULL, NULL),
(7, 18, 'Custom Custom Apparel', 999.00, 'uploads/designs/design_68e9b773c26ff_front.png', '', NULL, NULL, '', '2025-10-11 01:48:35', NULL, NULL, NULL),
(8, 18, 'Custom Custom Apparel', 999.00, 'uploads/designs/design_68e9b7fce33e8_front.png', '', NULL, NULL, '', '2025-10-11 01:50:52', NULL, NULL, NULL),
(9, 18, 'Custom Custom Apparel', 999.00, 'uploads/designs/design_68e9b83444dee_front.png', '', NULL, NULL, '', '2025-10-11 01:51:48', NULL, NULL, NULL),
(10, 18, 'Custom Custom Apparel', 999.00, 'uploads/designs/20251011/design_10/front.png', '', NULL, NULL, '', '2025-10-11 02:11:25', NULL, NULL, NULL),
(11, 18, 'Custom Custom Apparel', 999.00, 'uploads/designs/20251011/design_11/front.png', '', NULL, NULL, '', '2025-10-11 02:14:31', NULL, NULL, NULL),
(12, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251011/design_12/front.png', 'uploads/designs/20251011/design_12/back.png', NULL, NULL, '', '2025-10-11 02:22:50', '{\"version\":1,\"apparelType\":\"tshirt\",\"elements\":[]}', NULL, NULL),
(13, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251011/design_13/front.png', 'uploads/designs/20251011/design_13/back.png', NULL, NULL, '', '2025-10-11 03:54:48', '{\"version\":1,\"apparelType\":\"tshirt\",\"elements\":[]}', NULL, NULL),
(14, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251011/design_14/front.png', 'uploads/designs/20251011/design_14/back.png', NULL, NULL, '', '2025-10-11 03:59:05', '{\"version\":1,\"apparelType\":\"tshirt\",\"elements\":[]}', NULL, NULL),
(15, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251011/design_15/front.png', 'uploads/designs/20251011/design_15/back.png', NULL, NULL, '', '2025-10-11 04:05:43', '{\"version\":1,\"apparelType\":\"tshirt\",\"elements\":[]}', NULL, NULL),
(16, 18, 'Custom Custom', 999.00, 'uploads/designs/20251011/design_16/front.png', '', NULL, NULL, '', '2025-10-11 15:39:53', NULL, NULL, NULL),
(17, 18, 'Custom Custom', 999.00, 'uploads/designs/20251011/design_17/front.png', '', NULL, NULL, '', '2025-10-11 15:40:54', NULL, NULL, NULL),
(18, 18, 'Custom Custom', 999.00, 'uploads/designs/20251011/design_18/front.png', '', NULL, NULL, '', '2025-10-11 15:41:31', NULL, NULL, NULL),
(19, 18, 'Custom Custom', 999.00, 'uploads/designs/20251011/design_19/front.png', '', NULL, NULL, '', '2025-10-11 15:49:56', NULL, NULL, NULL),
(20, 18, 'Custom Custom', 999.00, 'uploads/designs/20251011/design_20/front.png', '', NULL, NULL, '', '2025-10-11 15:50:10', NULL, NULL, NULL),
(21, 18, 'Custom Custom', 999.00, 'uploads/designs/20251011/design_21/front.png', 'uploads/designs/20251011/design_21/back.png', NULL, NULL, '', '2025-10-11 15:56:16', NULL, NULL, NULL),
(22, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251011/design_22/front.png', 'uploads/designs/20251011/design_22/back.png', NULL, NULL, 'uploads/designs/20251011/design_22/texture.png', '2025-10-11 16:51:55', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(23, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251011/design_23/front.png', 'uploads/designs/20251011/design_23/back.png', NULL, NULL, 'uploads/designs/20251011/design_23/texture.png', '2025-10-11 16:52:26', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#9a3232\"}', 'tshirt', '#9a3232'),
(24, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251011/design_24/front.png', 'uploads/designs/20251011/design_24/back.png', NULL, NULL, 'uploads/designs/20251011/design_24/texture.png', '2025-10-11 16:52:42', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#9a3232\"}', 'tshirt', '#9a3232'),
(25, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251012/design_25/front.png', 'uploads/designs/20251012/design_25/back.png', NULL, NULL, 'uploads/designs/20251012/design_25/texture.png', '2025-10-12 02:03:21', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#201093\"}', 'tshirt', '#201093'),
(26, 18, 'Custom Shirt', 999.00, 'uploads/designs/20251012/design_26/front.png', 'uploads/designs/20251012/design_26/back.png', NULL, NULL, 'uploads/designs/20251012/design_26/texture.png', '2025-10-12 03:11:04', '{\"apparelType\":\"shirt\",\"baseColor\":\"#ea1a1a\"}', 'shirt', '#ea1a1a'),
(27, 18, 'Custom Suit', 999.00, 'uploads/designs/20251012/design_27/front.png', 'uploads/designs/20251012/design_27/back.png', NULL, NULL, 'uploads/designs/20251012/design_27/texture.png', '2025-10-12 03:24:11', '{\"apparelType\":\"suit\",\"baseColor\":\"#d48c8c\"}', 'suit', '#d48c8c'),
(28, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251012/design_28/front.png', 'uploads/designs/20251012/design_28/back.png', NULL, NULL, 'uploads/designs/20251012/design_28/texture.png', '2025-10-12 06:14:13', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#38bdf8\"}', 'tshirt', '#38bdf8'),
(29, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251012/design_29/front.png', 'uploads/designs/20251012/design_29/back.png', NULL, NULL, 'uploads/designs/20251012/design_29/texture.png', '2025-10-12 06:32:39', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(30, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251012/design_30/front.png', 'uploads/designs/20251012/design_30/back.png', NULL, NULL, 'uploads/designs/20251012/design_30/texture.png', '2025-10-12 09:35:08', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(31, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251012/design_31/front.png', 'uploads/designs/20251012/design_31/back.png', NULL, NULL, 'uploads/designs/20251012/design_31/texture.png', '2025-10-12 12:25:46', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(32, 18, 'Custom Shirt', 999.00, 'uploads/designs/20251012/design_32/front.png', 'uploads/designs/20251012/design_32/back.png', NULL, NULL, 'uploads/designs/20251012/design_32/texture.png', '2025-10-12 12:31:40', '{\"apparelType\":\"shirt\",\"baseColor\":\"#ef4444\"}', 'shirt', '#ef4444'),
(33, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251012/design_33/front.png', 'uploads/designs/20251012/design_33/back.png', NULL, NULL, 'uploads/designs/20251012/design_33/texture.png', '2025-10-12 14:36:36', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#ef4444\"}', 'tshirt', '#ef4444'),
(34, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251013/design_34/front.png', 'uploads/designs/20251013/design_34/back.png', NULL, NULL, 'uploads/designs/20251013/design_34/texture.png', '2025-10-13 14:30:09', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(35, 20, 'Custom Tshirt', 999.00, 'uploads/designs/20251106/design_35/front.png', 'uploads/designs/20251106/design_35/back.png', NULL, NULL, 'uploads/designs/20251106/design_35/texture.png', '2025-11-06 13:00:50', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(36, 20, 'Custom Tshirt', 999.00, 'uploads/designs/20251106/design_36/front.png', 'uploads/designs/20251106/design_36/back.png', NULL, NULL, 'uploads/designs/20251106/design_36/texture.png', '2025-11-06 13:27:58', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(37, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251107/design_37/front.png', 'uploads/designs/20251107/design_37/back.png', NULL, NULL, 'uploads/designs/20251107/design_37/texture.png', '2025-11-07 08:25:45', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#38bdf8\"}', 'tshirt', '#38bdf8'),
(38, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251107/design_38/front.png', 'uploads/designs/20251107/design_38/back.png', NULL, NULL, 'uploads/designs/20251107/design_38/texture.png', '2025-11-07 09:56:21', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(39, 18, 'Custom Tshirt', 999.00, 'uploads/designs/20251107/design_39/front.png', 'uploads/designs/20251107/design_39/back.png', NULL, NULL, 'uploads/designs/20251107/design_39/texture.png', '2025-11-07 10:03:07', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#ef4444\"}', 'tshirt', '#ef4444'),
(40, 20, 'Custom Tshirt', 999.00, 'uploads/designs/20251108/design_40/front.png', 'uploads/designs/20251108/design_40/back.png', NULL, NULL, 'uploads/designs/20251108/design_40/texture.png', '2025-11-08 04:31:48', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(41, 20, 'Custom Tshirt', 999.00, 'uploads/designs/20251108/design_41/front.png', 'uploads/designs/20251108/design_41/back.png', NULL, NULL, 'uploads/designs/20251108/design_41/texture.png', '2025-11-08 04:45:53', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(42, 20, 'Custom Tshirt', 999.00, 'uploads/designs/20251108/design_42/front.png', 'uploads/designs/20251108/design_42/back.png', NULL, NULL, 'uploads/designs/20251108/design_42/texture.png', '2025-11-08 05:54:21', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc'),
(43, 20, 'Custom Tshirt', 999.00, 'uploads/designs/20251108/design_43/front.png', 'uploads/designs/20251108/design_43/back.png', NULL, NULL, 'uploads/designs/20251108/design_43/texture.png', '2025-11-08 07:16:35', '{\"apparelType\":\"tshirt\",\"baseColor\":\"#cccccc\"}', 'tshirt', '#cccccc');

-- --------------------------------------------------------

--
-- Table structure for table `designer_reward_wallet`
--

CREATE TABLE `designer_reward_wallet` (
  `id` int(10) UNSIGNED NOT NULL,
  `designer_id` int(10) UNSIGNED NOT NULL,
  `buyer_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `design_id` int(10) UNSIGNED NOT NULL,
  `drop_slug` varchar(120) DEFAULT NULL,
  `reward_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('available','redeemed','expired') NOT NULL DEFAULT 'available',
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `redeemed_at` timestamp NULL DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `designs`
--

CREATE TABLE `designs` (
  `design_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `design_file` varchar(255) NOT NULL,
  `design_file_back` varchar(255) DEFAULT NULL,
  `design_type` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `designs`
--

INSERT INTO `designs` (`design_id`, `customer_id`, `design_file`, `design_file_back`, `design_type`, `created_at`) VALUES
(1, 6, 'N/A', NULL, 'none', '2025-08-27 00:00:00'),
(2, 6, 'N/A', NULL, 'none', '2025-08-27 00:00:00'),
(3, 7, 'uploads/designs/design_7_1757822287.png', NULL, '3D Custom', '2025-09-14 00:00:00'),
(4, 7, 'uploads/designs/design_7_1757822367.png', NULL, '3D Custom', '2025-09-14 00:00:00'),
(5, 10, 'uploads/designs/design_10_1757913669_front.png', 'uploads/designs/design_10_1757913669_back.png', '3D Custom', '2025-09-15 10:51:09'),
(6, 14, 'N/A', NULL, 'none', '2025-09-15 00:00:00'),
(7, 14, 'N/A', NULL, 'none', '2025-09-15 00:00:00'),
(8, 5, 'uploads/designs/design_5_1758769199_front.png', 'uploads/designs/design_5_1758769199_back.png', '3D Custom', '2025-09-25 08:29:59'),
(9, 17, 'N/A', NULL, 'none', '2025-10-05 00:00:00'),
(10, 18, 'N/A', NULL, 'none', '2025-10-07 00:00:00'),
(11, 18, 'uploads/designs/68e4f19832951.png', NULL, 'custom_tshirt', '2025-10-07 00:00:00'),
(12, 18, 'uploads/designs/68e4f259ee7a0.png', NULL, 'custom_tshirt', '2025-10-07 00:00:00'),
(13, 18, 'uploads/designs/68e4f3943e47d.png', NULL, 'custom_tshirt', '2025-10-07 00:00:00'),
(14, 18, 'uploads/designs/68e4f42c833e4.png', NULL, 'custom_tshirt', '2025-10-07 00:00:00'),
(15, 18, 'uploads/designs/68e4f8554b62f.png', NULL, 'custom_tshirt', '2025-10-07 00:00:00'),
(16, 18, 'uploads/designs/68e4f8a514d08.png', NULL, 'custom_tshirt', '2025-10-07 00:00:00'),
(17, 18, 'uploads/designs/68e4f93cb36c2.png', NULL, 'custom_tshirt', '2025-10-07 00:00:00'),
(18, 18, 'uploads/designs/68e4fc4546ae4.png', NULL, 'custom_tshirt', '2025-10-07 00:00:00'),
(19, 18, 'uploads/designs/68e4fd7ed2a8a.png', NULL, 'custom_tshirt', '2025-10-07 00:00:00'),
(20, 18, 'uploads/designs/68e50915635b8.png', NULL, 'custom_tshirt', '2025-10-07 00:00:00'),
(21, 18, 'N/A', NULL, 'standard_product', '2025-10-11 00:00:00'),
(22, 18, 'N/A', NULL, 'standard_product', '2025-10-11 00:00:00'),
(23, 18, 'uploads/designs/20251012/design_25/front.png', 'uploads/designs/20251012/design_25/back.png', 'custom', '2025-10-12 07:44:15'),
(24, 18, 'uploads/designs/20251012/design_26/front.png', 'uploads/designs/20251012/design_26/back.png', 'custom', '2025-10-12 11:44:31'),
(25, 18, 'uploads/designs/20251012/design_27/front.png', 'uploads/designs/20251012/design_27/back.png', 'custom', '2025-10-12 11:44:31'),
(26, 18, 'uploads/designs/20251012/design_28/front.png', 'uploads/designs/20251012/design_28/back.png', 'custom', '2025-10-12 11:44:31'),
(27, 18, 'uploads/designs/20251012/design_29/front.png', 'uploads/designs/20251012/design_29/back.png', 'custom', '2025-10-12 13:06:17'),
(28, 18, 'uploads/designs/20251011/design_23/front.png', 'uploads/designs/20251011/design_23/back.png', 'custom', '2025-10-12 13:08:03'),
(29, 18, 'N/A', NULL, 'standard_product', '2025-10-12 00:00:00'),
(30, 18, 'N/A', NULL, 'standard_product', '2025-10-12 00:00:00'),
(31, 18, 'N/A', NULL, 'standard_product', '2025-10-12 00:00:00'),
(32, 18, 'N/A', NULL, 'standard_product', '2025-10-12 00:00:00'),
(33, 18, 'N/A', NULL, 'standard_product', '2025-10-12 00:00:00'),
(34, 18, 'N/A', NULL, 'standard_product', '2025-10-12 00:00:00'),
(35, 18, 'N/A', NULL, 'standard_product', '2025-10-13 00:00:00'),
(36, 18, 'N/A', NULL, 'standard_product', '2025-10-17 00:00:00'),
(37, 20, 'N/A', NULL, 'standard_product', '2025-11-06 00:00:00'),
(38, 20, 'N/A', NULL, 'standard_product', '2025-11-06 00:00:00'),
(39, 20, 'N/A', NULL, 'standard_product', '2025-11-06 00:00:00'),
(40, 20, 'N/A', NULL, 'standard_product', '2025-11-06 00:00:00'),
(41, 20, 'N/A', NULL, 'standard_product', '2025-11-06 00:00:00'),
(42, 18, 'N/A', NULL, 'standard_product', '2025-11-07 00:00:00'),
(43, 18, 'N/A', NULL, 'standard_product', '2025-11-07 00:00:00'),
(44, 18, 'N/A', NULL, 'standard_product', '2025-11-08 00:00:00'),
(45, 18, 'N/A', NULL, 'standard_product', '2025-11-08 00:00:00'),
(46, 18, 'N/A', NULL, 'standard_product', '2025-11-08 00:00:00'),
(47, 20, 'N/A', NULL, 'standard_product', '2025-11-16 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `design_spotlight_submissions`
--

CREATE TABLE `design_spotlight_submissions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `design_id` int(11) DEFAULT NULL,
  `title` varchar(120) NOT NULL,
  `story` text NOT NULL,
  `homepage_quote` varchar(160) DEFAULT NULL,
  `inspiration_url` varchar(255) DEFAULT NULL,
  `instagram_handle` varchar(80) DEFAULT NULL,
  `design_preview` varchar(255) DEFAULT NULL,
  `share_gallery` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `moderated_at` datetime DEFAULT NULL,
  `moderated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `design_spotlight_submissions`
--

INSERT INTO `design_spotlight_submissions` (`id`, `customer_id`, `design_id`, `title`, `story`, `homepage_quote`, `inspiration_url`, `instagram_handle`, `design_preview`, `share_gallery`, `status`, `created_at`, `moderated_at`, `moderated_by`) VALUES
(1, 18, 32, 'Action Jaction', 'Like birds of a feather flock together, thoughts and words also walk together. To ignite your creativity, we bring you a Random Phrase Generator tool with advanced artificial intelligence. It generates random phrases with substantial definitions. For example, if you hit generate, it will create phrases like “Burst Your Bubble” with a definition below it, that is “To Ruin Someone’s Happy Moment”. This tool will not only help your linguistic expertise thrive, but also make your brain think like a prudent person.', NULL, NULL, NULL, 'uploads/designs/20251012/design_32/front.png', 1, 'approved', '2025-11-07 04:56:21', '2025-11-07 11:02:45', 4),
(2, 18, 29, 'jainish tshirt', 'Like birds of a feather flock together, thoughts and words also walk together. To ignite your creativity, we bring you a Random Phrase Generator tool with advanced artificial intelligence. It generates random phrases with substantial definitions. For example, if you hit generate, it will create phrases like “Burst Your Bubble” with a definition below it, that is “To Ruin Someone’s Happy Moment”. This tool will not only help your linguistic expertise thrive, but also make your brain think like a prudent person.', NULL, NULL, NULL, 'uploads/designs/20251012/design_29/front.png', 1, 'approved', '2025-11-07 05:12:40', '2025-11-07 11:14:04', 4),
(3, 18, 30, 'fancy tshirt', 'it just nice to have t shirt which is made as your choice so basically it is wonderful', 'this design is blue like ocean and has strip like island and that orange is my personal yacht', NULL, NULL, 'uploads/designs/20251012/design_30/front.png', 1, 'approved', '2025-11-07 05:48:12', '2025-11-07 11:18:23', 4);

-- --------------------------------------------------------

--
-- Table structure for table `flash_banners`
--

CREATE TABLE `flash_banners` (
  `id` int(10) UNSIGNED NOT NULL,
  `message` varchar(255) NOT NULL,
  `subtext` text DEFAULT NULL,
  `cta` varchar(160) DEFAULT NULL,
  `href` varchar(255) DEFAULT NULL,
  `badge` varchar(120) DEFAULT NULL,
  `variant` enum('promo','info','alert') NOT NULL DEFAULT 'promo',
  `dismissible` tinyint(1) NOT NULL DEFAULT 0,
  `mode` enum('standard','drop') NOT NULL DEFAULT 'standard',
  `drop_label` varchar(120) DEFAULT NULL,
  `drop_slug` varchar(120) DEFAULT NULL,
  `schedule_start` datetime DEFAULT NULL,
  `schedule_end` datetime DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `countdown_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `countdown_target` datetime DEFAULT NULL,
  `countdown_label` varchar(120) DEFAULT NULL,
  `countdown_mode` varchar(32) DEFAULT NULL,
  `visibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`visibility`)),
  `waitlist_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `waitlist_slug` varchar(120) DEFAULT NULL,
  `waitlist_button_label` varchar(160) DEFAULT NULL,
  `waitlist_success_copy` varchar(255) DEFAULT NULL,
  `drop_teaser` varchar(255) DEFAULT NULL,
  `drop_story` text DEFAULT NULL,
  `drop_highlights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`drop_highlights`)),
  `drop_access_notes` text DEFAULT NULL,
  `drop_media_url` varchar(255) DEFAULT NULL,
  `promotion_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`promotion_payload`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `material_type` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image_url` varchar(255) DEFAULT 'image/placeholder.png',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `is_clearance` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `product_name`, `stock_qty`, `material_type`, `price`, `image_url`, `is_archived`, `is_clearance`) VALUES
(1, 'Classic Tee', 49, 'Cotton', 499.00, 'image/6908f075853955.5c58ba72acb59.png', 0, 0),
(2, 'Fleece Hoodie', 28, 'Fleece', 1299.00, 'image/4HK1MBL.webp', 0, 0),
(3, 'Danim Jeans', 46, 'cotten', 1999.00, 'image/OIP11.jpeg', 1, 0),
(4, 'Custom 3D Design', 9965, 'Custom', 2000.00, 'image/1cc7738fc9ba1d90f421717c0f5c62bc.jpg', 0, 0),
(5, 'Black SAD T-shirt ', 15, 'Aesthetic ', 1500.00, 'image/2f21f32c33bb47ef43ae3fa1ff772d1c.jpg', 0, 0),
(6, 'Danim Jeans', 8, 'Denim', 999.00, 'image/12d6dd5b088ac6053660a83064de702f.jpg', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_quiz_tags`
--

CREATE TABLE `inventory_quiz_tags` (
  `inventory_id` int(11) NOT NULL,
  `style_tags` varchar(255) NOT NULL,
  `palette_tags` varchar(255) NOT NULL,
  `goal_tags` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_quiz_tags`
--

INSERT INTO `inventory_quiz_tags` (`inventory_id`, `style_tags`, `palette_tags`, `goal_tags`, `created_at`, `updated_at`) VALUES
(1, 'street,minimal', 'monochrome,earth', 'everyday,launch', '2025-10-12 08:19:05', '2025-10-12 08:19:05'),
(2, 'street,bold', 'vivid,monochrome', 'launch,everyday', '2025-10-12 08:19:05', '2025-10-12 08:19:05'),
(3, 'street', 'earth,monochrome', 'everyday', '2025-10-12 08:19:05', '2025-10-12 08:19:05'),
(4, 'street,minimal,bold', 'monochrome,earth,vivid', 'everyday,launch,gift', '2025-10-12 08:19:05', '2025-10-12 08:19:05'),
(5, 'bold', 'earth', 'everyday', '2025-10-12 08:19:05', '2025-11-07 11:00:49'),
(6, 'street,minimal,bold', 'monochrome,earth,vivid', 'everyday,launch,gift', '2025-11-07 08:24:39', '2025-11-07 08:24:39');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `design_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `order_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `design_id`, `inventory_id`, `status`, `order_date`) VALUES
(1, 6, 1, 2, 'Completed', '2025-08-27'),
(2, 6, 2, 1, 'Completed', '2025-08-27'),
(3, 14, 6, 2, 'Completed', '2025-09-15'),
(4, 14, 7, 2, 'Completed', '2025-09-15'),
(5, 17, 9, 3, 'Completed', '2025-10-05'),
(6, 18, 10, 2, 'Completed', '2025-10-07'),
(7, 18, 18, 4, 'Completed', '2025-10-07'),
(8, 18, 19, 4, 'Completed', '2025-10-07'),
(9, 18, 20, 4, 'Completed', '2025-10-07'),
(10, 18, 7, 4, 'Completed', '2025-10-11'),
(11, 18, 8, 4, 'Completed', '2025-10-11'),
(12, 18, 9, 4, 'Completed', '2025-10-11'),
(13, 18, 21, 3, 'Completed', '2025-10-11'),
(14, 18, 19, 4, 'Completed', '2025-10-11'),
(15, 18, 20, 4, 'Completed', '2025-10-11'),
(16, 18, 21, 4, 'Completed', '2025-10-11'),
(17, 18, 22, 3, 'Completed', '2025-10-11'),
(21, 18, 23, 4, 'Completed', '2025-10-12'),
(22, 18, 24, 4, 'Completed', '2025-10-12'),
(23, 18, 25, 4, 'Completed', '2025-10-12'),
(24, 18, 26, 4, 'Completed', '2025-10-12'),
(25, 18, 27, 4, 'Completed', '2025-10-12'),
(26, 18, 28, 4, 'Completed', '2025-10-12'),
(27, 18, 29, 5, 'Completed', '2025-10-12'),
(28, 18, 30, 2, 'Completed', '2025-10-12'),
(29, 18, 25, 4, 'Completed', '2025-10-12'),
(30, 18, 2, 4, 'Completed', '2025-10-12'),
(31, 18, 2, 4, 'Completed', '2025-10-12'),
(32, 18, 31, 3, 'Completed', '2025-10-12'),
(33, 18, 32, 5, 'Completed', '2025-10-12'),
(34, 18, 33, 5, 'Completed', '2025-10-12'),
(35, 18, 31, 4, 'Completed', '2025-10-12'),
(36, 18, 31, 4, 'Completed', '2025-10-12'),
(37, 18, 32, 4, 'Completed', '2025-10-12'),
(38, 18, 34, 5, 'Completed', '2025-10-12'),
(39, 18, 35, 5, 'shipped', '2025-10-13'),
(40, 18, 34, 4, 'shipped', '2025-10-13'),
(41, 18, 36, 1, 'shipped', '2025-10-17'),
(42, 20, 35, 4, 'completed', '2025-11-06'),
(43, 20, 35, 4, 'pending', '2025-11-06'),
(44, 20, 36, 4, 'pending', '2025-11-06'),
(45, 20, 35, 4, 'pending', '2025-11-06'),
(46, 20, 37, 6, 'pending', '2025-11-06'),
(47, 20, 38, 6, 'pending', '2025-11-06'),
(48, 20, 39, 6, 'processing', '2025-11-06'),
(49, 20, 40, 6, 'processing', '2025-11-06'),
(50, 20, 41, 5, 'pending', '2025-11-06'),
(51, 18, 4, 4, 'pending', '2025-11-07'),
(52, 18, 42, 6, 'pending', '2025-11-07'),
(53, 18, 4, 4, 'pending', '2025-11-07'),
(54, 18, 43, 6, 'pending', '2025-11-07'),
(55, 18, 37, 4, 'pending', '2025-11-07'),
(56, 20, 40, 4, 'pending', '2025-11-08'),
(57, 20, 41, 4, 'pending', '2025-11-08'),
(58, 20, 42, 4, 'pending', '2025-11-08'),
(59, 20, 42, 4, 'pending', '2025-11-08'),
(60, 18, 44, 6, 'pending', '2025-11-08'),
(61, 20, 43, 4, 'pending', '2025-11-08'),
(62, 18, 45, 5, 'processing', '2025-11-08'),
(63, 18, 46, 5, 'shipped', '2025-11-08'),
(64, 20, 47, 5, 'pending', '2025-11-16');

-- --------------------------------------------------------

--
-- Table structure for table `printservice`
--

CREATE TABLE `printservice` (
  `service_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_info` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report`
--

CREATE TABLE `report` (
  `report_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status_summary` text DEFAULT NULL,
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `style_quiz_results`
--

CREATE TABLE `style_quiz_results` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `style_choice` varchar(50) NOT NULL,
  `palette_choice` varchar(50) NOT NULL,
  `goal_choice` varchar(50) NOT NULL,
  `persona_label` varchar(120) NOT NULL,
  `persona_summary` varchar(255) NOT NULL,
  `recommendations_json` text NOT NULL,
  `source_label` varchar(60) NOT NULL DEFAULT 'shop_quiz',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `style_quiz_results`
--

INSERT INTO `style_quiz_results` (`id`, `customer_id`, `style_choice`, `palette_choice`, `goal_choice`, `persona_label`, `persona_summary`, `recommendations_json`, `source_label`, `submitted_at`, `updated_at`) VALUES
(1, 18, 'bold', 'earth', 'everyday', 'Statement Maker Bundle', 'We balanced earth-tone textures for a everyday rotation that never misses.', '[{\"inventory_id\":4,\"name\":\"Custom 3D Design\",\"price\":2000,\"image_url\":\"image/1cc7738fc9ba1d90f421717c0f5c62bc.jpg\",\"reason\":\"Bold statements flagged by merch team \\u2022 Earthy tone mix aligns with your picks\"},{\"inventory_id\":5,\"name\":\"Black SAD T-shirt \",\"price\":1500,\"image_url\":\"image/2f21f32c33bb47ef43ae3fa1ff772d1c.jpg\",\"reason\":\"Bold statements flagged by merch team \\u2022 Earthy tone mix aligns with your picks\"},{\"inventory_id\":6,\"name\":\"Danim Jeans\",\"price\":999,\"image_url\":\"image/12d6dd5b088ac6053660a83064de702f.jpg\",\"reason\":\"Bold statements flagged by merch team \\u2022 Earthy tone mix aligns with your picks\"}]', 'inbox_flow', '2025-11-07 12:04:25', '2025-11-07 11:04:25'),
(21, 20, 'minimal', 'earth', 'gift', 'Clean Classic Bundle', 'We balanced earth-tone textures for a gift kit that feels premium.', '[{\"inventory_id\":4,\"name\":\"Custom 3D Design\",\"price\":2000,\"image_url\":\"image/1cc7738fc9ba1d90f421717c0f5c62bc.jpg\",\"reason\":\"Minimal staples flagged by merch team \\u2022 Earthy tone mix aligns with your picks\"},{\"inventory_id\":6,\"name\":\"Danim Jeans\",\"price\":999,\"image_url\":\"image/12d6dd5b088ac6053660a83064de702f.jpg\",\"reason\":\"Minimal staples flagged by merch team \\u2022 Earthy tone mix aligns with your picks\"},{\"inventory_id\":1,\"name\":\"Classic Tee\",\"price\":499,\"image_url\":\"image/6908f075853955.5c58ba72acb59.png\",\"reason\":\"Minimal staples flagged by merch team \\u2022 Earthy tone mix aligns with your picks\"}]', 'inbox_flow', '2025-11-08 15:38:02', '2025-11-08 10:08:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`billing_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `reset_token_hash` (`reset_token_hash`);

--
-- Indexes for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_feedback_order` (`order_id`);

--
-- Indexes for table `custom_designs`
--
ALTER TABLE `custom_designs`
  ADD PRIMARY KEY (`design_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `designer_reward_wallet`
--
ALTER TABLE `designer_reward_wallet`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_order_design` (`order_id`,`design_id`),
  ADD KEY `idx_designer_status` (`designer_id`,`status`),
  ADD KEY `idx_drop_slug` (`drop_slug`);

--
-- Indexes for table `designs`
--
ALTER TABLE `designs`
  ADD PRIMARY KEY (`design_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- Indexes for table `design_spotlight_submissions`
--
ALTER TABLE `design_spotlight_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_design` (`design_id`),
  ADD KEY `idx_spotlight_status_created` (`status`,`created_at`),
  ADD KEY `idx_design_spotlight_status` (`status`);

--
-- Indexes for table `flash_banners`
--
ALTER TABLE `flash_banners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_flash_banners_drop_slug` (`drop_slug`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`);

--
-- Indexes for table `inventory_quiz_tags`
--
ALTER TABLE `inventory_quiz_tags`
  ADD PRIMARY KEY (`inventory_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `design_id` (`design_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_inventory_id` (`inventory_id`);

--
-- Indexes for table `printservice`
--
ALTER TABLE `printservice`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `style_quiz_results`
--
ALTER TABLE `style_quiz_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_customer` (`customer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `billing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `custom_designs`
--
ALTER TABLE `custom_designs`
  MODIFY `design_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `designer_reward_wallet`
--
ALTER TABLE `designer_reward_wallet`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `designs`
--
ALTER TABLE `designs`
  MODIFY `design_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `design_spotlight_submissions`
--
ALTER TABLE `design_spotlight_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `flash_banners`
--
ALTER TABLE `flash_banners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `printservice`
--
ALTER TABLE `printservice`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report`
--
ALTER TABLE `report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `style_quiz_results`
--
ALTER TABLE `style_quiz_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  ADD CONSTRAINT `fk_feedback_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `designs`
--
ALTER TABLE `designs`
  ADD CONSTRAINT `designs_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`);

--
-- Constraints for table `design_spotlight_submissions`
--
ALTER TABLE `design_spotlight_submissions`
  ADD CONSTRAINT `fk_spotlight_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_spotlight_design` FOREIGN KEY (`design_id`) REFERENCES `custom_designs` (`design_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_quiz_tags`
--
ALTER TABLE `inventory_quiz_tags`
  ADD CONSTRAINT `fk_quiz_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`design_id`) REFERENCES `designs` (`design_id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`);

--
-- Constraints for table `report`
--
ALTER TABLE `report`
  ADD CONSTRAINT `report_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
