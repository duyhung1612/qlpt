-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost
-- Thời gian đã tạo: Th9 14, 2025 lúc 10:15 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `ql_phongtro`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `decided_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `room_id`, `status`, `created_at`, `decided_at`) VALUES
(1, 2, 5, 'approved', '2025-09-13 08:31:11', NULL),
(2, 1, 8, 'rejected', '2025-09-13 13:35:00', '2025-09-14 00:49:42'),
(3, 2, 8, 'rejected', '2025-09-13 13:35:33', '2025-09-14 00:49:41'),
(4, 2, 4, 'rejected', '2025-09-13 13:37:18', '2025-09-14 00:49:41'),
(5, 1, 9, 'rejected', '2025-09-13 15:54:01', '2025-09-14 00:49:40'),
(6, 1, 10, 'rejected', '2025-09-13 16:06:31', '2025-09-14 00:49:37'),
(7, 4, 9, 'approved', '2025-09-13 16:30:35', '2025-09-14 00:49:40'),
(8, 4, 6, 'approved', '2025-09-13 16:53:16', '2025-09-14 00:49:39'),
(9, 4, 10, 'cancelled', '2025-09-13 17:40:55', '2025-09-14 00:49:37');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `deposit` int(11) DEFAULT 0,
  `monthly_rent` int(11) DEFAULT 0,
  `status` enum('active','ended','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `contracts`
--

INSERT INTO `contracts` (`id`, `user_id`, `room_id`, `start_date`, `end_date`, `deposit`, `monthly_rent`, `status`, `notes`, `created_at`) VALUES
(2, 4, 10, '2025-09-14', '2026-09-15', 50000000, 50000000, 'active', NULL, '2025-09-14 19:48:30'),
(3, 2, 8, '2025-09-14', NULL, 0, 0, 'active', NULL, '2025-09-14 19:49:48'),
(4, 2, 5, '2025-09-14', '2026-01-09', 50000000, 5000000, 'active', NULL, '2025-09-14 19:55:28'),
(5, 2, 3, '2025-09-14', '2026-01-02', 20000000, 20000000, 'active', NULL, '2025-09-14 20:00:07');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `month` char(7) NOT NULL,
  `rent` int(11) NOT NULL DEFAULT 0,
  `electric_kwh` int(11) DEFAULT 0,
  `electric_price` int(11) DEFAULT 0,
  `water_m3` int(11) DEFAULT 0,
  `water_price` int(11) DEFAULT 0,
  `other_fee` int(11) DEFAULT 0,
  `discount` int(11) DEFAULT 0,
  `total` int(11) NOT NULL DEFAULT 0,
  `status` enum('unpaid','paid') DEFAULT 'unpaid',
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_sent` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `invoices`
--

INSERT INTO `invoices` (`id`, `contract_id`, `month`, `rent`, `electric_kwh`, `electric_price`, `water_m3`, `water_price`, `other_fee`, `discount`, `total`, `status`, `issued_at`, `paid_at`, `notes`, `amount`, `due_date`, `created_at`, `note`, `room_id`, `user_id`, `is_sent`, `sent_at`) VALUES
(1, 4, '', 0, 0, 0, 0, 0, 0, 0, 0, 'paid', '2025-09-14 20:14:29', NULL, NULL, 5000000, '2025-10-01', '2025-09-14 20:14:29', '', 5, 2, 0, NULL),
(2, 4, '', 0, 0, 0, 0, 0, 0, 0, 0, 'paid', '2025-09-14 20:14:32', NULL, NULL, 5000000, '2025-10-01', '2025-09-14 20:14:32', '', 5, 2, 0, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` int(11) NOT NULL,
  `area` float NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `deposit` int(11) DEFAULT 0,
  `electric_price` int(11) DEFAULT 0,
  `water_price` int(11) DEFAULT 0,
  `max_people` int(11) DEFAULT 1,
  `floor` int(11) DEFAULT 1,
  `status` enum('empty','reserved','occupied','maintenance') DEFAULT 'empty',
  `description` text DEFAULT NULL,
  `furnished` tinyint(1) DEFAULT 0,
  `ac` tinyint(1) DEFAULT 0,
  `private_bath` tinyint(1) DEFAULT 0,
  `kitchen` tinyint(1) DEFAULT 0,
  `balcony` tinyint(1) DEFAULT 0,
  `parking` tinyint(1) DEFAULT 0,
  `pet_friendly` tinyint(1) DEFAULT 0,
  `internet` tinyint(1) DEFAULT 0,
  `utilities_included` tinyint(1) DEFAULT 0,
  `available_from` date DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `rooms`
--

INSERT INTO `rooms` (`id`, `name`, `price`, `area`, `address`, `deposit`, `electric_price`, `water_price`, `max_people`, `floor`, `status`, `description`, `furnished`, `ac`, `private_bath`, `kitchen`, `balcony`, `parking`, `pet_friendly`, `internet`, `utilities_included`, `available_from`, `image`, `created_at`) VALUES
(3, 'Phòng svip01', 20000000, 50, 'Hoàng Mai', 20000000, 3, 100000, 5, 10, 'occupied', 'Phòng đầy đủ nội thất', 1, 1, 1, 1, 1, 1, 1, 1, 1, '2025-09-13', '1757872425_84dea40d.jpg', '2025-09-13 08:05:01'),
(4, 'Phòng svip02', 30000000, 60, 'Cầu Giấy', 30000000, 3, 100000, 6, 15, 'empty', 'Tiện nghi đầy đủ, Nội thất rất nhiều', 1, 1, 1, 1, 1, 1, 1, 1, 1, '2025-09-13', '1757872445_ba1003a6.jpg', '2025-09-13 08:06:16'),
(5, 'Phòng svip03', 70000000, 100, 'Hai Bà Trưng', 70000000, 3, 100000, 10, 20, 'occupied', 'Phòng đủ toàn bộ mọi thứ', 1, 1, 1, 1, 1, 1, 1, 1, 1, '2025-09-13', '1757872457_2b5ddd58.jpg', '2025-09-13 08:07:14'),
(6, 'Phòng svip04', 4000000, 30, 'Bắc Từ Liêm', 4000000, 4, 100000, 3, 5, 'occupied', '', 0, 1, 1, 0, 0, 1, 0, 1, 0, NULL, '1757872470_e5659c30.webp', '2025-09-13 08:10:44'),
(7, 'Phòng svip05', 50000000, 30, 'Nam Từ Liêm', 50000000, 3, 100000, 3, 4, 'empty', 'Phòng siêu rẻ', 0, 1, 1, 0, 0, 1, 0, 1, 0, '2025-09-13', '1757872481_fd45ecde.jpg', '2025-09-13 08:33:02'),
(8, 'Phòng svip07', 5000000, 30, 'Hoàn Kiếm', 5000000, 3, 100000, 4, 1, 'empty', '', 0, 1, 1, 1, 0, 0, 0, 0, 0, '2025-09-16', '1757794835_92322c23.webp', '2025-09-13 09:12:34'),
(9, 'Phòng A101', 2500000, 20, '123 Trần Phú, Hà Nội', 500000, 3500, 15000, 2, 1, 'empty', 'Phòng đầy đủ nội thất, gần trường học', 1, 1, 1, 0, 1, 0, 1, 0, 1, NULL, '1757874363_5aa8878b.webp', '2025-09-13 13:51:50'),
(10, 'Phòng B202', 3000000, 25, '456 Nguyễn Trãi, Hà Nội', 1000000, 3500, 15000, 3, 2, 'empty', 'Phòng rộng rãi, có ban công', 1, 1, 1, 1, 1, 1, 1, 1, 0, NULL, '1757872355_f36ef952.jpg', '2025-09-13 13:51:50');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `room_images`
--

CREATE TABLE `room_images` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `room_images`
--

INSERT INTO `room_images` (`id`, `room_id`, `path`, `is_primary`) VALUES
(21, 10, '1757874046_752a222f.jpg', 0),
(22, 9, '1757874367_0229018d.jpg', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gmail` varchar(50) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `status` enum('pending','active','rejected') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `full_name`, `phone`, `created_at`, `gmail`, `fullname`, `status`) VALUES
(1, 'admin', 'adminadmin', 'admin', 'Administrator', '0123456789', '2025-09-13 08:02:46', 'admin@gmail.com', NULL, 'pending'),
(2, 'duyhung', 'duyhung', 'user', NULL, NULL, '2025-09-13 08:30:58', 'duyhung@gmail.com', NULL, 'active'),
(4, 'ynhi', 'ynhi', 'user', '', '', '2025-09-13 16:30:27', 'ynhi@gmail.com', NULL, 'active');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Chỉ mục cho bảng `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Chỉ mục cho bảng `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `idx_invoices_user` (`user_id`),
  ADD KEY `idx_invoices_contract` (`contract_id`);

--
-- Chỉ mục cho bảng `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `room_images`
--
ALTER TABLE `room_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `room_images`
--
ALTER TABLE `room_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `room_images`
--
ALTER TABLE `room_images`
  ADD CONSTRAINT `room_images_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
