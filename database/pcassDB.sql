-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Mar 07, 2026 at 06:02 PM
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
  `status` enum('Pending','Confirmed','Completed','Cancelled') DEFAULT 'Pending',
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
(11, 8, 2, '2026-02-16', '08:00:00', 'Pending', '', '2026-02-15 10:32:52'),
(12, 8, 1, '2026-02-16', '07:00:00', 'Pending', '', '2026-02-15 11:36:12'),
(13, 8, 1, '2026-02-28', '07:00:00', 'Pending', '', '2026-02-18 17:43:38'),
(14, 11, 1, '2026-02-21', '07:00:00', 'Pending', '', '2026-02-20 09:53:35'),
(15, 11, 3, '2026-02-22', '07:00:00', 'Completed', '', '2026-02-21 13:10:37'),
(16, 12, 3, '2026-02-23', '10:00:00', 'Pending', '', '2026-02-22 19:18:24'),
(17, 12, 3, '2026-02-25', '07:00:00', 'Pending', 'Routine immunization', '2026-02-24 17:09:42'),
(18, 13, 3, '2026-03-08', '08:30:00', 'Pending', '', '2026-03-07 15:25:38');

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
(13, 13, 'Alex', 'Mutai', 'Male', '2026-03-07', '2026-03-07 15:23:47');

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
(1, 'Mary Kamau', 'kamau@gmail.com', '0734267723', 'General Pediatrics', 'MP-LK675', 'PhD', 5, 'Full-Time', 'Active', 'specialist', '$2y$10$lAPA3zt5OcdIC.L87qobP.N/ebINXggEBuGEkX9yWPmUbxLnKehL2', '2026-01-27 07:36:24'),
(2, 'Chris Kiprono', 'ckiprono@gmail.com', '0722367543', 'Pediatric Cardiology', 'MP-LK555', 'PhD', 12, 'Part-Time', 'Active', 'specialist', '$2y$10$Lho6VsehCpKoc62cCYko3OVdpWXfeGOhNlt/cgElxhSnLaYatYeBe', '2026-02-09 14:51:37'),
(3, 'Karen Christine', 'christine@gmai.com', '0786455433', 'General Pediatrics', 'MP-LK756', 'MbSc', 2, 'Full-Time', 'Active', 'immunization', '$2y$10$5ypidC9CkDqZ7vMvfNxpXuUpDl1eIj1Llx1Qg4HBdD4jeR4EWek1a', '2026-02-21 12:45:34'),
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
(2, 12, 3, 1, 'vaccine', 'BCG and Polio first dose not given at birth', '', 'new', '2026-03-07 18:59:14', NULL, NULL);

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
(1, 11, 3, '2026-02-22', 4.20, 52.0, 36.8, NULL, NULL, NULL, NULL, '', '2026-02-22 12:12:22');

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
(13, 'Nancy Makena', 'nancymakena@gmail.com', '$2y$10$NsYijQo/2DlCbZKkC.7e6OesAZSLGLMkI7WYF/uqgHGeQgqiWFiay', '2026-03-07 15:23:18', 'mother', 'Nancy Makena', 'nancymakena@gmail.com', '+254734569872', 'Joseph Mutai', 'jmutai@gmail.com', '+254722345876', '', '', '', '');

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
(7, 13, 13, 'vaccine_reminder', 'Vaccine Due: BCG', 'BCG (Dose 1) is due now. Recommended by Mar 07, 2026.', 1, 0, '2026-03-07 15:32:14'),
(8, 13, 13, 'vaccine_reminder', 'Vaccine Due: OPV 0', 'OPV 0 (Dose 0) is due now. Recommended by Mar 07, 2026.', 2, 0, '2026-03-07 15:32:14'),
(9, 12, 12, 'flag', 'Vaccine-Related Concern for Leah Nana', 'During an immunization checkup, our doctor has identified that Leah Nana needs to be reviewed by a specialist.\n\nReason: BCG and Polio first dose not given at birth\nA specialist will review your child\'s case and contact you soon.', 2, 0, '2026-03-07 15:59:14');

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
(8, 11, 6, '2026-02-22', 3, NULL, 'Completed', NULL, '2026-02-22 12:19:35');

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
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `blocked_slots`
--
ALTER TABLE `blocked_slots`
  MODIFY `block_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `child_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
  MODIFY `flag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `growth_records`
--
ALTER TABLE `growth_records`
  MODIFY `growth_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `guardians`
--
ALTER TABLE `guardians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `lab_results`
--
ALTER TABLE `lab_results`
  MODIFY `lab_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `milestone_definitions`
--
ALTER TABLE `milestone_definitions`
  MODIFY `milestone_number` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `prescription_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `specialist_reviews`
--
ALTER TABLE `specialist_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teeth_definitions`
--
ALTER TABLE `teeth_definitions`
  MODIFY `tooth_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  MODIFY `vaccination_record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
