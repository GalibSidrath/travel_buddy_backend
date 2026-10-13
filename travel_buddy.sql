-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 13, 2026 at 06:48 PM
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
-- Database: `travel_buddy`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookmark`
--

CREATE TABLE `bookmark` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_room`
--

CREATE TABLE `chat_room` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `host_username` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_room`
--

INSERT INTO `chat_room` (`id`, `trip_id`, `username`, `host_username`, `created_at`) VALUES
(1, 4, 'galib', 'tanjim_traveler', '2026-10-10 15:13:12'),
(2, 4, 'new_traveler', 'tanjim_traveler', '2026-10-10 15:31:46');

-- --------------------------------------------------------

--
-- Table structure for table `confirm_trip`
--

CREATE TABLE `confirm_trip` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `confirm_trip`
--

INSERT INTO `confirm_trip` (`id`, `trip_id`, `username`) VALUES
(1, 1, 'tanjim_traveler'),
(2, 2, 'galib123');

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE `message` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `sender_username` varchar(100) NOT NULL,
  `message_text` text NOT NULL,
  `seen_status` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message`
--

INSERT INTO `message` (`id`, `room_id`, `sender_username`, `message_text`, `seen_status`, `created_at`) VALUES
(1, 1, 'tanjim_traveler', 'Hey Galib! Welcome to the Sylhet Tour chat room!', 1, '2026-10-10 15:13:12'),
(2, 1, 'galib', 'Thanks Tanjim! Excited to be joining!', 1, '2026-10-10 15:13:12'),
(3, 1, 'tanjim_traveler', 'Hey Galib! Welcome to the Sylhet Tour chat room!', 1, '2026-10-10 15:13:29'),
(4, 1, 'galib', 'Thanks Tanjim! Excited to be joining!', 1, '2026-10-10 15:13:29');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset`
--

CREATE TABLE `password_reset` (
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset`
--

INSERT INTO `password_reset` (`email`, `otp`, `expires_at`) VALUES
('abdmgalib2001@gmail.com', '882945', '2026-10-10 21:36:40');

-- --------------------------------------------------------

--
-- Table structure for table `trip_comment`
--

CREATE TABLE `trip_comment` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trip_comment`
--

INSERT INTO `trip_comment` (`id`, `trip_id`, `username`, `parent_id`, `comment_text`, `created_at`) VALUES
(1, 4, 'galib', NULL, 'Hey Tanjim, what will be the weather like in Sylhet during November?', '2026-10-10 13:54:35'),
(2, 4, 'tanjim_traveler', 1, 'November in Sylhet is pleasant and mild! Perfect for boat rides.', '2026-10-10 13:54:35'),
(3, 4, 'galib', NULL, 'Testing comment from Galib token via automated test!', '2026-10-10 13:57:39'),
(4, 4, 'galib', 3, 'Automated reply to comment ID 3!', '2026-10-10 13:57:39'),
(5, 4, 'new_traveler', NULL, 'How many days will we spend in Jaflong?', '2026-10-10 15:31:45'),
(6, 4, 'new_traveler', 1, 'We will spend 2 full days in Jaflong!', '2026-10-10 15:31:46');

-- --------------------------------------------------------

--
-- Table structure for table `trip_info`
--

CREATE TABLE `trip_info` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `host_username` varchar(100) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `start_day` date NOT NULL,
  `end_day` date NOT NULL,
  `budget` decimal(10,2) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `transport_method` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `total_seats` int(11) NOT NULL,
  `available_seats` int(11) NOT NULL,
  `cover_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trip_info`
--

INSERT INTO `trip_info` (`id`, `title`, `host_username`, `destination`, `start_day`, `end_day`, `budget`, `category`, `transport_method`, `description`, `total_seats`, `available_seats`, `cover_picture`) VALUES
(1, 'Sajek Valley Tour', 'galib', 'Sajek Valley, Rangamati', '2026-11-10', '2026-11-14', 6500.00, 'Adventure', 'Chander Gari', 'Exciting tour to Sajek Valley including clouds and hills view.', 8, 7, NULL),
(2, 'Cox\'s Bazar Beach Holiday', 'galib', 'Cox\'s Bazar', '2026-12-01', '2026-12-05', 12000.00, 'Relaxation', 'Air', 'Enjoy the longest sea beach in the world with sunset views and fresh seafood.', 5, 4, 'trip_galib_1791634299_6aca2b7bae64a.png'),
(3, 'Sajek Valley Tour', 'galib', 'Sajek Valley, Rangamati', '2026-11-10', '2026-11-14', 6500.00, 'Adventure', 'Chander Gari', 'Exciting tour to Sajek Valley including clouds and hills view.', 8, 8, NULL),
(4, 'Sylhet Ratargul & Jaflong Tour', 'tanjim_traveler', 'Sylhet, Bangladesh', '2026-11-20', '2026-11-23', 5500.00, 'adventure', 'Train & Boat', 'Explore the freshwater swamp forest Ratargul and clear waters of Jaflong with scenic beauty.', 6, 6, NULL),
(5, 'Bandarban Mountain Trekking', 'new_traveler', 'Bandarban, Bangladesh', '2026-11-25', '2026-11-29', 7500.00, 'adventure', 'Chander Gari', 'Trekking to Nilgiri and Nafakhum waterfall.', 10, 10, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `trip_request`
--

CREATE TABLE `trip_request` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trip_request`
--

INSERT INTO `trip_request` (`id`, `trip_id`, `username`, `status`, `created_at`) VALUES
(1, 1, 'tanjim_traveler', 'accepted', '2026-10-10 12:56:19'),
(2, 2, 'galib123', 'accepted', '2026-10-10 12:57:03'),
(3, 4, 'galib', 'pending', '2026-10-10 13:02:57'),
(4, 3, 'tanjim_traveler', 'pending', '2026-10-10 15:17:47'),
(5, 4, 'new_traveler', 'pending', '2026-10-10 15:31:45');

-- --------------------------------------------------------

--
-- Table structure for table `user_info`
--

CREATE TABLE `user_info` (
  `username` varchar(100) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `nid_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_info`
--

INSERT INTO `user_info` (`username`, `full_name`, `email`, `gender`, `country`, `dob`, `profile_pic`, `nid_pic`) VALUES
('galib', 'Abdullah Mohd. Galib', 'abdmgalib2001@gmail.com', 'Male', 'Bangladesh', '2000-07-01', 'galib_profile_1791638858_6aca3d4a1ed07.png', 'galib_nid_1791612369_6ac9d5d1dd131.jpg'),
('galib123', 'Abd M Galib', 'galib@example.com', 'Male', 'Bangladesh', '1998-05-15', 'galib123_profile_1791610847_6ac9cfdfae519.jpg', 'galib123_nid_1791610847_6ac9cfdfae6bb.jpg'),
('new_traveler', 'Abdullah M Galib', 'john@example.com', 'Male', 'Bangladesh', '2000-07-01', NULL, NULL),
('tanjim_traveler', 'Tanjim Ahmed', 'tanjim.travel@gmail.com', 'Male', 'Bangladesh', '2000-08-20', 'tanjim_traveler_profile_1791611581_6ac9d2bd2b6fd.jpg', 'tanjim_traveler_nid_1791611581_6ac9d2bd2b89d.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `user_login`
--

CREATE TABLE `user_login` (
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_login`
--

INSERT INTO `user_login` (`username`, `password`) VALUES
('galib', '$2y$10$XstmceTIOhJsk2lTr52hY.Po4Sf3a51lmlgDLPRL02IEEzswnOhRu'),
('galib123', '$2y$10$Ez9Fq17Q7D0HGZ0R8450Bu9jVeRaqKBnh27/7BinF07D7LS2oTEsC'),
('new_traveler', '$2y$10$kZj2MubWwWCfEzmtvz1.6.MATvCjLizkoGK3bYnCdR6HnXOvw.Mi6'),
('tanjim_traveler', '$2y$10$GsYjk9Lx8yhXoE0nTmrBWOJpmkgaapNN/yyUMyfwxoZSG9Z3J1RYm');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookmark`
--
ALTER TABLE `bookmark`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`,`trip_id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `chat_room`
--
ALTER TABLE `chat_room`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `trip_id` (`trip_id`,`username`,`host_username`),
  ADD KEY `username` (`username`),
  ADD KEY `host_username` (`host_username`);

--
-- Indexes for table `confirm_trip`
--
ALTER TABLE `confirm_trip`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `sender_username` (`sender_username`);

--
-- Indexes for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `trip_comment`
--
ALTER TABLE `trip_comment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `username` (`username`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `trip_info`
--
ALTER TABLE `trip_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `host_username` (`host_username`);

--
-- Indexes for table `trip_request`
--
ALTER TABLE `trip_request`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`trip_id`,`username`),
  ADD KEY `trip_req_fk_2` (`username`);

--
-- Indexes for table `user_info`
--
ALTER TABLE `user_info`
  ADD PRIMARY KEY (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_login`
--
ALTER TABLE `user_login`
  ADD PRIMARY KEY (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookmark`
--
ALTER TABLE `bookmark`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `chat_room`
--
ALTER TABLE `chat_room`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `confirm_trip`
--
ALTER TABLE `confirm_trip`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `message`
--
ALTER TABLE `message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `trip_comment`
--
ALTER TABLE `trip_comment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `trip_info`
--
ALTER TABLE `trip_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `trip_request`
--
ALTER TABLE `trip_request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookmark`
--
ALTER TABLE `bookmark`
  ADD CONSTRAINT `bookmark_ibfk_1` FOREIGN KEY (`username`) REFERENCES `user_info` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookmark_ibfk_2` FOREIGN KEY (`trip_id`) REFERENCES `trip_info` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_room`
--
ALTER TABLE `chat_room`
  ADD CONSTRAINT `chat_room_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trip_info` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_room_ibfk_2` FOREIGN KEY (`username`) REFERENCES `user_info` (`username`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_room_ibfk_3` FOREIGN KEY (`host_username`) REFERENCES `user_info` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `confirm_trip`
--
ALTER TABLE `confirm_trip`
  ADD CONSTRAINT `confirm_trip_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trip_info` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `confirm_trip_ibfk_2` FOREIGN KEY (`username`) REFERENCES `user_info` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `message`
--
ALTER TABLE `message`
  ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_room` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_ibfk_2` FOREIGN KEY (`sender_username`) REFERENCES `user_info` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `trip_comment`
--
ALTER TABLE `trip_comment`
  ADD CONSTRAINT `fk_comment_parent` FOREIGN KEY (`parent_id`) REFERENCES `trip_comment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comment_trip` FOREIGN KEY (`trip_id`) REFERENCES `trip_info` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (`username`) REFERENCES `user_info` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `trip_info`
--
ALTER TABLE `trip_info`
  ADD CONSTRAINT `trip_info_ibfk_1` FOREIGN KEY (`host_username`) REFERENCES `user_info` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `trip_request`
--
ALTER TABLE `trip_request`
  ADD CONSTRAINT `trip_req_fk_1` FOREIGN KEY (`trip_id`) REFERENCES `trip_info` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trip_req_fk_2` FOREIGN KEY (`username`) REFERENCES `user_info` (`username`) ON DELETE CASCADE;

--
-- Constraints for table `user_login`
--
ALTER TABLE `user_login`
  ADD CONSTRAINT `user_login_ibfk_1` FOREIGN KEY (`username`) REFERENCES `user_info` (`username`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
