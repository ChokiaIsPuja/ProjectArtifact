-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 01, 2026 at 11:20 PM
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
-- Database: `db_roguelike`
--

-- --------------------------------------------------------

--
-- Table structure for table `bag`
--

CREATE TABLE `bag` (
  `bag_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bag`
--

INSERT INTO `bag` (`bag_id`, `item_id`, `player_id`, `qty`) VALUES
(2, 2, 7, 1),
(3, 3, 7, 1),
(4, 4, 7, 1),
(5, 5, 7, 1),
(6, 6, 7, 1),
(7, 7, 7, 1),
(8, 17, 7, 0),
(9, 19, 7, 0),
(10, 12, 7, 0),
(11, 14, 7, 0),
(12, 9, 7, 0),
(13, 19, 7, 0),
(14, 20, 7, 0),
(15, 21, 7, 0),
(16, 8, 7, 0),
(17, 13, 7, 0),
(18, 1, 7, 1),
(19, 1, 7, 1),
(20, 1, 7, 1),
(21, 19, 7, 0),
(22, 1, 7, 1),
(23, 1, 7, 1),
(24, 1, 7, 1),
(25, 1, 7, 1),
(26, 1, 7, 1),
(27, 1, 7, 1),
(28, 1, 7, 1),
(29, 1, 7, 1),
(30, 1, 7, 1),
(31, 1, 7, 1),
(32, 1, 7, 1),
(33, 1, 7, 1),
(34, 1, 7, 1),
(35, 1, 7, 1),
(36, 1, 7, 1),
(37, 1, 7, 1),
(38, 1, 7, 1),
(39, 1, 7, 1),
(40, 1, 7, 1),
(41, 1, 7, 1),
(42, 1, 7, 1),
(43, 1, 7, 1),
(44, 1, 7, 1),
(45, 16, 7, 0);

-- --------------------------------------------------------

--
-- Table structure for table `class`
--

CREATE TABLE `class` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(22) NOT NULL,
  `base_atk` int(11) NOT NULL,
  `base_def` int(11) NOT NULL,
  `base_hp` int(11) NOT NULL,
  `base_spd` int(11) NOT NULL,
  `avatar` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class`
--

INSERT INTO `class` (`class_id`, `class_name`, `base_atk`, `base_def`, `base_hp`, `base_spd`, `avatar`) VALUES
(1, 'Knight', 20, 35, 400, 10, 'Carter.png'),
(2, 'Archer', 30, 20, 300, 25, 'ElfyaSprite.png'),
(3, 'Mage', 35, 10, 200, 15, 'Mistic.png');

-- --------------------------------------------------------

--
-- Table structure for table `dropped_items`
--

CREATE TABLE `dropped_items` (
  `item_drop_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `base_chance` float NOT NULL,
  `qty` int(11) NOT NULL,
  `drop_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dropped_items`
--

INSERT INTO `dropped_items` (`item_drop_id`, `item_id`, `base_chance`, `qty`, `drop_id`) VALUES
(1, 2, 100, 1, 1),
(2, 1, 100, 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `drop_table`
--

CREATE TABLE `drop_table` (
  `drop_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drop_table`
--

INSERT INTO `drop_table` (`drop_id`) VALUES
(1),
(2);

-- --------------------------------------------------------

--
-- Table structure for table `enemy`
--

CREATE TABLE `enemy` (
  `enemy_id` int(11) NOT NULL,
  `enemy_name` varchar(200) NOT NULL,
  `base_exp` int(11) NOT NULL,
  `sprite` text NOT NULL,
  `drop_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enemy`
--

INSERT INTO `enemy` (`enemy_id`, `enemy_name`, `base_exp`, `sprite`, `drop_id`) VALUES
(1, 'Coffeen', 15, 'coffeen.png', 1),
(2, 'chogar', 2, 'chogar.png', 2);

-- --------------------------------------------------------

--
-- Table structure for table `enemy_stats`
--

CREATE TABLE `enemy_stats` (
  `enemy_stats_id` int(11) NOT NULL,
  `hp` int(11) NOT NULL,
  `atk` int(11) NOT NULL,
  `def` int(11) NOT NULL,
  `spd` int(11) NOT NULL,
  `enemy_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enemy_stats`
--

INSERT INTO `enemy_stats` (`enemy_stats_id`, `hp`, `atk`, `def`, `spd`, `enemy_id`) VALUES
(1, 40, 5, 5, 4, 1),
(2, 15, 10, 2, 2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `node_id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `sprite` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item`
--

CREATE TABLE `item` (
  `item_id` int(11) NOT NULL,
  `id_item_attributes` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `item_type` varchar(200) NOT NULL,
  `item_desc` text NOT NULL,
  `price` int(11) NOT NULL,
  `sprite` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item`
--

INSERT INTO `item` (`item_id`, `id_item_attributes`, `item_name`, `item_type`, `item_desc`, `price`, `sprite`) VALUES
(1, 1, 'Minor Healing Potion', 'consumables', 'Heals Character by 50 HP', 0, 'healing_potion1.png\r\n'),
(2, 2, 'Coffee', 'consumables', 'Heal 20 health', 0, 'coffee.png'),
(3, 3, 'Shiny Wooden Bow', 'armaments', 'Oooooo.... Shiny.....', 700, 'shiny_wooden_bow.png'),
(4, 4, 'Archer\'s Hood', 'helmet', 'a', 1200, 'archerhood.png'),
(5, 5, 'Archer\'s Robe', 'armor', 'a', 2000, 'archerrobe.png\r\n'),
(6, 7, 'The Perfect Twenty', 'accessory', 'a', 5000, 'theperfecttwenty'),
(7, 6, 'Archer\'s Boots', 'boots', 'a', 1500, 'archerboots.png'),
(8, 8, 'Wooden Sword', 'armaments', 'It still hurts y\'know?', 50, 'default.png'),
(9, 9, 'Leather Cap', 'helmet', 'Protects one from the scorching ray of the sun.', 12, 'default.png'),
(10, 10, 'Farmer\'s Cap', 'helmet', 'Some poor farmer is probably searching for this everywhere...', 10, 'default.png'),
(11, 11, 'Wooden Plank', 'armaments', 'You realize this is not a sandbox right?', 20, 'default.png'),
(12, 12, 'Ragged Clothes', 'armor', 'Where did you get this...', 5, 'default.png'),
(13, 13, 'Leather Tunic', 'armor', 'Works!', 100, 'default.png'),
(14, 14, 'Worn Sandals', 'boots', 'Ah... the memories...', 10, 'worn_sandals.png'),
(15, 15, 'Leather Boots', 'boots', 'Works! Especially on your foot!', 70, 'default.png'),
(16, 16, 'Copper Ring', 'Accessory', 'A classic starter item!', 20, 'copper_ring.png'),
(17, 17, 'Wooden Shield', 'Accessory', 'A great defense.... is a shield!', 30, 'wooden_shield.png'),
(18, 18, 'Healing Herb', 'Consumable', 'Tastes like mint...', 2, 'healing_herb.png'),
(19, 19, 'Fresh Water', 'Consumable', 'Stay hydrated!', 5, 'fresh_water.png'),
(20, 20, 'Vial of Life', 'Consumable', 'Permanent +10 HP upon consumption', 2000, 'vial_of_life.png'),
(21, 21, 'Life Fruit', 'Consumable', 'Increase Max HP by 20 upon consumption (\"This feels oddly familiar\")', 3140, 'life_fruit.png'),
(22, 22, 'Chicken Breast', 'Consumable', 'Grow stronger, increases ATK by +1 upon consumption', 200, 'chicken_breast.png'),
(23, 23, 'Spark Potion', 'Consumable', 'Increases ATK by +2 upon comsumption', 1500, 'spark_potion.png');

-- --------------------------------------------------------

--
-- Table structure for table `item_attributes`
--

CREATE TABLE `item_attributes` (
  `id_item_attributes` int(11) NOT NULL,
  `att_atk` int(11) DEFAULT NULL,
  `att_def` int(11) DEFAULT NULL,
  `att_hp` int(11) DEFAULT NULL,
  `att_max_hp` int(11) DEFAULT NULL,
  `att_spd` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_attributes`
--

INSERT INTO `item_attributes` (`id_item_attributes`, `att_atk`, `att_def`, `att_hp`, `att_max_hp`, `att_spd`) VALUES
(1, 0, 0, 50, 0, NULL),
(2, 0, 0, 20, 0, NULL),
(3, 20, 0, 0, 0, NULL),
(4, 0, 10, 0, 20, NULL),
(5, 0, 10, NULL, 20, NULL),
(6, 0, 15, NULL, 50, 7),
(7, 20, 20, NULL, 20, NULL),
(8, 5, 0, 0, 0, 0),
(9, 0, 1, 0, 5, 0),
(10, 1, 1, 0, 10, 0),
(11, 3, 2, 0, 0, 0),
(12, 0, 3, 0, 5, 0),
(13, 0, 4, 0, 0, 1),
(14, 2, 1, 0, 0, 3),
(15, 1, 3, 0, 10, 4),
(16, 3, 0, 0, 0, 1),
(17, 0, 5, 0, 10, 0),
(18, 0, 0, 5, 0, 0),
(19, 0, 0, 10, 0, 0),
(20, 0, 0, 0, 10, 0),
(21, 0, 0, 0, 20, 0),
(22, 1, 0, 0, 0, 0),
(23, 2, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `nodes`
--

CREATE TABLE `nodes` (
  `node_id` int(11) NOT NULL,
  `node_name` varchar(255) NOT NULL,
  `node_type` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nodes`
--

INSERT INTO `nodes` (`node_id`, `node_name`, `node_type`) VALUES
(1, 'Beginning', 1),
(2, 'Combat', 2);

-- --------------------------------------------------------

--
-- Table structure for table `obtainable_skill`
--

CREATE TABLE `obtainable_skill` (
  `skill_id` int(11) NOT NULL,
  `skill_name` varchar(200) NOT NULL,
  `class_id` int(11) NOT NULL,
  `lvl_required` int(11) NOT NULL,
  `cooldown` int(20) NOT NULL,
  `id_skill_attributes` int(22) NOT NULL,
  `skill_area` int(11) NOT NULL,
  `skill_desc` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `obtainable_skill`
--

INSERT INTO `obtainable_skill` (`skill_id`, `skill_name`, `class_id`, `lvl_required`, `cooldown`, `id_skill_attributes`, `skill_area`, `skill_desc`) VALUES
(1, 'Arrow Rain', 2, 3, 3, 1, 5, 'Shoot a barrage of arrows into the air, dealing 80% of attack in a large area.'),
(3, 'Shoot', 2, 1, 0, 2, 1, 'Deal 100% atk to 1 enemy');

-- --------------------------------------------------------

--
-- Table structure for table `obtained_skill`
--

CREATE TABLE `obtained_skill` (
  `obtained_skill_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player`
--

CREATE TABLE `player` (
  `player_id` int(11) NOT NULL,
  `name` varchar(10) NOT NULL,
  `class_id` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  `exp` int(11) NOT NULL,
  `gold` int(20) NOT NULL,
  `id_user` int(11) NOT NULL,
  `created_at` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `player`
--

INSERT INTO `player` (`player_id`, `name`, `class_id`, `level`, `exp`, `gold`, `id_user`, `created_at`) VALUES
(1, 'Kyuri', 2, 43, 0, 0, 1, '2026-05-18 23:44:05'),
(3, 'Fuii', 1, 1, 0, 0, 1, '2026-05-19 07:38:14'),
(4, 'Elfya', 2, 1, 0, 0, 1, '2026-05-19 19:52:42'),
(5, 'Kiyti', 3, 1, 0, 0, 1, '2026-05-19 19:52:59'),
(6, 'Rika', 2, 1, 0, 0, 2, '2026-05-20 13:01:20'),
(7, 'Mimi', 2, 12, 61, 25, 1, '2026-05-21 12:46:01');

-- --------------------------------------------------------

--
-- Table structure for table `player_equipment`
--

CREATE TABLE `player_equipment` (
  `slot_id` int(11) NOT NULL,
  `slot_name` varchar(200) NOT NULL,
  `bag_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `player_equipment`
--

INSERT INTO `player_equipment` (`slot_id`, `slot_name`, `bag_id`, `player_id`) VALUES
(19, 'armaments', 3, 7),
(20, 'helmet', 4, 7),
(21, 'armor', 5, 7),
(22, 'accessory', 6, 7);

-- --------------------------------------------------------

--
-- Table structure for table `player_stats`
--

CREATE TABLE `player_stats` (
  `player_stat_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `curr_max_hp` int(11) NOT NULL,
  `curr_atk` int(11) NOT NULL,
  `curr_def` int(11) NOT NULL,
  `curr_spd` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `player_stats`
--

INSERT INTO `player_stats` (`player_stat_id`, `player_id`, `curr_max_hp`, `curr_atk`, `curr_def`, `curr_spd`) VALUES
(1, 7, 350, 52, -42, 33);

-- --------------------------------------------------------

--
-- Table structure for table `runs`
--

CREATE TABLE `runs` (
  `run_id` int(11) NOT NULL,
  `current_node` varchar(255) NOT NULL,
  `player_id` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `started_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `runs`
--

INSERT INTO `runs` (`run_id`, `current_node`, `player_id`, `status`, `started_at`) VALUES
(1, 'node17', 7, 0, '2026-06-01 17:03:34'),
(2, 'node1', 1, 0, '2026-06-01 17:19:08'),
(3, 'node1', 3, 0, '2026-06-01 17:22:35'),
(4, 'node1', 5, 0, '2026-06-01 17:23:00');

-- --------------------------------------------------------

--
-- Table structure for table `shop_item`
--

CREATE TABLE `shop_item` (
  `shop_item_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `shop_pool_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shop_item`
--

INSERT INTO `shop_item` (`shop_item_id`, `item_id`, `shop_pool_id`) VALUES
(1, 1, 1),
(2, 19, 1),
(3, 14, 1),
(4, 7, 1),
(5, 4, 1),
(6, 5, 1),
(7, 22, 1),
(8, 2, 1),
(9, 16, 1),
(10, 10, 1),
(11, 18, 1),
(12, 15, 1),
(13, 9, 1),
(14, 13, 1),
(15, 21, 1),
(16, 12, 1),
(17, 3, 1),
(18, 23, 1),
(19, 6, 1),
(20, 20, 1),
(21, 11, 1),
(22, 17, 1),
(23, 8, 1);

-- --------------------------------------------------------

--
-- Table structure for table `shop_pool`
--

CREATE TABLE `shop_pool` (
  `shop_pool_id` int(11) NOT NULL,
  `map_level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shop_pool`
--

INSERT INTO `shop_pool` (`shop_pool_id`, `map_level`) VALUES
(1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `skill_attributes`
--

CREATE TABLE `skill_attributes` (
  `id_skill_attributes` int(11) NOT NULL,
  `skill_atk` int(11) NOT NULL,
  `skill_def` int(11) NOT NULL,
  `skill_heal` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skill_attributes`
--

INSERT INTO `skill_attributes` (`id_skill_attributes`, `skill_atk`, `skill_def`, `skill_heal`) VALUES
(1, 80, 0, 0),
(2, 100, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id_user` int(11) NOT NULL,
  `username` varchar(20) NOT NULL,
  `password` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id_user`, `username`, `password`) VALUES
(1, 'Chokia', '12345'),
(2, 'admin1', 'admin1');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bag`
--
ALTER TABLE `bag`
  ADD PRIMARY KEY (`bag_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Indexes for table `class`
--
ALTER TABLE `class`
  ADD PRIMARY KEY (`class_id`);

--
-- Indexes for table `dropped_items`
--
ALTER TABLE `dropped_items`
  ADD PRIMARY KEY (`item_drop_id`),
  ADD KEY `id_item` (`item_id`,`drop_id`),
  ADD KEY `drop_id` (`drop_id`);

--
-- Indexes for table `drop_table`
--
ALTER TABLE `drop_table`
  ADD PRIMARY KEY (`drop_id`);

--
-- Indexes for table `enemy`
--
ALTER TABLE `enemy`
  ADD PRIMARY KEY (`enemy_id`),
  ADD UNIQUE KEY `drop_id` (`drop_id`);

--
-- Indexes for table `enemy_stats`
--
ALTER TABLE `enemy_stats`
  ADD PRIMARY KEY (`enemy_stats_id`),
  ADD KEY `enemy_id` (`enemy_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `node_id` (`node_id`);

--
-- Indexes for table `item`
--
ALTER TABLE `item`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `id_item_attributes` (`id_item_attributes`);

--
-- Indexes for table `item_attributes`
--
ALTER TABLE `item_attributes`
  ADD PRIMARY KEY (`id_item_attributes`);

--
-- Indexes for table `nodes`
--
ALTER TABLE `nodes`
  ADD PRIMARY KEY (`node_id`);

--
-- Indexes for table `obtainable_skill`
--
ALTER TABLE `obtainable_skill`
  ADD PRIMARY KEY (`skill_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `id_skill_attributes` (`id_skill_attributes`);

--
-- Indexes for table `obtained_skill`
--
ALTER TABLE `obtained_skill`
  ADD PRIMARY KEY (`obtained_skill_id`),
  ADD KEY `skill_id` (`skill_id`,`player_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Indexes for table `player`
--
ALTER TABLE `player`
  ADD PRIMARY KEY (`player_id`),
  ADD KEY `class_id` (`class_id`,`id_user`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `player_equipment`
--
ALTER TABLE `player_equipment`
  ADD PRIMARY KEY (`slot_id`),
  ADD KEY `bag_id` (`bag_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Indexes for table `player_stats`
--
ALTER TABLE `player_stats`
  ADD PRIMARY KEY (`player_stat_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Indexes for table `runs`
--
ALTER TABLE `runs`
  ADD PRIMARY KEY (`run_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Indexes for table `shop_item`
--
ALTER TABLE `shop_item`
  ADD PRIMARY KEY (`shop_item_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `shop_pool_id` (`shop_pool_id`);

--
-- Indexes for table `shop_pool`
--
ALTER TABLE `shop_pool`
  ADD PRIMARY KEY (`shop_pool_id`);

--
-- Indexes for table `skill_attributes`
--
ALTER TABLE `skill_attributes`
  ADD PRIMARY KEY (`id_skill_attributes`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bag`
--
ALTER TABLE `bag`
  MODIFY `bag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `class`
--
ALTER TABLE `class`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dropped_items`
--
ALTER TABLE `dropped_items`
  MODIFY `item_drop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `drop_table`
--
ALTER TABLE `drop_table`
  MODIFY `drop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `enemy`
--
ALTER TABLE `enemy`
  MODIFY `enemy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `enemy_stats`
--
ALTER TABLE `enemy_stats`
  MODIFY `enemy_stats_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item`
--
ALTER TABLE `item`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `item_attributes`
--
ALTER TABLE `item_attributes`
  MODIFY `id_item_attributes` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `nodes`
--
ALTER TABLE `nodes`
  MODIFY `node_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `obtainable_skill`
--
ALTER TABLE `obtainable_skill`
  MODIFY `skill_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `obtained_skill`
--
ALTER TABLE `obtained_skill`
  MODIFY `obtained_skill_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `player`
--
ALTER TABLE `player`
  MODIFY `player_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `player_equipment`
--
ALTER TABLE `player_equipment`
  MODIFY `slot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `player_stats`
--
ALTER TABLE `player_stats`
  MODIFY `player_stat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `runs`
--
ALTER TABLE `runs`
  MODIFY `run_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shop_item`
--
ALTER TABLE `shop_item`
  MODIFY `shop_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `shop_pool`
--
ALTER TABLE `shop_pool`
  MODIFY `shop_pool_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `skill_attributes`
--
ALTER TABLE `skill_attributes`
  MODIFY `id_skill_attributes` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bag`
--
ALTER TABLE `bag`
  ADD CONSTRAINT `bag_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`),
  ADD CONSTRAINT `bag_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `player` (`player_id`);

--
-- Constraints for table `dropped_items`
--
ALTER TABLE `dropped_items`
  ADD CONSTRAINT `dropped_items_ibfk_1` FOREIGN KEY (`drop_id`) REFERENCES `drop_table` (`drop_id`),
  ADD CONSTRAINT `dropped_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`);

--
-- Constraints for table `drop_table`
--
ALTER TABLE `drop_table`
  ADD CONSTRAINT `drop_table_ibfk_1` FOREIGN KEY (`drop_id`) REFERENCES `enemy` (`drop_id`);

--
-- Constraints for table `enemy_stats`
--
ALTER TABLE `enemy_stats`
  ADD CONSTRAINT `enemy_stats_ibfk_1` FOREIGN KEY (`enemy_id`) REFERENCES `enemy` (`enemy_id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`node_id`) REFERENCES `nodes` (`node_id`);

--
-- Constraints for table `item`
--
ALTER TABLE `item`
  ADD CONSTRAINT `item_ibfk_1` FOREIGN KEY (`id_item_attributes`) REFERENCES `item_attributes` (`id_item_attributes`);

--
-- Constraints for table `obtainable_skill`
--
ALTER TABLE `obtainable_skill`
  ADD CONSTRAINT `obtainable_skill_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`),
  ADD CONSTRAINT `obtainable_skill_ibfk_2` FOREIGN KEY (`id_skill_attributes`) REFERENCES `skill_attributes` (`id_skill_attributes`);

--
-- Constraints for table `obtained_skill`
--
ALTER TABLE `obtained_skill`
  ADD CONSTRAINT `obtained_skill_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `player` (`player_id`),
  ADD CONSTRAINT `obtained_skill_ibfk_2` FOREIGN KEY (`obtained_skill_id`) REFERENCES `obtainable_skill` (`skill_id`);

--
-- Constraints for table `player`
--
ALTER TABLE `player`
  ADD CONSTRAINT `player_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`),
  ADD CONSTRAINT `player_ibfk_3` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`);

--
-- Constraints for table `player_equipment`
--
ALTER TABLE `player_equipment`
  ADD CONSTRAINT `player_equipment_ibfk_1` FOREIGN KEY (`bag_id`) REFERENCES `bag` (`bag_id`);

--
-- Constraints for table `player_stats`
--
ALTER TABLE `player_stats`
  ADD CONSTRAINT `player_stats_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `player` (`player_id`) ON DELETE CASCADE;

--
-- Constraints for table `runs`
--
ALTER TABLE `runs`
  ADD CONSTRAINT `runs_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `player` (`player_id`);

--
-- Constraints for table `shop_item`
--
ALTER TABLE `shop_item`
  ADD CONSTRAINT `shop_item_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`),
  ADD CONSTRAINT `shop_item_ibfk_2` FOREIGN KEY (`shop_pool_id`) REFERENCES `shop_pool` (`shop_pool_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
