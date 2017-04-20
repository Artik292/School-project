-- phpMyAdmin SQL Dump
-- version 4.6.5.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 20, 2017 at 06:35 AM
-- Server version: 10.1.21-MariaDB
-- PHP Version: 5.6.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `try`
--

-- --------------------------------------------------------

--
-- Table structure for table `hope`
--

CREATE TABLE `hope` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `surname` text NOT NULL,
  `address` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `hope`
--

INSERT INTO `hope` (`id`, `name`, `surname`, `address`) VALUES
(2, 'faw', 'ffh', 'hufeg'),
(3, 'awfg', 'ga', 'gawg'),
(4, 'ghjthy', 'afegh', 'hehw'),
(5, 'agrh', 'aeg', 'awef');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hope`
--
ALTER TABLE `hope`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `hope`
--
ALTER TABLE `hope`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
