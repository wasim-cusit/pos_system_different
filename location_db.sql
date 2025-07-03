-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 01, 2025 at 11:27 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `location_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `id` int(11) NOT NULL,
  `province_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`id`, `province_id`, `name`) VALUES
(1, 1, 'Lahore'),
(2, 1, 'Rawalpindi'),
(3, 1, 'Multan'),
(4, 2, 'Karachi'),
(5, 2, 'Hyderabad'),
(6, 3, 'Peshawar'),
(7, 3, 'Hangu'),
(8, 4, 'Quetta'),
(9, 4, 'Gwadar'),
(10, 4, 'Zhob');

-- --------------------------------------------------------

--
-- Table structure for table `primary_schools`
--

CREATE TABLE `primary_schools` (
  `id` int(11) NOT NULL,
  `uc_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `primary_schools`
--

INSERT INTO `primary_schools` (`id`, `uc_id`, `name`) VALUES
(1, 1, 'Govt Primary School Ravi Model Lahore'),
(2, 2, 'Govt Girls Primary School Rawalpindi City'),
(3, 3, 'Govt Boys Primary School Shujabad Multan'),
(4, 4, 'Govt Primary School Korangi Block B'),
(5, 5, 'Govt Girls School Latifabad No.3'),
(6, 6, 'Govt Primary School Saddar Peshawar'),
(7, 7, 'Govt Primary School Thall No.1'),
(8, 7, 'Thall Model Primary School (Boys)'),
(9, 8, 'Govt Girls School Thall City Campus'),
(10, 9, 'Govt Primary School Sariab Road Quetta');

-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`id`, `name`) VALUES
(1, 'Punjab'),
(2, 'Sindh'),
(3, 'Khyber Pakhtunkhwa'),
(4, 'Balochistan');

-- --------------------------------------------------------

--
-- Table structure for table `tehsils`
--

CREATE TABLE `tehsils` (
  `id` int(11) NOT NULL,
  `district_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tehsils`
--

INSERT INTO `tehsils` (`id`, `district_id`, `name`) VALUES
(1, 1, 'Ravi'),
(2, 2, 'Rawalpindi City'),
(3, 3, 'Shujabad'),
(4, 4, 'Korangi'),
(5, 5, 'Latifabad'),
(6, 6, 'Peshawar City'),
(7, 7, 'Thall'),
(8, 8, 'Quetta City'),
(9, 9, 'Gwadar Town'),
(10, 10, 'Zhob City');

-- --------------------------------------------------------

--
-- Table structure for table `union_councils`
--

CREATE TABLE `union_councils` (
  `id` int(11) NOT NULL,
  `tehsil_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `union_councils`
--

INSERT INTO `union_councils` (`id`, `tehsil_id`, `name`) VALUES
(1, 1, 'Ravi Town'),
(2, 2, 'Rawal Town'),
(3, 3, 'Shujabad'),
(4, 4, 'Korangi 5'),
(5, 5, 'Latifabad 3'),
(6, 6, 'Peshawar Saddar'),
(7, 7, 'Thall'),
(8, 7, 'Thall Dallan'),
(9, 8, 'Quetta Sariab'),
(10, 9, 'Gwadar Central');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `districts`
--
ALTER TABLE `districts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `province_id` (`province_id`);

--
-- Indexes for table `primary_schools`
--
ALTER TABLE `primary_schools`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uc_id` (`uc_id`);

--
-- Indexes for table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tehsils`
--
ALTER TABLE `tehsils`
  ADD PRIMARY KEY (`id`),
  ADD KEY `district_id` (`district_id`);

--
-- Indexes for table `union_councils`
--
ALTER TABLE `union_councils`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tehsil_id` (`tehsil_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `districts`
--
ALTER TABLE `districts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `primary_schools`
--
ALTER TABLE `primary_schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `provinces`
--
ALTER TABLE `provinces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tehsils`
--
ALTER TABLE `tehsils`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `union_councils`
--
ALTER TABLE `union_councils`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `districts`
--
ALTER TABLE `districts`
  ADD CONSTRAINT `districts_ibfk_1` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`);

--
-- Constraints for table `primary_schools`
--
ALTER TABLE `primary_schools`
  ADD CONSTRAINT `primary_schools_ibfk_1` FOREIGN KEY (`uc_id`) REFERENCES `union_councils` (`id`);

--
-- Constraints for table `tehsils`
--
ALTER TABLE `tehsils`
  ADD CONSTRAINT `tehsils_ibfk_1` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`);

--
-- Constraints for table `union_councils`
--
ALTER TABLE `union_councils`
  ADD CONSTRAINT `union_councils_ibfk_1` FOREIGN KEY (`tehsil_id`) REFERENCES `tehsils` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
