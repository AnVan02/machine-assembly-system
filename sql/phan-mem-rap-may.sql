-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 11, 2026 at 02:46 AM
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
  `linhkien_chon` varchar(255) DEFAULT NULL,
  `so_serial` varchar(255) DEFAULT NULL,
  `so_may` int DEFAULT NULL,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chitiet_donhang`
--

INSERT INTO `chitiet_donhang` (`id_ct`, `id_donhang`, `ten_donhang`, `ten_cauhinh`, `ten_linhkien`, `loai_linhkien`, `linhkien_chon`, `so_serial`, `so_may`, `user_id`) VALUES
(44, 58, 'An Tâm', 'cấu hình 1', '3200G', 'CPU', 'cấu hình 1', '1', 1, NULL),
(45, 58, 'An Tâm', 'cấu hinh 2', '4500', 'CPU', NULL, '2', 0, NULL),
(46, 58, 'An Tâm', 'cấu hình 1', 'H610', 'MAIN', 'cấu hình 1', '1', 1, NULL),
(47, 58, 'An Tâm', 'cấu hinh 2', 'H550', 'MAIN', NULL, '1', 0, NULL),
(48, 58, 'An Tâm', 'cấu hinh 2, cấu hình 1 ', '8G', 'RAM', 'cấu hình 1', '1', 1, NULL),
(49, 58, 'An Tâm', 'cấu hinh 2, cấu hình 1', '8G', 'RAM', NULL, '2', 0, NULL),
(50, 58, 'An Tâm', 'cấu hinh 2, cấu hình 1', '8G', 'RAM', NULL, '1', 0, NULL),
(51, 58, 'An Tâm', 'cấu hinh 2, cấu hình 1 ', '256', 'SSD', NULL, '1', 0, NULL),
(52, 58, 'An Tâm', 'cấu hinh 2, cấu hình 1', '256', 'SSD', 'cấu hình 1', '2', 1, NULL),
(53, 58, 'An Tâm', 'cấu hình 1', '550W', 'PSU', 'cấu hình 1', '1', 1, NULL),
(54, 58, 'An Tâm', 'cấu hinh 2', '660W', 'PSU', NULL, '1', 0, NULL),
(55, 58, 'An Tâm', 'cấu hình 1', 'WIN 11 HOME', 'WIN', 'cấu hình 1', '1', 1, NULL),
(56, 58, 'An Tâm', 'cấu hinh 2', 'WIN 11 PRO', 'WIN', NULL, '1', 0, NULL);

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
(58, 'RS-1775874335', 'An Tâm', 2, NULL, '2026-04-11 02:25:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `role` enum('ketoan','kythuat','admin') DEFAULT 'kythuat',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `role`, `created_at`) VALUES
(1, 'ketoan', '$2y$10$XfRz1ZhUU7TgqiMRXz/hCeMLLP4zq48Te3SlWZChYsZIsUkjJU2Im', 'Kế Toán', 'ketoan', '2026-04-08 02:57:43'),
(2, 'kythuat', '$2y$10$U.jhARKGBLv5wI03RtamPugP3q/AThtPEZAG36jFF7bvyGzCpBA0C', 'Kỹ Thuật', 'kythuat', '2026-04-08 02:57:43'),
(3, 'admin', '$2y$10$KxUKEteoPv5UWm2/rDr5bOVLXJ8C0yekhjFBCS5w8FuEbxkCq43iq', 'Quản Trị Viên', 'admin', '2026-04-08 02:57:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chitiet_donhang`
--
ALTER TABLE `chitiet_donhang`
  ADD PRIMARY KEY (`id_ct`),
  ADD KEY `id_donhang` (`id_donhang`);

--
-- Indexes for table `donhang`
--
ALTER TABLE `donhang`
  ADD PRIMARY KEY (`id_donhang`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chitiet_donhang`
--
ALTER TABLE `chitiet_donhang`
  MODIFY `id_ct` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `donhang`
--
ALTER TABLE `donhang`
  MODIFY `id_donhang` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chitiet_donhang`
--
ALTER TABLE `chitiet_donhang`
  ADD CONSTRAINT `chitiet_donhang_ibfk_1` FOREIGN KEY (`id_donhang`) REFERENCES `donhang` (`id_donhang`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
