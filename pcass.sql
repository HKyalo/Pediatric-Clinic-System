-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Jun 24, 2026 at 10:07 AM
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
-- Database: `pcass`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `full_name`, `email`, `password`, `created_at`) VALUES
(1, 'System Administrator', 'admin@pedialink.com', '$2y$10$SCKZDYdlvr/EtGSRdMdxie0pSDGyPXBFHgnsyBS6xrbFWgTXwk6EO', '2026-01-22 11:32:23');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('Pending','Completed','Cancelled','Missed') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `child_id`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `notes`, `created_at`) VALUES
(8, 5, 2, '2026-02-28', '10:30:00', 'Cancelled', '', '2026-02-09 14:53:22'),
(9, 5, 2, '2026-02-12', '10:30:00', 'Completed', '', '2026-02-11 17:24:40'),
(10, 8, 2, '2026-02-17', '09:30:00', 'Completed', '', '2026-02-15 10:19:42'),
(11, 8, 2, '2026-02-16', '08:00:00', 'Missed', '', '2026-02-15 10:32:52'),
(12, 8, 1, '2026-02-16', '07:00:00', 'Missed', '', '2026-02-15 11:36:12'),
(13, 8, 1, '2026-02-28', '07:00:00', 'Missed', '', '2026-02-18 17:43:38'),
(14, 11, 1, '2026-02-21', '07:00:00', 'Missed', '', '2026-02-20 09:53:35'),
(15, 11, 3, '2026-02-22', '07:00:00', 'Completed', '', '2026-02-21 13:10:37'),
(16, 12, 3, '2026-02-23', '10:00:00', 'Missed', '', '2026-02-22 19:18:24'),
(17, 12, 3, '2026-02-25', '07:00:00', 'Missed', 'Routine immunization', '2026-02-24 17:09:42'),
(18, 13, 3, '2026-03-08', '08:30:00', 'Missed', '', '2026-03-07 15:25:38'),
(19, 13, 4, '2026-03-11', '14:30:00', 'Completed', '', '2026-03-11 08:45:33'),
(20, 13, 4, '2026-03-12', '16:00:00', 'Missed', '', '2026-03-11 12:59:27'),
(21, 13, 3, '2026-03-16', '07:00:00', 'Missed', '', '2026-03-15 17:44:47'),
(22, 14, 4, '2026-04-16', '10:00:00', 'Missed', '', '2026-04-15 13:10:46'),
(23, 13, 4, '2026-04-15', '07:00:00', 'Missed', '', '2026-04-15 19:05:35'),
(24, 13, 4, '2026-04-18', '07:00:00', 'Missed', '', '2026-04-15 20:26:41'),
(25, 15, 4, '2026-04-17', '08:30:00', 'Missed', '', '2026-04-16 12:16:46'),
(26, 15, 4, '2026-04-22', '13:00:00', 'Completed', '', '2026-04-22 09:14:55'),
(27, 11, 4, '2026-04-22', '14:30:00', 'Missed', '', '2026-04-22 10:55:08'),
(28, 11, 1, '2026-04-25', '07:00:00', 'Missed', 'Referral from immunization doctor. Flag ID: 7', '2026-04-23 13:45:47'),
(29, 16, 4, '2026-04-23', '17:30:00', 'Completed', '', '2026-04-23 14:02:22'),
(30, 16, 1, '2026-04-23', '17:30:00', 'Completed', 'Referral from immunization doctor. Flag ID: 9', '2026-04-23 14:12:04'),
(31, 16, 1, '2026-04-27', '14:30:00', 'Completed', 'follow-up appointment', '2026-04-27 10:31:09'),
(32, 15, 1, '2026-04-27', '16:00:00', 'Completed', '', '2026-04-27 11:43:32'),
(33, 16, 4, '2026-04-28', '14:30:00', 'Completed', '', '2026-04-28 10:58:12'),
(34, 16, 3, '2026-04-29', '14:30:00', 'Missed', '', '2026-04-29 11:22:29'),
(35, 17, 4, '2026-06-13', '14:30:00', 'Completed', '', '2026-06-13 11:04:00'),
(36, 18, 3, '2026-06-13', '17:30:00', 'Completed', '', '2026-06-13 13:17:53'),
(37, 19, 4, '2026-06-14', '14:30:00', 'Pending', '', '2026-06-14 10:55:11');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_slots`
--

CREATE TABLE `blocked_slots` (
  `block_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `block_date` date NOT NULL,
  `block_time` time NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blocked_slots`
--

INSERT INTO `blocked_slots` (`block_id`, `doctor_id`, `block_date`, `block_time`, `reason`, `created_at`) VALUES
(2, 2, '2026-03-08', '07:00:00', 'Personal', '2026-03-07 16:34:21');

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `child_id` int(11) NOT NULL,
  `guardian_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `children`
--

INSERT INTO `children` (`child_id`, `guardian_id`, `first_name`, `last_name`, `gender`, `date_of_birth`, `created_at`) VALUES
(2, 4, 'Liam', 'Njenga', 'Male', '2024-02-14', '2026-01-22 09:44:28'),
(3, 5, 'Grace', 'Wairimu', 'Female', '2025-11-26', '2026-01-25 12:45:38'),
(4, 7, 'Wendy', 'Cynthia', 'Female', '2024-12-02', '2026-01-26 13:09:08'),
(5, 8, 'harriet', 'mumbua', 'Female', '2021-02-25', '2026-01-26 17:52:59'),
(6, 7, 'Rachel', 'Wairimu', 'Female', '2026-05-16', '2026-02-09 14:44:30'),
(7, 9, 'Lloyd', 'Mumo', 'Male', '2022-07-07', '2026-02-14 09:45:59'),
(8, 9, 'Leo', 'Museo', 'Male', '2023-01-01', '2026-02-14 09:51:23'),
(9, 10, 'Leah', 'Njoki', 'Female', '2022-04-01', '2026-02-17 20:06:27'),
(10, 11, 'Barbra', 'Wangui', 'Female', '2021-08-20', '2026-02-18 17:21:08'),
(11, 9, 'David', 'Maina', 'Male', '2026-01-01', '2026-02-20 09:52:19'),
(12, 12, 'Leah', 'Nana', 'Female', '2026-02-15', '2026-02-22 18:06:35'),
(13, 13, 'Alex', 'Mutai', 'Male', '2026-03-07', '2026-03-07 15:23:47'),
(14, 14, 'Edwin', 'Musau', 'Male', '2026-04-08', '2026-04-15 13:09:14'),
(15, 16, 'Tracy', 'Ndirang\'u', 'Female', '2026-04-13', '2026-04-16 12:15:02'),
(16, 17, 'Eliana', 'Wambui', 'Female', '2026-04-22', '2026-04-23 14:01:36'),
(17, 18, 'Ryan', 'Kamau', 'Male', '2026-06-13', '2026-06-13 10:45:37'),
(18, 18, 'Nita', 'Kamau', 'Female', '2026-06-13', '2026-06-13 13:13:04'),
(19, 19, 'Mumbua', 'Kyalo', 'Female', '2026-06-12', '2026-06-14 10:53:10'),
(20, 20, 'Sheryl', 'Kamau', 'Female', '2026-06-15', '2026-06-15 07:17:45');

-- --------------------------------------------------------

--
-- Table structure for table `child_milestones`
--

CREATE TABLE `child_milestones` (
  `id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `milestone_number` int(11) NOT NULL,
  `achieved` tinyint(1) DEFAULT 0,
  `date_achieved` date DEFAULT NULL,
  `age_months` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `child_teeth`
--

CREATE TABLE `child_teeth` (
  `id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `tooth_id` int(11) NOT NULL,
  `emerged_date` date DEFAULT NULL,
  `age_months` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `doctor_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `years_of_experience` int(11) DEFAULT 0,
  `employment_type` enum('Full-Time','Part-Time','Contract','Visiting') DEFAULT 'Full-Time',
  `status` enum('Active','Inactive','On Leave') DEFAULT 'Active',
  `doctor_role` enum('immunization','specialist') DEFAULT 'immunization',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`doctor_id`, `full_name`, `email`, `phone`, `specialization`, `license_number`, `qualification`, `years_of_experience`, `employment_type`, `status`, `doctor_role`, `password`, `created_at`) VALUES
(1, 'Mary Kamau', 'kamau@gmail.com', '0734267723', 'General Pediatrics', 'MP-LK675', 'PhD', 5, 'Full-Time', 'Active', 'specialist', '$2y$10$JA/YrN9H4.3afuBgUGXP.uWDveiqyrZJjQwvKA0bQR45SHuArOW2.', '2026-01-27 07:36:24'),
(2, 'Chris Kiprono', 'ckiprono@gmail.com', '0722367543', 'Pediatric Cardiology', 'MP-LK555', 'PhD', 12, 'Part-Time', 'Active', 'specialist', '$2y$10$Lho6VsehCpKoc62cCYko3OVdpWXfeGOhNlt/cgElxhSnLaYatYeBe', '2026-02-09 14:51:37'),
(3, 'Karen Christine', 'christine@gmail.com', '0786455433', 'General Pediatrics', 'MP-LK756', 'MbSc', 2, 'Full-Time', 'Active', 'immunization', '$2y$10$5ypidC9CkDqZ7vMvfNxpXuUpDl1eIj1Llx1Qg4HBdD4jeR4EWek1a', '2026-02-21 12:45:34'),
(4, 'Angela Nduta', 'nduta@gmail.com', '0722456334', 'General Pediatrics', 'MP-LK999', '', 6, 'Full-Time', 'Active', 'immunization', '$2y$10$OQJT8MHd5BqWE0yQBESBXeZ3FiZRqpo0WcJV0z3o517wJ1GGs0TRe', '2026-03-07 16:41:01');

-- --------------------------------------------------------

--
-- Table structure for table `flags`
--

CREATE TABLE `flags` (
  `flag_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `flagged_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `flag_type` enum('growth','milestone','vaccine','multiple','other') DEFAULT 'other',
  `reason` text NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('new','in_review','resolved') DEFAULT 'new',
  `created_at` datetime DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `flags`
--

INSERT INTO `flags` (`flag_id`, `child_id`, `flagged_by`, `assigned_to`, `flag_type`, `reason`, `notes`, `status`, `created_at`, `resolved_at`, `resolved_by`) VALUES
(1, 11, 3, 1, 'other', 'nutrition- Child isnt breastfeeding as required', '', 'new', '2026-02-22 17:21:19', NULL, NULL),
(2, 12, 3, 1, 'vaccine', 'BCG and Polio first dose not given at birth', '', 'new', '2026-03-07 18:59:14', NULL, NULL),
(3, 11, 4, 1, 'vaccine', 'delays of administering some vaccines', '', 'new', '2026-04-22 13:57:41', NULL, NULL),
(4, 11, 4, 1, 'growth', 'weight drop', '', 'new', '2026-04-22 16:52:25', NULL, NULL),
(5, 11, 4, 1, 'growth', 'weight drop', '', 'new', '2026-04-23 16:26:13', NULL, NULL),
(6, 11, 4, 1, 'growth', 'weight drop', '', 'new', '2026-04-23 16:33:23', NULL, NULL),
(7, 11, 4, 1, 'growth', 'weight drop', '', 'new', '2026-04-23 16:45:29', NULL, NULL),
(8, 16, 4, 1, 'other', 'signs of jaundice', 'Eliana presents some symptoms pointing to Jaundice: yellowness of eyes', 'new', '2026-04-23 17:05:51', NULL, NULL),
(9, 16, 4, 1, 'other', 'signs of jaundice', 'Eliana presents symptoms of jaundice: yellowness of the eyes', 'new', '2026-04-23 17:11:46', NULL, NULL),
(10, 16, 4, 1, 'growth', 'xxxxxx', 'xxxxxxxx', 'new', '2026-04-28 14:19:33', NULL, NULL),
(11, 13, 4, 2, 'growth', 'xxx', 'xxxxx', 'new', '2026-04-28 16:53:20', NULL, NULL),
(12, 13, 4, 2, 'growth', 'xxx', 'xxxxx', 'new', '2026-04-28 17:26:54', NULL, NULL),
(13, 11, 4, 1, 'growth', 'xxxxxx', 'xxxx', 'new', '2026-04-28 17:27:20', NULL, NULL),
(14, 14, 4, 1, 'vaccine', 'Severe reaction to previous DPT vaccine', 'Child developed high fever (40°C) and inconsolable crying for 48 hours after last vaccine dose. Mother is anxious about continuing vaccinations. Need specialist review before next dose.', 'new', '2026-06-13 16:04:22', NULL, NULL),
(15, 18, 3, 1, 'growth', 'Microcephaly suspected - small head circumference at birth', 'Newborn infant with head circumference measuring 26cm at birth (below 3rd percentile for gestational age). Weight and height are within normal range. No obvious dysmorphic features noted.', 'new', '2026-06-13 16:30:19', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `growth_records`
--

CREATE TABLE `growth_records` (
  `growth_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `height_cm` decimal(5,1) DEFAULT NULL,
  `head_circumference` decimal(4,1) DEFAULT NULL,
  `bmi` decimal(4,1) DEFAULT NULL,
  `milestone_notes` text DEFAULT NULL,
  `weight_percentile` varchar(10) DEFAULT NULL,
  `height_percentile` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `growth_records`
--

INSERT INTO `growth_records` (`growth_id`, `child_id`, `doctor_id`, `record_date`, `weight_kg`, `height_cm`, `head_circumference`, `bmi`, `milestone_notes`, `weight_percentile`, `height_percentile`, `notes`, `created_at`) VALUES
(1, 11, 3, '2026-02-22', 4.20, 52.0, 36.8, NULL, NULL, NULL, NULL, '', '2026-02-22 12:12:22'),
(2, 15, 4, '2026-04-22', 4.20, 49.8, 33.9, NULL, NULL, NULL, NULL, '', '2026-04-22 09:18:56'),
(3, 16, 4, '2026-04-23', 3.20, 45.0, 35.0, NULL, NULL, NULL, NULL, '', '2026-04-23 14:03:52'),
(4, 16, 4, '2026-04-28', 3.50, 45.0, 35.9, NULL, NULL, NULL, NULL, '', '2026-04-28 11:13:58'),
(5, 17, 4, '2026-06-13', 3.20, 48.0, 34.0, NULL, NULL, NULL, NULL, '', '2026-06-13 11:07:36'),
(6, 18, 3, '2026-06-13', 3.10, 46.0, 25.0, NULL, NULL, NULL, NULL, '', '2026-06-13 13:27:52');

-- --------------------------------------------------------

--
-- Table structure for table `guardians`
--

CREATE TABLE `guardians` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `login_email_type` varchar(50) DEFAULT NULL,
  `mother_name` varchar(255) DEFAULT NULL,
  `mother_email` varchar(255) DEFAULT NULL,
  `mother_phone` varchar(20) DEFAULT NULL,
  `father_name` varchar(255) DEFAULT NULL,
  `father_email` varchar(255) DEFAULT NULL,
  `father_phone` varchar(20) DEFAULT NULL,
  `guardian_name` varchar(255) DEFAULT NULL,
  `guardian_relationship` varchar(100) DEFAULT NULL,
  `guardian_email` varchar(255) DEFAULT NULL,
  `guardian_phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guardians`
--

INSERT INTO `guardians` (`id`, `name`, `email`, `password`, `created_at`, `login_email_type`, `mother_name`, `mother_email`, `mother_phone`, `father_name`, `father_email`, `father_phone`, `guardian_name`, `guardian_relationship`, `guardian_email`, `guardian_phone`) VALUES
(4, 'Grace Wairimu', 'Wairimu@gmail.com', '$2y$10$Fgi0mGQDlxKeYR1n/eAEduLaaqE78synhGgLp7iDE9FiFIaxAtFo.', '2026-01-27 07:10:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Harriet Mumbua', 'Mumbua@gmail.com', '$2y$10$m8FK2hL.PIrUdjhSVcBzgOns1jRlygwz5h8etDN4mIqrCIkdscuoK', '2026-01-27 07:10:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Faith Njoki', 'faithnjoki@gmail.com', '$2y$10$.MhrFDZ/YuJflVnG6vsLgeFcpJvWYbhJsQVx3fn6EEq9.smYMbudC', '2026-01-27 07:10:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'Teresia Njoroge', 'njoroge@gmail.com', '$2y$10$1oKQzpyRgXOXnOSu0jXgQO5pbJcgJ4jL/ZMMwNNnJ9WZsD9RGlAr2', '2026-01-27 07:10:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'Faith Mulandi', 'fmulandi@gmail.com', '$2y$10$CYVK2zW8HKIvGnun6mt7EOzzWORJ6Y9oH/va9AlHBdGAY9WxOSggq', '2026-02-14 09:41:00', 'mother', 'Faith Mulandi', 'fmulandi@gmail.com', '+254723456654', 'Timothy Musau', 'Tmusau@gmail.com', '+254734567896', '', '', '', ''),
(10, 'Peter Kimani', 'pkimani@gmail.com', '$2y$10$Q.rRa/bA9F21DdUHltxXceTWjODtMNo3HDf4egmF98O8pE4gXbEi2', '2026-02-17 20:05:20', 'father', 'Christine Njeri', 'njeri@gmail.com', '0756435255', 'Peter Kimani', 'pkimani@gmail.com', '0787645678', '', '', '', ''),
(11, 'Mary Lillian', 'lillian@gmail.com', '$2y$10$PbRmQFn5mpo3H2J2BiA5teNKFme5f6Kx151FoaZN.qVXk3jkNolEa', '2026-02-18 17:11:05', 'mother', 'Mary Lillian', 'lillian@gmail.com', '0756345543', '', '', '', '', '', NULL, ''),
(12, 'Sarah Grace', 'grace@gmail.com', '$2y$10$9CtjlQpvs2B3xZ79qUMEeujuAvuvIqj8czLDLXzhsauDAvrvB8dXu', '2026-02-22 17:57:53', 'guardian', '', '', '', '', '', '', 'Sarah Grace', 'Aunt', 'grace@gmail.com', '0722456765'),
(13, 'Nancy Makena', 'nancymakena@gmail.com', '$2y$10$NsYijQo/2DlCbZKkC.7e6OesAZSLGLMkI7WYF/uqgHGeQgqiWFiay', '2026-03-07 15:23:18', 'mother', 'Nancy Makena', 'nancymakena@gmail.com', '+254734569872', 'Joseph Mutai', 'jmutai@gmail.com', '+254722345876', '', '', '', ''),
(14, 'Teresia Ngonyo', 'ngonyo@gmail.com', '$2y$10$RQA6v/Vm18b0X9h1Usw0TemJjlviCIX4dk4hNrghVRwMLx7lNOQk2', '2026-04-15 13:08:44', 'mother', 'Teresia Ngonyo', 'ngonyo@gmail.com', '0723578954', 'Festus Musau', 'musau@gmail.com', '0723872893', '', '', '', 'fmulandi@gmail.com'),
(15, 'Elizabeth Wanjiru', 'wanjiru@gmail.com', '$2y$10$5PrXiz4SXJOmGBCb2vWOyeOGs6KM6fORtmGwN59/K98DwO4gaccWW', '2026-04-16 06:44:25', 'guardian', '', '', '', '', '', '', 'Elizabeth Wanjiru', 'Aunt', 'wanjiru@gmail.com', '0723456654'),
(16, 'Hope Chepkorir', 'chep@gmail.com', '$2y$10$9WHqB90jNAv6GyBN6Br2cuK379QpOSsi/rmMf/z/9WaQQQLxP979O', '2026-04-16 12:14:05', 'mother', 'Hope Chepkorir', 'chep@gmail.com', '0733786657', 'James Ndirang\'u', 'ndirangu@gmail.com', '0722453654', '', '', '', 'admin@pedialink.com'),
(17, 'Ann Wanjiru', 'ann@gmail.com', '$2y$10$U64KUMqeXfB7bRgSDRfxvO.VgicKkN6p9pjDwBhhVhuGUWidGHvJC', '2026-04-23 14:01:05', 'mother', 'Ann Wanjiru', 'ann@gmail.com', '0745336782', 'Simon Kinyanjui', 'simon@gmail.com', '0723453998', '', '', '', 'nduta@gmail.com'),
(18, 'Christine Njeri', 'njeri@gmail.com', '$2y$10$OwjtBh4OQELj2Gx3Xk87uu49DqJbHTMYIWbkgUXyRl3bl/mVLkEpa', '2026-06-13 10:44:56', 'mother', 'Christine Njeri', 'njeri@gmail.com', '0722453345', 'Mark Kamau', 'kamau@gmail.com', '0725778657', '', '', '', 'ann@gmail.com'),
(19, 'Edith Wambui', 'wambui@gmail.com', '$2y$10$ytCt/cV8mtZdxgs2z.Gi..cZrRoB0gBIO/gB2xjQFtlxsqjj4luoG', '2026-06-14 10:52:27', 'mother', 'Edith Wambui', 'wambui@gmail.com', '0723455566', '', '', '', '', '', '', 'njeri@gmail.com'),
(20, 'Anna Wanjiru', 'anna@gmail.com', '$2y$10$m61RP/N0s4G.4punJ8AxwOzP3tUTkwYpsMo/M7FiLDl9KJBDp3UQq', '2026-06-15 07:17:18', 'mother', 'Anna Wanjiru', 'anna@gmail.com', '0723113387', '', '', '', '', '', '', 'njeri@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `lab_results`
--

CREATE TABLE `lab_results` (
  `lab_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `review_id` int(11) DEFAULT NULL,
  `test_name` varchar(255) NOT NULL,
  `test_date` date NOT NULL,
  `result_value` varchar(500) DEFAULT NULL,
  `result_summary` text DEFAULT NULL,
  `interpretation` varchar(20) DEFAULT 'Pending',
  `attachment` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_results`
--

INSERT INTO `lab_results` (`lab_id`, `child_id`, `doctor_id`, `review_id`, `test_name`, `test_date`, `result_value`, `result_summary`, `interpretation`, `attachment`, `created_at`) VALUES
(1, 16, 1, NULL, 'Total Serum Bilirubin', '2026-04-23', '12.5 mg/dL', 'Elevated bilirubin levels indicating neonatal jaundice. Patient presenting with yellow discoloration of eyes and skin.', 'Pending', NULL, '2026-04-23 14:20:56'),
(2, 16, 1, NULL, 'Direct Bilirubin', '2026-04-23', '0.8 mg/dL', 'Normal. Ruling out conjugated hyperbilirubinemia.', 'Pending', NULL, '2026-04-23 14:20:56'),
(3, 16, 1, 6, 'none', '2026-04-27', '', '', 'Pending', NULL, '2026-04-27 10:35:57'),
(4, 15, 1, 7, 'none', '2026-04-27', '', '', 'Pending', NULL, '2026-04-27 11:44:44'),
(5, 15, 1, 7, 'none', '2026-04-27', '', '', 'Pending', NULL, '2026-04-27 11:50:43');

-- --------------------------------------------------------

--
-- Table structure for table `milestone_definitions`
--

CREATE TABLE `milestone_definitions` (
  `milestone_number` int(11) NOT NULL,
  `category` enum('social','motor','language','cognitive') NOT NULL,
  `description` text NOT NULL,
  `expected_age_range` varchar(50) NOT NULL,
  `expected_age_min` int(11) DEFAULT NULL,
  `expected_age_max` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `milestone_definitions`
--

INSERT INTO `milestone_definitions` (`milestone_number`, `category`, `description`, `expected_age_range`, `expected_age_min`, `expected_age_max`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'social', 'Social smile / follows a colourful object dangled before their eyes', '0-2 months', 0, 2, 1, 1, '2026-02-22 15:12:16'),
(2, 'motor', 'Holds head upright / follows object or face with eyes / turns head to sound / smiles when spoken to', '2-4 months', 2, 4, 2, 1, '2026-02-22 15:12:16'),
(3, 'motor', 'Rolls over / reaches for and grasps objects / takes objects to mouth / babbles', '4-6 months', 4, 6, 3, 1, '2026-02-22 15:12:16'),
(4, 'motor', 'Sits without support / moves object from one hand to the other / repeats syllables (bababa, mamama)', '6-9 months', 6, 9, 4, 1, '2026-02-22 15:12:16'),
(5, 'motor', 'Takes steps with support / picks up small object with 2 fingers / says 2-3 words / imitates simple gestures', '9-12 months', 9, 12, 5, 1, '2026-02-22 15:12:16'),
(6, 'motor', 'Walks without support / drinks from a cup / says 7-10 words / points to body parts on request', '12-18 months', 12, 18, 6, 1, '2026-02-22 15:12:16'),
(7, 'language', 'Kicks a ball / builds tower with 3 blocks / points at pictures on request / speaks in short sentences', '18-24 months', 18, 24, 7, 1, '2026-02-22 15:12:16'),
(8, 'cognitive', 'Jumps / undresses and dresses self / says name, tells short story / interested in playing with other children', '24+ months', 24, 36, 8, 1, '2026-02-22 15:12:16');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `guardian_id` int(11) NOT NULL,
  `child_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `guardian_id`, `child_id`, `notification_type`, `title`, `message`, `related_id`, `is_read`, `created_at`) VALUES
(1, 9, 8, 'appointment_reminder', '📅 Appointment in 2 days', 'Leo has an appointment with Dr. Chris Kiprono on Tuesday, February 17, 2026 at 9:30 AM', 10, 1, '2026-02-15 15:31:01'),
(2, 9, 8, 'appointment_reminder', '⚠️ Appointment Tomorrow', 'Leo has an appointment with Dr. Chris Kiprono on Monday, February 16, 2026 at 8:00 AM', 11, 1, '2026-02-15 15:31:01'),
(3, 9, 8, 'appointment_reminder', '⚠️ Appointment Tomorrow', 'Leo has an appointment with Dr. Mary Kamau on Monday, February 16, 2026 at 7:00 AM', 12, 1, '2026-02-15 15:31:02'),
(4, 9, 8, 'appointment_reminder', '⚠️ Appointment TODAY!', 'Leo has an appointment with Dr. Chris Kiprono on Tuesday, February 17, 2026 at 9:30 AM', 10, 1, '2026-02-17 16:25:04'),
(5, 12, 12, 'appointment_reminder', '🟡 TOMORROW: Appointment with Immunization Doctor', 'Leah has an appointment with Dr. Karen Christine 💉 on Wednesday, February 25, 2026 at 7:00 AM', 17, 0, '2026-02-24 17:09:47'),
(6, 13, 13, 'appointment_reminder', '🟡 TOMORROW: Appointment with Immunization Doctor', 'Alex has an appointment with Dr. Karen Christine 💉 on Sunday, March 8, 2026 at 8:30 AM', 18, 1, '2026-03-07 15:26:09'),
(7, 13, 13, 'vaccine_reminder', 'Vaccine Due: BCG', 'BCG (Dose 1) is due now. Recommended by Mar 07, 2026.', 1, 1, '2026-03-07 15:32:14'),
(8, 13, 13, 'vaccine_reminder', 'Vaccine Due: OPV 0', 'OPV 0 (Dose 0) is due now. Recommended by Mar 07, 2026.', 2, 1, '2026-03-07 15:32:14'),
(9, 12, 12, 'flag', 'Vaccine-Related Concern for Leah Nana', 'During an immunization checkup, our doctor has identified that Leah Nana needs to be reviewed by a specialist.\n\nReason: BCG and Polio first dose not given at birth\nA specialist will review your child\'s case and contact you soon.', 2, 0, '2026-03-07 15:59:14'),
(10, 13, 13, 'appointment_reminder', 'In 2 Days: Appointment with Immunization Doctor', 'Alex has an appointment with Dr. Angela Nduta on Friday, March 13, 2026 at 10:00 AM', 19, 1, '2026-03-11 08:47:08'),
(11, 13, 13, 'appointment_reminder', 'Tomorrow: Appointment with Immunization Doctor', 'Alex has an appointment with Dr. Angela Nduta on Thursday, March 12, 2026 at 4:00 PM', 20, 1, '2026-03-11 12:59:33'),
(12, 13, 13, 'appointment_reminder', 'Tomorrow: Appointment with Immunization Doctor', 'Alex has an appointment with Dr. Karen Christine on Monday, March 16, 2026 at 7:00 AM', 21, 1, '2026-03-15 17:45:07'),
(13, 13, 13, 'appointment_reminder', 'Tomorrow: Appointment with Immunization Doctor', 'Alex has an appointment with Dr. Angela Nduta on Thursday, April 16, 2026 at 2:30 PM', 23, 1, '2026-04-15 19:06:48'),
(14, 13, 13, 'vaccine_reminder', 'Vaccine Due Soon: OPV 1', 'Alex Mutai needs OPV 1 (Dose 1). Due by Apr 18, 2026.', 3, 1, '2026-04-15 19:35:50'),
(15, 13, 13, 'vaccine_reminder', 'Vaccine Due Soon: DPT-HepB-Hib 1 (Pentavalent 1)', 'Alex Mutai needs DPT-HepB-Hib 1 (Pentavalent 1) (Dose 1). Due by Apr 18, 2026.', 4, 1, '2026-04-15 19:35:51'),
(16, 13, 13, 'vaccine_reminder', 'Vaccine Due Soon: PCV 1', 'Alex Mutai needs PCV 1 (Dose 1). Due by Apr 18, 2026.', 5, 1, '2026-04-15 19:35:51'),
(17, 13, 13, 'vaccine_reminder', 'Vaccine Due Soon: Rotavirus 1', 'Alex Mutai needs Rotavirus 1 (Dose 1). Due by Apr 18, 2026.', 6, 1, '2026-04-15 19:35:51'),
(18, 13, 13, 'appointment_rescheduled', 'Appointment Rescheduled', 'Alex Mutai\'s appointment with Dr. Angela Nduta has been rescheduled to Wednesday, April 15, 2026 at 7:00 AM.', 23, 1, '2026-04-15 19:57:56'),
(19, 13, 13, 'appointment_confirmation', 'Appointment Confirmed', 'Alex Mutai has an appointment with Dr. Angela Nduta on Saturday, April 18, 2026 at 7:00 AM.', 24, 1, '2026-04-15 20:26:41'),
(20, 14, 14, 'appointment_reminder', 'Today: Appointment with Immunization Doctor', 'Edwin has an appointment with Dr. Angela Nduta on Thursday, April 16, 2026 at 10:00 AM', 22, 1, '2026-04-16 07:22:39'),
(21, 14, 14, 'vaccine_reminder', 'Vaccine Overdue: BCG', 'Edwin Musau needs BCG (Dose 1). Due by Apr 08, 2026.', 1, 1, '2026-04-16 07:22:39'),
(22, 14, 14, 'vaccine_reminder', 'Vaccine Overdue: OPV 0', 'Edwin Musau needs OPV 0 (Dose 0). Due by Apr 08, 2026.', 2, 1, '2026-04-16 07:22:39'),
(23, 16, 15, 'vaccine_reminder', 'Vaccine Overdue: BCG', 'Tracy Ndirang\'u needs BCG (Dose 1). Due by Apr 13, 2026.', 1, 1, '2026-04-16 12:15:41'),
(24, 16, 15, 'vaccine_reminder', 'Vaccine Overdue: OPV 0', 'Tracy Ndirang\'u needs OPV 0 (Dose 0). Due by Apr 13, 2026.', 2, 1, '2026-04-16 12:15:41'),
(25, 16, 15, 'appointment_confirmation', 'Appointment Confirmed', 'Tracy Ndirang\'u has an appointment with Dr. Angela Nduta on Friday, April 17, 2026 at 8:30 AM.', 25, 1, '2026-04-16 12:16:46'),
(26, 16, 15, 'appointment_reminder', 'Tomorrow: Appointment with Immunization Doctor', 'Tracy has an appointment with Dr. Angela Nduta on Friday, April 17, 2026 at 8:30 AM', 25, 1, '2026-04-16 12:16:53'),
(27, 16, 15, 'appointment_confirmation', 'Appointment Confirmed', 'Tracy Ndirang\'u has an appointment with Dr. Angela Nduta on Wednesday, April 22, 2026 at 1:00 PM.', 26, 0, '2026-04-22 09:14:55'),
(28, 16, 15, 'appointment_reminder', 'Today: Appointment with Immunization Doctor', 'Tracy has an appointment with Dr. Angela Nduta on Wednesday, April 22, 2026 at 1:00 PM', 26, 0, '2026-04-22 09:14:57'),
(29, 9, 11, 'appointment_confirmation', 'Appointment Confirmed', 'David Maina has an appointment with Dr. Angela Nduta on Wednesday, April 22, 2026 at 2:30 PM.', 27, 0, '2026-04-22 10:55:08'),
(30, 9, 11, 'appointment_reminder', 'Today: Appointment with Immunization Doctor', 'David has an appointment with Dr. Angela Nduta on Wednesday, April 22, 2026 at 2:30 PM', 27, 0, '2026-04-22 10:55:13'),
(31, 9, 11, 'vaccine_reminder', 'Vaccine Overdue: OPV 2', 'David Maina needs OPV 2 (Dose 2). Due by Mar 12, 2026.', 7, 0, '2026-04-22 10:55:13'),
(32, 9, 11, 'vaccine_reminder', 'Vaccine Overdue: DPT-HepB-Hib 2 (Pentavalent 2)', 'David Maina needs DPT-HepB-Hib 2 (Pentavalent 2) (Dose 2). Due by Mar 12, 2026.', 8, 0, '2026-04-22 10:55:13'),
(33, 9, 11, 'vaccine_reminder', 'Vaccine Overdue: PCV 2', 'David Maina needs PCV 2 (Dose 2). Due by Mar 12, 2026.', 9, 0, '2026-04-22 10:55:13'),
(34, 9, 11, 'vaccine_reminder', 'Vaccine Overdue: Rotavirus 2', 'David Maina needs Rotavirus 2 (Dose 2). Due by Mar 12, 2026.', 10, 0, '2026-04-22 10:55:13'),
(35, 9, 11, 'vaccine_reminder', 'Vaccine Overdue: OPV 3', 'David Maina needs OPV 3 (Dose 3). Due by Apr 09, 2026.', 11, 0, '2026-04-22 10:55:13'),
(36, 9, 11, 'vaccine_reminder', 'Vaccine Overdue: DPT-HepB-Hib 3 (Pentavalent 3)', 'David Maina needs DPT-HepB-Hib 3 (Pentavalent 3) (Dose 3). Due by Apr 09, 2026.', 12, 0, '2026-04-22 10:55:13'),
(37, 9, 11, 'vaccine_reminder', 'Vaccine Overdue: PCV 3', 'David Maina needs PCV 3 (Dose 3). Due by Apr 09, 2026.', 13, 0, '2026-04-22 10:55:13'),
(38, 9, 11, 'vaccine_reminder', 'Vaccine Overdue: IPV', 'David Maina needs IPV (Dose 1). Due by Apr 09, 2026.', 14, 0, '2026-04-22 10:55:14'),
(39, 9, 11, 'flag', 'Vaccine-Related Concern for David Maina', 'During an immunization checkup, our doctor has identified that David Maina needs to be reviewed by a specialist.\n\nReason: delays of administering some vaccines\nA specialist will review your child\'s case', 3, 0, '2026-04-22 10:57:41'),
(40, 9, 11, 'flag', 'Growth Concern for David Maina', 'During an immunization checkup, our doctor has identified that David Maina needs to be reviewed by a specialist.\n\nReason: weight drop\nA specialist will review your child\'s case', 4, 0, '2026-04-22 13:52:25'),
(41, 9, 11, 'flag', 'Growth Concern for David Maina', 'During an immunization checkup, our doctor has identified that David Maina needs to be reviewed by a specialist.\n\nReason: weight drop\nA specialist will review your child\'s case', 5, 0, '2026-04-23 13:26:14'),
(42, 9, 11, 'flag', 'Growth Concern for David Maina', 'During an immunization checkup, our doctor has identified that David Maina needs to be reviewed by a specialist.\n\nReason: weight drop', 6, 0, '2026-04-23 13:33:24'),
(43, 9, 11, 'flag', 'Growth Concern for David Maina', 'During an immunization checkup, our doctor has identified that David Maina needs to be reviewed by a specialist.\n\nReason: weight drop', 7, 0, '2026-04-23 13:45:30'),
(44, 17, 16, 'appointment_confirmation', 'Appointment Confirmed', 'Eliana Wambui has an appointment with Dr. Angela Nduta on Thursday, April 23, 2026 at 5:30 PM.', 29, 0, '2026-04-23 14:02:22'),
(45, 17, 16, 'appointment_reminder', 'Today: Appointment with Immunization Doctor', 'Eliana has an appointment with Dr. Angela Nduta on Thursday, April 23, 2026 at 5:30 PM', 29, 0, '2026-04-23 14:02:24'),
(46, 17, 16, 'vaccine_reminder', 'Vaccine Overdue: BCG', 'Eliana Wambui needs BCG (Dose 1). Due by Apr 22, 2026.', 1, 0, '2026-04-23 14:02:24'),
(47, 17, 16, 'vaccine_reminder', 'Vaccine Overdue: OPV 0', 'Eliana Wambui needs OPV 0 (Dose 0). Due by Apr 22, 2026.', 2, 0, '2026-04-23 14:02:24'),
(48, 17, 16, 'flag', 'Other Concern for Eliana Wambui', 'During an immunization checkup, our doctor has identified that Eliana Wambui needs to be reviewed by a specialist.\n\nReason: signs of jaundice', 8, 0, '2026-04-23 14:05:51'),
(49, 17, 16, 'flag', 'Other Concern for Eliana Wambui', 'During an immunization checkup, our doctor has identified that Eliana Wambui needs to be reviewed by a specialist.\n\nReason: signs of jaundice', 9, 0, '2026-04-23 14:11:46'),
(50, 17, 16, 'appointment_reminder', 'Today: Appointment with Specialist Doctor', 'Eliana has an appointment with Dr. Mary Kamau on Thursday, April 23, 2026 at 5:30 PM', 30, 0, '2026-04-23 14:12:25'),
(51, 17, 16, 'appointment_confirmation', 'Appointment Confirmed', 'Eliana Wambui has an appointment with Dr. Mary Kamau on Monday, April 27, 2026 at 2:30 PM.', 31, 0, '2026-04-27 10:31:09'),
(52, 17, 16, 'appointment_reminder', 'Today: Appointment with Specialist Doctor', 'Eliana has an appointment with Dr. Mary Kamau on Monday, April 27, 2026 at 2:30 PM', 31, 0, '2026-04-27 10:31:15'),
(53, 16, 15, 'appointment_confirmation', 'Appointment Confirmed', 'Tracy Ndirang\'u has an appointment with Dr. Mary Kamau on Monday, April 27, 2026 at 4:00 PM.', 32, 0, '2026-04-27 11:43:32'),
(54, 17, 16, 'appointment_confirmation', 'Appointment Confirmed', 'Eliana Wambui has an appointment with Dr. Angela Nduta on Tuesday, April 28, 2026 at 2:30 PM.', 33, 0, '2026-04-28 10:58:13'),
(55, 17, 16, 'appointment_reminder', 'Today: Appointment with Immunization Doctor', 'Eliana has an appointment with Dr. Angela Nduta on Tuesday, April 28, 2026 at 2:30 PM', 33, 0, '2026-04-28 10:59:15'),
(56, 17, 16, 'flag', 'Growth Concern for Eliana Wambui', 'During an immunization checkup, our doctor has identified that Eliana Wambui needs to be reviewed by a specialist.\n\nReason: xxxxxx', 10, 1, '2026-04-28 11:19:33'),
(57, 13, 13, 'flag', 'Growth Concern for Alex Mutai', 'During an immunization checkup, our doctor has identified that Alex Mutai needs to be reviewed by a specialist.\n\nReason: xxx', 11, 0, '2026-04-28 13:53:20'),
(58, 13, 13, 'flag', 'Growth Concern for Alex Mutai', 'Our doctor has identified that Alex Mutai needs to be reviewed by a specialist.\n\nReason: xxx', 12, 0, '2026-04-28 14:26:54'),
(59, 9, 11, 'flag', 'Growth Concern for David Maina', 'Our doctor has identified that David Maina needs to be reviewed by a specialist.\n\nReason: xxxxxx', 13, 0, '2026-04-28 14:27:20'),
(60, 17, 16, 'appointment_confirmation', 'Appointment Confirmed', 'Eliana Wambui has an appointment with Dr. Karen Christine on Wednesday, April 29, 2026 at 2:30 PM.', 34, 0, '2026-04-29 11:22:29'),
(61, 17, 16, 'appointment_reminder', 'Today: Appointment with Immunization Doctor', 'Eliana has an appointment with Dr. Karen Christine on Wednesday, April 29, 2026 at 2:30 PM', 34, 1, '2026-04-29 11:22:35'),
(62, 18, 17, 'vaccine_reminder', 'Vaccine Overdue: BCG', 'Ryan Kamau needs BCG (Dose 1). Due by Jun 13, 2026.', 1, 1, '2026-06-13 10:46:24'),
(63, 18, 17, 'vaccine_reminder', 'Vaccine Overdue: OPV 0', 'Ryan Kamau needs OPV 0 (Dose 0). Due by Jun 13, 2026.', 2, 1, '2026-06-13 10:46:24'),
(64, 18, 17, 'appointment_confirmation', 'Appointment Confirmed', 'Ryan Kamau has an appointment with Dr. Angela Nduta on Saturday, June 13, 2026 at 2:30 PM.', 35, 1, '2026-06-13 11:04:00'),
(65, 18, 17, 'appointment_reminder', 'Today: Appointment with Immunization Doctor', 'Ryan has an appointment with Dr. Angela Nduta on Saturday, June 13, 2026 at 2:30 PM', 35, 1, '2026-06-13 11:06:08'),
(66, 14, 14, 'flag', 'Vaccine Concern for Edwin Musau', 'Our doctor has identified that Edwin Musau needs to be reviewed by a specialist.\n\nReason: Severe reaction to previous DPT vaccine', 14, 1, '2026-06-13 13:04:22'),
(67, 14, 14, 'vaccine_reminder', 'Vaccine Overdue: OPV 1', 'Edwin Musau needs OPV 1 (Dose 1). Due by May 20, 2026.', 3, 1, '2026-06-13 13:05:27'),
(68, 14, 14, 'vaccine_reminder', 'Vaccine Overdue: DPT-HepB-Hib 1 (Pentavalent 1)', 'Edwin Musau needs DPT-HepB-Hib 1 (Pentavalent 1) (Dose 1). Due by May 20, 2026.', 4, 1, '2026-06-13 13:05:27'),
(69, 14, 14, 'vaccine_reminder', 'Vaccine Overdue: PCV 1', 'Edwin Musau needs PCV 1 (Dose 1). Due by May 20, 2026.', 5, 1, '2026-06-13 13:05:27'),
(70, 14, 14, 'vaccine_reminder', 'Vaccine Overdue: Rotavirus 1', 'Edwin Musau needs Rotavirus 1 (Dose 1). Due by May 20, 2026.', 6, 1, '2026-06-13 13:05:28'),
(71, 14, 14, 'vaccine_reminder', 'Vaccine Due Soon: OPV 2', 'Edwin Musau needs OPV 2 (Dose 2). Due by Jun 17, 2026.', 7, 1, '2026-06-13 13:05:28'),
(72, 14, 14, 'vaccine_reminder', 'Vaccine Due Soon: DPT-HepB-Hib 2 (Pentavalent 2)', 'Edwin Musau needs DPT-HepB-Hib 2 (Pentavalent 2) (Dose 2). Due by Jun 17, 2026.', 8, 1, '2026-06-13 13:05:28'),
(73, 14, 14, 'vaccine_reminder', 'Vaccine Due Soon: PCV 2', 'Edwin Musau needs PCV 2 (Dose 2). Due by Jun 17, 2026.', 9, 1, '2026-06-13 13:05:28'),
(74, 14, 14, 'vaccine_reminder', 'Vaccine Due Soon: Rotavirus 2', 'Edwin Musau needs Rotavirus 2 (Dose 2). Due by Jun 17, 2026.', 10, 1, '2026-06-13 13:05:28'),
(75, 18, 18, 'appointment_confirmation', 'Appointment Confirmed', 'Nita Kamau has an appointment with Dr. Karen Christine on Saturday, June 13, 2026 at 5:30 PM.', 36, 1, '2026-06-13 13:17:54'),
(76, 18, 18, 'appointment_reminder', 'Today: Appointment with Immunization Doctor', 'Nita has an appointment with Dr. Karen Christine on Saturday, June 13, 2026 at 5:30 PM', 36, 1, '2026-06-13 13:17:56'),
(77, 18, 18, 'vaccine_reminder', 'Vaccine Overdue: BCG', 'Nita Kamau needs BCG (Dose 1). Due by Jun 13, 2026.', 1, 1, '2026-06-13 13:17:56'),
(78, 18, 18, 'vaccine_reminder', 'Vaccine Overdue: OPV 0', 'Nita Kamau needs OPV 0 (Dose 0). Due by Jun 13, 2026.', 2, 1, '2026-06-13 13:17:56'),
(79, 18, 18, 'flag', 'Growth Concern for Nita Kamau', 'Our doctor has identified that Nita Kamau needs to be reviewed by a specialist.\n\nReason: Microcephaly suspected - small head circumference at birth', 15, 0, '2026-06-13 13:30:19'),
(80, 19, 19, 'appointment_confirmation', 'Appointment Confirmed', 'Mumbua Kyalo has an appointment with Dr. Angela Nduta on Sunday, June 14, 2026 at 2:30 PM.', 37, 0, '2026-06-14 10:55:11'),
(81, 19, 19, 'appointment_reminder', 'Today: Appointment with Immunization Doctor', 'Mumbua has an appointment with Dr. Angela Nduta on Sunday, June 14, 2026 at 2:30 PM', 37, 0, '2026-06-14 10:57:33'),
(82, 19, 19, 'vaccine_reminder', 'Vaccine Overdue: BCG', 'Mumbua Kyalo needs BCG (Dose 1). Due by Jun 12, 2026.', 1, 0, '2026-06-14 10:57:33'),
(83, 19, 19, 'vaccine_reminder', 'Vaccine Overdue: OPV 0', 'Mumbua Kyalo needs OPV 0 (Dose 0). Due by Jun 12, 2026.', 2, 0, '2026-06-14 10:57:33');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `prescription_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `review_id` int(11) DEFAULT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dosage` varchar(255) NOT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`prescription_id`, `child_id`, `doctor_id`, `review_id`, `medication_name`, `dosage`, `frequency`, `duration`, `instructions`, `created_at`) VALUES
(1, 16, 1, 4, 'None', '', '', '', '', '2026-04-23 15:20:24'),
(2, 16, 1, 5, 'None', '', '', '', '', '2026-04-23 15:29:21'),
(3, 16, 1, 6, 'None', '', '', '', '', '2026-04-27 10:35:41'),
(4, 15, 1, 7, 'xxx', '', '', '', '', '2026-04-27 11:44:33');

-- --------------------------------------------------------

--
-- Table structure for table `specialist_reviews`
--

CREATE TABLE `specialist_reviews` (
  `review_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `flag_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) NOT NULL,
  `review_date` datetime DEFAULT current_timestamp(),
  `diagnosis` text DEFAULT NULL,
  `diagnosis_notes` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `lab_orders` text DEFAULT NULL,
  `referrals` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `status` enum('active','completed','follow_up') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `specialist_reviews`
--

INSERT INTO `specialist_reviews` (`review_id`, `child_id`, `flag_id`, `doctor_id`, `review_date`, `diagnosis`, `diagnosis_notes`, `treatment_plan`, `lab_orders`, `referrals`, `follow_up_date`, `status`) VALUES
(4, 16, NULL, 1, '2026-04-23 18:20:24', 'Neonatal Jaundice - likely physiological, but rule out pathological causes', '3-week-old infant presents with yellow discoloration of eyes and skin. Active, feeding well. No other symptoms.', 'Increase feeding frequency (every 2-3 hours). Monitor urine/stool color. Follow-up in 2 days.', 'Total Serum Bilirubin - STAT', 'none at this time', '2026-04-25', 'active'),
(5, 16, NULL, 1, '2026-04-23 18:29:21', 'Neonatal Jaundice - likely physiological, but rule out pathological causes', '3-week-old infant presents with yellow discoloration of eyes and skin. Active, feeding well. No other symptoms.', 'Increase feeding frequency (every 2-3 hours). Monitor urine/stool color. Follow-up in 2 days.', 'Total Serum Bilirubin - STAT', 'none at this time', '2026-04-25', 'active'),
(6, 16, NULL, 1, '2026-04-27 13:35:41', 'Neonatal Jaundice - Resolved', 'Mother reports baby is feeding well, more alert, and yellow discoloration has significantly decreased. No new symptoms. Physical exam shows mild residual yellowing only on face, chest clear. ', 'Continue feeding every 2-3 hours. Monitor for any recurrence. No further intervention needed at this time. Condition resolved with conservative management.', 'None ', 'None', NULL, 'active'),
(7, 15, NULL, 1, '2026-04-27 14:44:33', 'xxx', 'xxxx', 'xxxxx', 'xxxx', 'xxx', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `teeth_definitions`
--

CREATE TABLE `teeth_definitions` (
  `tooth_id` int(11) NOT NULL,
  `tooth_type` varchar(50) NOT NULL,
  `expected_age_min` int(11) DEFAULT NULL,
  `expected_age_max` int(11) DEFAULT NULL,
  `quadrant` varchar(20) DEFAULT NULL,
  `order_num` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teeth_definitions`
--

INSERT INTO `teeth_definitions` (`tooth_id`, `tooth_type`, `expected_age_min`, `expected_age_max`, `quadrant`, `order_num`) VALUES
(1, 'Lower Central Incisor', 4, 10, 'lower', 1),
(2, 'Upper Central Incisor', 6, 12, 'upper', 1),
(3, 'Upper Lateral Incisor', 8, 13, 'upper', 2),
(4, 'Lower Lateral Incisor', 8, 13, 'lower', 2),
(5, 'Lower First Molar', 12, 18, 'lower', 3),
(6, 'Upper First Molar', 12, 18, 'upper', 3),
(7, 'Lower Canine', 16, 23, 'lower', 4),
(8, 'Upper Canine', 16, 23, 'upper', 4),
(9, 'Lower Second Molar', 24, 30, 'lower', 5),
(10, 'Upper Second Molar', 24, 30, 'upper', 5);

-- --------------------------------------------------------

--
-- Table structure for table `vaccination_records`
--

CREATE TABLE `vaccination_records` (
  `vaccination_record_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `date_administered` date NOT NULL,
  `administered_by` int(11) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `status` enum('Completed','Pending','Overdue') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vaccination_records`
--

INSERT INTO `vaccination_records` (`vaccination_record_id`, `child_id`, `vaccine_id`, `date_administered`, `administered_by`, `next_due_date`, `status`, `notes`, `created_at`) VALUES
(1, 5, 1, '2021-02-25', 2, NULL, 'Pending', NULL, '2026-02-10 10:02:51'),
(2, 5, 1, '2026-02-20', 2, NULL, 'Pending', NULL, '2026-02-20 09:58:11'),
(3, 11, 1, '2026-01-01', 3, NULL, 'Completed', NULL, '2026-02-22 12:13:17'),
(4, 11, 2, '2026-01-01', 3, NULL, 'Completed', NULL, '2026-02-22 12:13:35'),
(5, 11, 3, '2026-02-13', 3, NULL, 'Completed', NULL, '2026-02-22 12:19:00'),
(6, 11, 4, '2026-02-22', 3, NULL, 'Completed', NULL, '2026-02-22 12:19:35'),
(7, 11, 5, '2026-02-22', 3, NULL, 'Completed', NULL, '2026-02-22 12:19:35'),
(8, 11, 6, '2026-02-22', 3, NULL, 'Completed', NULL, '2026-02-22 12:19:35'),
(9, 13, 1, '2026-03-11', 4, NULL, 'Completed', NULL, '2026-03-11 12:56:28'),
(10, 13, 2, '2026-03-15', 3, NULL, 'Completed', NULL, '2026-03-15 17:54:48'),
(11, 15, 1, '2026-04-13', 4, NULL, 'Completed', NULL, '2026-04-22 09:19:14'),
(12, 15, 2, '2026-04-13', 4, NULL, 'Completed', NULL, '2026-04-22 09:19:14'),
(13, 16, 1, '2026-04-22', 4, NULL, 'Completed', NULL, '2026-04-23 14:04:08'),
(14, 16, 2, '2026-04-22', 4, NULL, 'Completed', NULL, '2026-04-23 14:04:08'),
(15, 17, 1, '2026-06-13', 4, NULL, 'Completed', NULL, '2026-06-13 11:07:51'),
(16, 17, 2, '2026-06-13', 4, NULL, 'Completed', NULL, '2026-06-13 11:07:52'),
(17, 18, 1, '2026-06-13', 3, NULL, 'Completed', NULL, '2026-06-13 13:28:01'),
(18, 18, 2, '2026-06-13', 3, NULL, 'Completed', NULL, '2026-06-13 13:28:01');

-- --------------------------------------------------------

--
-- Table structure for table `vaccines`
--

CREATE TABLE `vaccines` (
  `vaccine_id` int(11) NOT NULL,
  `vaccine_name` varchar(255) NOT NULL,
  `dose_number` int(11) DEFAULT NULL,
  `recommended_age` varchar(100) NOT NULL,
  `min_age_weeks` int(11) DEFAULT NULL,
  `max_age_weeks` int(11) DEFAULT NULL,
  `interval_weeks` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vaccines`
--

INSERT INTO `vaccines` (`vaccine_id`, `vaccine_name`, `dose_number`, `recommended_age`, `min_age_weeks`, `max_age_weeks`, `interval_weeks`, `description`, `created_at`) VALUES
(1, 'BCG', 1, 'At birth', 0, 4, NULL, 'Bacillus Calmette-Guérin vaccine for tuberculosis', '2026-01-25 13:40:22'),
(2, 'OPV 0', 0, 'At birth', 0, 2, NULL, 'Oral Polio Vaccine - Birth dose', '2026-01-25 13:40:22'),
(3, 'OPV 1', 1, '6 weeks', 6, 10, NULL, 'Oral Polio Vaccine - First dose', '2026-01-25 13:40:22'),
(4, 'DPT-HepB-Hib 1 (Pentavalent 1)', 1, '6 weeks', 6, 10, NULL, 'Diphtheria, Pertussis, Tetanus Hepatitis B, Haemophilus influenzae type b - First dose', '2026-01-25 13:40:22'),
(5, 'PCV 1', 1, '6 weeks', 6, 10, NULL, 'Pneumococcal Conjugate Vaccine - First dose', '2026-01-25 13:40:22'),
(6, 'Rotavirus 1', 1, '6 weeks', 6, 10, NULL, 'Rotavirus Vaccine - First dose', '2026-01-25 13:40:22'),
(7, 'OPV 2', 2, '10 weeks', 10, 14, 4, 'Oral Polio Vaccine - Second dose', '2026-01-25 13:40:22'),
(8, 'DPT-HepB-Hib 2 (Pentavalent 2)', 2, '10 weeks', 10, 14, 4, 'Pentavalent - Second dose', '2026-01-25 13:40:22'),
(9, 'PCV 2', 2, '10 weeks', 10, 14, 4, 'Pneumococcal Conjugate Vaccine - Second dose', '2026-01-25 13:40:22'),
(10, 'Rotavirus 2', 2, '10 weeks', 10, 14, 4, 'Rotavirus Vaccine - Second dose', '2026-01-25 13:40:22'),
(11, 'OPV 3', 3, '14 weeks', 14, 18, 4, 'Oral Polio Vaccine - Third dose', '2026-01-25 13:40:22'),
(12, 'DPT-HepB-Hib 3 (Pentavalent 3)', 3, '14 weeks', 14, 18, 4, 'Pentavalent - Third dose', '2026-01-25 13:40:22'),
(13, 'PCV 3', 3, '14 weeks', 14, 18, 4, 'Pneumococcal Conjugate Vaccine - Third dose', '2026-01-25 13:40:22'),
(14, 'IPV', 1, '14 weeks', 14, 18, NULL, 'Inactivated Polio Vaccine', '2026-01-25 13:40:22'),
(15, 'Measles-Rubella 1', 1, '9 months', 36, 40, NULL, 'Measles-Rubella Vaccine - First dose', '2026-01-25 13:40:22'),
(16, 'Yellow Fever', 1, '9 months', 36, 40, NULL, 'Yellow Fever Vaccine', '2026-01-25 13:40:22'),
(17, 'Vitamin A (200,000 IU)', 1, '6 months', 24, 28, NULL, 'Vitamin A Supplementation', '2026-01-25 13:40:22'),
(18, 'Measles-Rubella 2', 2, '18 months', 72, 76, 36, 'Measles-Rubella Vaccine - Second dose', '2026-01-25 13:40:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `appointments_ibfk_2` (`doctor_id`);

--
-- Indexes for table `blocked_slots`
--
ALTER TABLE `blocked_slots`
  ADD PRIMARY KEY (`block_id`),
  ADD UNIQUE KEY `unique_block` (`doctor_id`,`block_date`,`block_time`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`child_id`),
  ADD KEY `children_guardian_fk` (`guardian_id`);

--
-- Indexes for table `child_milestones`
--
ALTER TABLE `child_milestones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_child_milestone` (`child_id`,`milestone_number`),
  ADD KEY `child_id` (`child_id`);

--
-- Indexes for table `child_teeth`
--
ALTER TABLE `child_teeth`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_child_tooth` (`child_id`,`tooth_id`),
  ADD KEY `tooth_id` (`tooth_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctor_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `license_number` (`license_number`);

--
-- Indexes for table `flags`
--
ALTER TABLE `flags`
  ADD PRIMARY KEY (`flag_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `flagged_by` (`flagged_by`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `growth_records`
--
ALTER TABLE `growth_records`
  ADD PRIMARY KEY (`growth_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `guardians`
--
ALTER TABLE `guardians`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD PRIMARY KEY (`lab_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `review_id` (`review_id`);

--
-- Indexes for table `milestone_definitions`
--
ALTER TABLE `milestone_definitions`
  ADD PRIMARY KEY (`milestone_number`),
  ADD KEY `idx_age_range` (`expected_age_min`,`expected_age_max`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `idx_guardian_unread` (`guardian_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`prescription_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `review_id` (`review_id`);

--
-- Indexes for table `specialist_reviews`
--
ALTER TABLE `specialist_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `flag_id` (`flag_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `teeth_definitions`
--
ALTER TABLE `teeth_definitions`
  ADD PRIMARY KEY (`tooth_id`);

--
-- Indexes for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD PRIMARY KEY (`vaccination_record_id`),
  ADD KEY `child_id` (`child_id`),
  ADD KEY `vaccine_id` (`vaccine_id`),
  ADD KEY `administered_by` (`administered_by`);

--
-- Indexes for table `vaccines`
--
ALTER TABLE `vaccines`
  ADD PRIMARY KEY (`vaccine_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `blocked_slots`
--
ALTER TABLE `blocked_slots`
  MODIFY `block_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `child_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `child_milestones`
--
ALTER TABLE `child_milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `child_teeth`
--
ALTER TABLE `child_teeth`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `flags`
--
ALTER TABLE `flags`
  MODIFY `flag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `growth_records`
--
ALTER TABLE `growth_records`
  MODIFY `growth_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `guardians`
--
ALTER TABLE `guardians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `lab_results`
--
ALTER TABLE `lab_results`
  MODIFY `lab_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `milestone_definitions`
--
ALTER TABLE `milestone_definitions`
  MODIFY `milestone_number` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `prescription_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `specialist_reviews`
--
ALTER TABLE `specialist_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `teeth_definitions`
--
ALTER TABLE `teeth_definitions`
  MODIFY `tooth_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  MODIFY `vaccination_record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `vaccines`
--
ALTER TABLE `vaccines`
  MODIFY `vaccine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON UPDATE CASCADE;

--
-- Constraints for table `blocked_slots`
--
ALTER TABLE `blocked_slots`
  ADD CONSTRAINT `blocked_slots_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE;

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `children_guardian_fk` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `child_milestones`
--
ALTER TABLE `child_milestones`
  ADD CONSTRAINT `child_milestones_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE;

--
-- Constraints for table `child_teeth`
--
ALTER TABLE `child_teeth`
  ADD CONSTRAINT `child_teeth_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `child_teeth_ibfk_2` FOREIGN KEY (`tooth_id`) REFERENCES `teeth_definitions` (`tooth_id`) ON DELETE CASCADE;

--
-- Constraints for table `flags`
--
ALTER TABLE `flags`
  ADD CONSTRAINT `flags_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`),
  ADD CONSTRAINT `flags_ibfk_2` FOREIGN KEY (`flagged_by`) REFERENCES `doctors` (`doctor_id`),
  ADD CONSTRAINT `flags_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `doctors` (`doctor_id`),
  ADD CONSTRAINT `flags_ibfk_4` FOREIGN KEY (`resolved_by`) REFERENCES `doctors` (`doctor_id`);

--
-- Constraints for table `growth_records`
--
ALTER TABLE `growth_records`
  ADD CONSTRAINT `growth_records_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `growth_records_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

--
-- Constraints for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD CONSTRAINT `fk_lab_results_review` FOREIGN KEY (`review_id`) REFERENCES `specialist_reviews` (`review_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_results_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_results_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_prescriptions_review` FOREIGN KEY (`review_id`) REFERENCES `specialist_reviews` (`review_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

--
-- Constraints for table `specialist_reviews`
--
ALTER TABLE `specialist_reviews`
  ADD CONSTRAINT `specialist_reviews_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`),
  ADD CONSTRAINT `specialist_reviews_ibfk_2` FOREIGN KEY (`flag_id`) REFERENCES `flags` (`flag_id`),
  ADD CONSTRAINT `specialist_reviews_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

--
-- Constraints for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD CONSTRAINT `vaccination_records_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccination_records_ibfk_2` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccination_records_ibfk_3` FOREIGN KEY (`administered_by`) REFERENCES `doctors` (`doctor_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
