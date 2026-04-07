-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 30, 2026 at 07:45 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `phan-mem-rap-may`
--

-- --------------------------------------------------------

--
-- Table structure for table `chitiet_donhang`
--

CREATE TABLE `chitiet_donhang` (
  `id_ct` int NOT NULL,
  `id_donhang` int DEFAULT NULL,
  `ten_donhang` varchar(255) DEFAULT NULL,
  `ten_cauhinh` varchar(255) DEFAULT NULL,
  `ten_linhkien` varchar(255) DEFAULT NULL,
  `loai_linhkien` varchar(100) DEFAULT NULL,
  `so_serial` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `linhkien_chon` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `chitiet_donhang`
--

INSERT INTO `chitiet_donhang` (`id_ct`, `id_donhang`, `ten_donhang`, `ten_cauhinh`, `ten_linhkien`, `loai_linhkien`, `so_serial`, `linhkien_chon`, `user_id`) VALUES
(81, 5, 'Văn An', 'Ann1', '12100', 'CPU', '11', 'Ann1 | Máy 1', NULL),
(82, 5, 'Văn An', 'Ann2', '3400g', 'CPU', '11', 'Ann2 | Máy 1', NULL),
(83, 5, 'Văn An', 'Ann1', '12100', 'CPU', '22', 'Ann1 | Máy 2', NULL),
(84, 5, 'Văn An', 'Ann1, Ann2', 'h610', 'MAIN', '11', 'Ann1 | Máy 1', NULL),
(85, 5, 'Văn An', 'Ann2, Ann1', 'h610', 'MAIN', '22', 'Ann1 | Máy 2', NULL),
(86, 5, 'Văn An', 'Ann1, Ann2', 'h610', 'MAIN', '33', 'Ann2 | Máy 1', NULL),
(87, 5, 'Văn An', 'Ann1, Ann2', '8g', 'RAM', '11', 'Ann1 | Máy 1', NULL),
(88, 5, 'Văn An', 'Ann1, Ann2', '8g', 'RAM', '22', 'Ann1 | Máy 1', NULL),
(89, 5, 'Văn An', 'Ann2, Ann1', '8g', 'RAM', '33', 'Ann1 | Máy 2', NULL),
(90, 5, 'Văn An', 'Ann1, Ann2', '8g', 'RAM', '44', 'Ann1 | Máy 2', NULL),
(91, 5, 'Văn An', 'Ann1, Ann2', '8g', 'RAM', '55', 'Ann2 | Máy 1', NULL),
(92, 5, 'Văn An', 'Ann1, Ann2', '256', 'SSD', '11', 'Ann1 | Máy 1', NULL),
(93, 5, 'Văn An', 'Ann2, Ann1', '256', 'SSD', '22', 'Ann1 | Máy 2', NULL),
(94, 5, 'Văn An', 'Ann1, Ann2', '256', 'SSD', '33', 'Ann2 | Máy 1', NULL),
(95, 5, 'Văn An', 'Ann1, Ann2', '550w', 'PSU', '11', 'Ann1 | Máy 1', NULL),
(96, 5, 'Văn An', 'Ann2, Ann1', '550w', 'PSU', '22', 'Ann1 | Máy 2', NULL),
(97, 5, 'Văn An', 'Ann1, Ann2', '550w', 'PSU', '33', 'Ann2 | Máy 1', NULL),
(98, 5, 'Văn An', 'Ann1, Ann2', 'win 11 home', 'WIN', '11', 'Ann1 | Máy 1', NULL),
(99, 5, 'Văn An', 'Ann2, Ann1', 'win 11 home', 'WIN', '22', 'Ann1 | Máy 2', NULL),
(100, 5, 'Văn An', 'Ann1, Ann2', 'win 11 home', 'WIN', '33', 'Ann2 | Máy 1', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `donhang`
--

CREATE TABLE `donhang` (
  `id_donhang` int NOT NULL,
  `ma_don_hang` varchar(50) NOT NULL,
  `ten_khach_hang` varchar(255) DEFAULT NULL,
  `so_luong_may` int DEFAULT '1',
  `user_id` int DEFAULT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `donhang`
--

INSERT INTO `donhang` (`id_donhang`, `ma_don_hang`, `ten_khach_hang`, `so_luong_may`, `user_id`, `ngay_tao`) VALUES
(5, 'RS-1774899537', 'Văn An', 3, NULL, '2026-03-30 19:38:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chitiet_donhang`
--
ALTER TABLE `chitiet_donhang`
  ADD PRIMARY KEY (`id_ct`);

--
-- Indexes for table `donhang`
--
ALTER TABLE `donhang`
  ADD PRIMARY KEY (`id_donhang`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chitiet_donhang`
--
ALTER TABLE `chitiet_donhang`
  MODIFY `id_ct` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `donhang`
--
ALTER TABLE `donhang`
  MODIFY `id_donhang` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
