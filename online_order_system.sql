-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 30, 2025 at 10:39 AM
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
-- Database: `online_order_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `archived_orders`
--

CREATE TABLE `archived_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `invoice_no` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `delivered_by` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_people`
--

CREATE TABLE `delivery_people` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `footer_info`
--

CREATE TABLE `footer_info` (
  `id` int(11) NOT NULL,
  `map` text DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `footer_info`
--

INSERT INTO `footer_info` (`id`, `map`, `address`, `phone`, `email`, `created_by`, `updated_at`) VALUES
(1, '<iframe src=\"https://maps.google.com/maps?q=Seeb,%20Muscat,%20Oman&t=&z=13&ie=UTF8&iwloc=&output=embed\" width=\"100%\" height=\"200\" style=\"border:0;\" allowfullscreen=\"\" loading=\"lazy\"></iframe>', '300 Halban St, Seeb, Muscat, Oman', '+249 11 909 9743', 'brocksm123@gmail.com', '<a href=\"https://github.com/connect2024\" target=\"_blank\">Brock</a>', '2025-04-30 06:10:48');

-- --------------------------------------------------------

--
-- Table structure for table `message_templates`
--

CREATE TABLE `message_templates` (
  `id` int(11) NOT NULL,
  `template` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `details` text NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `address` text NOT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `delivered_by` varchar(100) DEFAULT NULL,
  `status` enum('pending','delivered') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receives`
--

CREATE TABLE `receives` (
  `id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `details` text NOT NULL,
  `phone` varchar(15) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `theme` varchar(20) DEFAULT 'light'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key_name`, `value`, `theme`) VALUES
(1, 'header_logo', 'uploads/Asset1 1.png', 'light'),
(2, 'header_name', 'Brock', 'light'),
(3, 'theme', 'dark', 'light');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(8, 'brock', '$2y$10$ObvBSTOFsO5ihlttsKCRD.qidwfp8HWQrGvRoGB5IZ24MKAzJ2GCe', 'admin'),
(9, 'sara', '$2y$10$yC6kTSx.gybVCbCCaQ/4..M9qy/wdJOHbBpNrQ1vM8tacTD0THTqa', 'user');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `archived_orders`
--
ALTER TABLE `archived_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `delivery_people`
--
ALTER TABLE `delivery_people`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `footer_info`
--
ALTER TABLE `footer_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `message_templates`
--
ALTER TABLE `message_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `receives`
--
ALTER TABLE `receives`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_name` (`key_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `delivery_people`
--
ALTER TABLE `delivery_people`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `footer_info`
--
ALTER TABLE `footer_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `message_templates`
--
ALTER TABLE `message_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `receives`
--
ALTER TABLE `receives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
