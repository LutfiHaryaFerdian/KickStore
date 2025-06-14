-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 14, 2025 at 07:37 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `shoe_store`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `created_at`) VALUES
(49, 2, 4, 1, '2025-06-14 19:08:42');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Sneakers', 'Casual and sport sneakers', '2025-06-01 16:29:08'),
(2, 'Formal', 'Formal and dress shoes', '2025-06-01 16:29:08'),
(3, 'Boots', 'Boots for all occasions', '2025-06-01 16:29:08'),
(4, 'Sandals', 'Summer sandals and flip-flops', '2025-06-01 16:29:08');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT '0.00',
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `shipping_amount` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `shipping_address` text,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `subtotal`, `tax_amount`, `shipping_amount`, `status`, `payment_status`, `payment_method`, `shipping_address`, `notes`, `created_at`) VALUES
(31, 2, 1980000.00, 0.00, 0.00, 0.00, 'pending', 'pending', 'bank_transfer', 'kandis', NULL, '2025-06-14 17:44:09'),
(33, 2, 1210000.00, 1100000.00, 110000.00, 0.00, 'pending', 'pending', 'bank_transfer', 'kandis', '', '2025-06-14 18:44:33'),
(34, 2, 1210000.00, 1100000.00, 110000.00, 0.00, 'pending', 'pending', 'cod', 'kandis', '', '2025-06-14 18:46:36'),
(35, 2, 1210000.00, 1100000.00, 110000.00, 0.00, 'pending', 'pending', 'bank_transfer', 'kandis', '', '2025-06-14 18:51:11');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(32, 31, 7, 1, 1800000.00),
(34, 33, 12, 1, 1100000.00),
(35, 34, 12, 1, 1100000.00),
(36, 35, 12, 1, 1100000.00);

-- --------------------------------------------------------

--
-- Table structure for table `payment_proofs`
--

CREATE TABLE `payment_proofs` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `admin_notes` text,
  `verified_by` int DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payment_proofs`
--

INSERT INTO `payment_proofs` (`id`, `order_id`, `file_path`, `original_filename`, `file_size`, `file_type`, `uploaded_at`, `status`, `admin_notes`, `verified_by`, `verified_at`) VALUES
(1, 33, 'uploads/payment_proofs/payment_1749926673_684dc311ecb8f.png', '90c16ac4-49d8-4a54-b7c8-eedd49782e05.png', 2308900, 'image/png', '2025-06-14 18:44:33', 'pending', NULL, NULL, NULL),
(2, 35, 'uploads/payment_proofs/payment_1749927071_684dc49fb7cd1.png', '90c16ac4-49d8-4a54-b7c8-eedd49782e05.png', 2308900, 'image/png', '2025-06-14 18:51:11', 'pending', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `stock` int DEFAULT '0',
  `category_id` int DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `size` varchar(10) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `stock`, `category_id`, `image`, `brand`, `size`, `color`, `image_url`, `status`, `created_at`) VALUES
(1, 'Nike Air Max', '', 2200000.00, 45, 1, NULL, 'Nike', '42', 'Black', 'uploads/products/6846eb620055d_1749478242.png', 'active', '2025-06-01 16:29:08'),
(2, 'Adidas Campuss', '', 1700000.00, 25, 1, NULL, 'Adidas', '41', 'White', 'uploads/products/6846ebe8844bd_1749478376.png', 'active', '2025-06-01 16:29:08'),
(4, 'Timberland Boots', 'Durable work boots', 2000000.00, 19, 3, NULL, 'Timberland', '44', 'Brown', 'uploads/products/684dc1477c603_1749926215.png', 'active', '2025-06-01 16:29:08'),
(6, 'Adidas Spezial', 'Sepatu Classic Adidas', 1700000.00, 87, 1, NULL, 'Adidas', '42', 'Black', 'uploads/products/6846eb2f8955e_1749478191.png', 'active', '2025-06-01 16:55:12'),
(7, 'Adidas Samba', '', 1800000.00, 27, 1, NULL, 'Adidas', '42', 'white', 'uploads/products/6846eaed55a31_1749478125.png', 'active', '2025-06-02 03:21:35'),
(8, 'Nike P-6000', '', 1500000.00, 47, 1, NULL, 'Nike', '42', 'Black', 'uploads/products/6846f4604b9dd_1749480544.png', 'active', '2025-06-09 14:48:16'),
(12, 'Nike Dunk', '', 1100000.00, 47, 1, NULL, 'Nike', '42', 'Black', 'uploads/products/684dc0ee3e5cf_1749926126.png', 'active', '2025-06-14 18:35:26'),
(13, 'New Balance 1906 L', '', 2500000.00, 50, 2, NULL, 'New Balance', '42', 'Black', 'uploads/products/684dce5e2fa9a_1749929566.png', 'active', '2025-06-14 19:32:46'),
(14, 'Crocs Classic Clog Lightning McQueen', '', 2200000.00, 25, 4, NULL, 'Crocs', '42', 'Merah', 'uploads/products/684dcf1379bcb_1749929747.png', 'active', '2025-06-14 19:35:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `role` enum('buyer','admin') DEFAULT 'buyer',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `address`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@shoestore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', NULL, NULL, 'admin', '2025-06-01 16:29:08'),
(2, 'lutpi', 'lutfiharyaferdian@gmail.com', '$2y$10$xEs/tn0eGQTSD8xEsyXBfuSUrxw/sM9Ta8.J7AV2TnJ01VaIbP9KO', 'lutpi', '08586', 'kandis', 'buyer', '2025-06-01 16:30:28'),
(3, 'Alka', 'indriazanalkautsar@gmail.com', '$2y$10$jwNeaCbayWfFHxXRoSTHce9OD84uQK6Cw2gZcKsR5uHoC1MEY1Kr.', 'Alka', '0812', 'kemiling', 'buyer', '2025-06-01 17:39:12'),
(4, 'lian', 'liananantaril@gmail.com', '$2y$10$nA07XtJFrNvl0HDn5Aj2xOhCLi2HCkjxCRIJ0KHNEIL0DSb92uNVq', 'lian', '0895', 'kemiling', 'buyer', '2025-06-09 16:40:30'),
(31, 'samid', 'dhimas@gmail.com', '$2y$10$yvCxZyUo9LRhPgFWUf2vC.PftWRIc9.vkkXQi6Go5rO8pCJ2eATMy', 'dhimas', '0895', 'sukarame', 'buyer', '2025-06-13 13:13:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD CONSTRAINT `payment_proofs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_proofs_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
