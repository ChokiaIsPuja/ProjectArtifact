-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2026 at 10:51 PM
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
(1, 33, 17, 1),
(2, 51, 19, 0),
(3, 56, 16, 0),
(4, 15, 19, 1),
(5, 15, 19, 1),
(6, 15, 19, 1),
(7, 24, 18, 0);

-- --------------------------------------------------------

--
-- Table structure for table `choice_reward`
--

CREATE TABLE `choice_reward` (
  `id_choice_reward` int(11) NOT NULL,
  `event_hp` int(11) NOT NULL,
  `event_max_hp` int(11) DEFAULT 0,
  `event_str` int(11) NOT NULL DEFAULT 0,
  `event_def` int(11) NOT NULL DEFAULT 0,
  `event_dex` int(11) NOT NULL DEFAULT 0,
  `event_int` int(11) NOT NULL DEFAULT 0,
  `event_fth` int(11) NOT NULL DEFAULT 0,
  `item_id` int(11) DEFAULT 0,
  `qty` int(11) NOT NULL,
  `gold` int(11) NOT NULL,
  `event_options_id` int(11) DEFAULT 0,
  `exp` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `choice_reward`
--

INSERT INTO `choice_reward` (`id_choice_reward`, `event_hp`, `event_max_hp`, `event_str`, `event_def`, `event_dex`, `event_int`, `event_fth`, `item_id`, `qty`, `gold`, `event_options_id`, `exp`) VALUES
(2, 0, 0, 0, 0, 0, 0, 0, 2, 0, 0, 5, 0),
(3, 0, 0, 0, 0, 0, 0, 0, 15, 1, 0, 6, 0),
(4, 0, 0, 0, 0, 0, 0, 0, 15, 1, 0, 7, 0),
(5, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 8, 0),
(6, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 9, 0),
(7, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 10, 0),
(8, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 11, 0);

-- --------------------------------------------------------

--
-- Table structure for table `class`
--

CREATE TABLE `class` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(22) NOT NULL,
  `base_str` int(11) NOT NULL,
  `base_def` int(11) NOT NULL,
  `base_hp` int(11) NOT NULL,
  `base_dex` int(11) NOT NULL,
  `base_int` int(11) NOT NULL,
  `base_fth` int(11) NOT NULL,
  `avatar` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class`
--

INSERT INTO `class` (`class_id`, `class_name`, `base_str`, `base_def`, `base_hp`, `base_dex`, `base_int`, `base_fth`, `avatar`) VALUES
(1, 'Knight', 20, 30, 400, 10, 5, 0, 'Carter.png'),
(2, 'Archer', 20, 10, 300, 25, 10, 0, 'ElfyaSprite.png'),
(3, 'Mage', 5, 10, 300, 15, 25, 10, 'Mistic.png');

-- --------------------------------------------------------

--
-- Table structure for table `detail_dialogue`
--

CREATE TABLE `detail_dialogue` (
  `detail_dialogue_id` int(11) NOT NULL,
  `text` text NOT NULL,
  `order_no` int(11) NOT NULL DEFAULT 1,
  `event_dialogue_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_dialogue`
--

INSERT INTO `detail_dialogue` (`detail_dialogue_id`, `text`, `order_no`, `event_dialogue_id`) VALUES
(4, 'tess', 1, 3),
(5, 'tess', 2, 3),
(6, 'You see a water well standing ominously ahead. What do you want to do?', 1, 1),
(7, 'ba', 1, 4),
(8, 'bas', 2, 4);

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
  `enemy_id` int(11) NOT NULL,
  `enemy_hp` int(11) NOT NULL,
  `enemy_str` int(11) NOT NULL,
  `enemy_def` int(11) NOT NULL,
  `enemy_dex` int(11) NOT NULL,
  `enemy_fth` int(11) NOT NULL,
  `enemy_int` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enemy_stats`
--

INSERT INTO `enemy_stats` (`enemy_stats_id`, `enemy_id`, `enemy_hp`, `enemy_str`, `enemy_def`, `enemy_dex`, `enemy_fth`, `enemy_int`) VALUES
(1, 1, 40, 5, 5, 4, 0, 2),
(2, 2, 15, 10, 2, 2, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `equipment_att_req`
--

CREATE TABLE `equipment_att_req` (
  `equip_req_id` int(11) NOT NULL,
  `req_str` int(11) NOT NULL,
  `req_def` int(11) NOT NULL,
  `req_dex` int(11) NOT NULL,
  `req_int` int(11) NOT NULL,
  `req_fth` int(11) NOT NULL,
  `item_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_att_req`
--

INSERT INTO `equipment_att_req` (`equip_req_id`, `req_str`, `req_def`, `req_dex`, `req_int`, `req_fth`, `item_id`) VALUES
(1, 0, 0, 0, 0, 0, 1),
(2, 0, 0, 0, 0, 0, 2),
(3, 0, 0, 0, 0, 0, 3),
(4, 0, 0, 0, 0, 0, 4),
(5, 0, 0, 0, 0, 0, 5),
(6, 0, 0, 0, 0, 0, 6),
(7, 0, 0, 0, 0, 0, 7),
(8, 0, 0, 0, 0, 0, 8),
(9, 0, 0, 0, 0, 0, 9),
(10, 0, 0, 0, 0, 0, 10),
(11, 0, 0, 0, 0, 0, 11),
(12, 0, 0, 0, 0, 0, 12),
(13, 0, 0, 0, 0, 0, 13),
(14, 0, 0, 0, 0, 0, 14),
(15, 0, 0, 0, 0, 0, 15),
(16, 0, 0, 0, 0, 0, 16),
(17, 0, 0, 0, 0, 0, 17),
(18, 12, 0, 0, 0, 0, 18),
(19, 2, 0, 2, 0, 0, 19),
(20, 15, 0, 0, 0, 0, 20),
(21, 8, 0, 12, 0, 0, 21),
(22, 18, 0, 0, 0, 10, 22),
(23, 2, 0, 5, 2, 0, 23),
(24, 0, 0, 12, 0, 0, 24),
(25, 13, 0, 10, 0, 0, 25),
(26, 9, 0, 20, 0, 0, 26),
(27, 20, 5, 8, 10, 2, 27),
(28, 0, 0, 0, 11, 0, 28),
(29, 0, 0, 9, 15, 0, 29),
(30, 0, 0, 0, 20, 5, 30),
(31, 0, 0, 3, 22, 12, 31),
(32, 10, 0, 12, 24, 2, 32),
(33, 10, 5, 0, 0, 0, 33),
(34, 13, 12, 0, 0, 0, 34),
(35, 16, 18, 0, 0, 0, 35),
(36, 15, 15, 0, 0, 10, 36),
(37, 0, 0, 10, 0, 0, 37),
(38, 0, 2, 10, 15, 0, 38),
(39, 0, 0, 22, 2, 16, 39),
(40, 0, 0, 12, 7, 0, 40),
(41, 0, 0, 0, 7, 0, 41),
(42, 0, 0, 0, 16, 10, 42),
(43, 0, 0, 0, 22, 0, 43),
(44, 0, 2, 2, 0, 0, 44),
(45, 12, 8, 0, 0, 0, 45),
(46, 0, 0, 13, 0, 0, 46),
(47, 0, 0, 17, 0, 0, 47),
(48, 0, 0, 0, 16, 0, 48),
(49, 0, 0, 0, 12, 0, 49),
(50, 10, 0, 0, 0, 0, 50),
(51, 14, 10, 0, 0, 0, 51),
(52, 0, 0, 11, 0, 0, 52),
(53, 0, 0, 15, 0, 0, 53),
(54, 0, 0, 0, 10, 0, 54),
(55, 0, 0, 0, 16, 0, 55),
(56, 0, 0, 0, 0, 0, 56),
(57, 0, 0, 0, 0, 0, 57),
(58, 0, 0, 0, 0, 0, 58),
(59, 0, 0, 0, 0, 0, 59),
(60, 0, 0, 0, 0, 0, 60);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `sprite` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `event_name`, `sprite`) VALUES
(1, 'The Wishing Well', 'the_wishing_well.png'),
(3, 'tess', 'tess'),
(4, 'ba', 'ba');

-- --------------------------------------------------------

--
-- Table structure for table `event_dialogue`
--

CREATE TABLE `event_dialogue` (
  `id_event_dialogue` int(11) NOT NULL,
  `event_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_dialogue`
--

INSERT INTO `event_dialogue` (`id_event_dialogue`, `event_id`) VALUES
(1, 1),
(3, 3),
(4, 4);

-- --------------------------------------------------------

--
-- Table structure for table `event_options`
--

CREATE TABLE `event_options` (
  `event_options_id` int(11) NOT NULL,
  `option_name` varchar(255) NOT NULL,
  `event_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_options`
--

INSERT INTO `event_options` (`event_options_id`, `option_name`, `event_id`) VALUES
(5, 'tess1', 3),
(6, 'Throw 1 coin', 1),
(7, 'Throw 1 coin', 1),
(8, 'Throw 100 coins', 1),
(9, 'Throw 1000 coins', 1),
(10, 'Ignore it. (Leave)', 1),
(11, 'ba', 4);

-- --------------------------------------------------------

--
-- Table structure for table `event_option_requirements`
--

CREATE TABLE `event_option_requirements` (
  `event_option_req_id` int(11) NOT NULL,
  `req_max_hp` int(11) NOT NULL DEFAULT 0,
  `req_hp` int(11) NOT NULL DEFAULT 0,
  `req_gold` int(11) NOT NULL DEFAULT 0,
  `req_level` int(11) NOT NULL DEFAULT 0,
  `req_str` int(11) NOT NULL DEFAULT 0,
  `req_def` int(11) NOT NULL DEFAULT 0,
  `req_dex` int(11) NOT NULL DEFAULT 0,
  `req_int` int(11) NOT NULL DEFAULT 0,
  `req_fth` int(11) NOT NULL DEFAULT 0,
  `id_item` int(11) DEFAULT NULL,
  `event_options_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_option_requirements`
--

INSERT INTO `event_option_requirements` (`event_option_req_id`, `req_max_hp`, `req_hp`, `req_gold`, `req_level`, `req_str`, `req_def`, `req_dex`, `req_int`, `req_fth`, `id_item`, `event_options_id`) VALUES
(7, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 5),
(8, 0, 0, 1, 0, 0, 0, 0, 0, 0, NULL, 6),
(9, 0, 0, 1, 0, 0, 0, 0, 0, 0, NULL, 7),
(10, 0, 0, 100, 0, 0, 0, 0, 0, 0, NULL, 8),
(11, 0, 0, 1000, 0, 0, 0, 0, 0, 0, NULL, 9),
(12, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 10),
(13, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 11);

-- --------------------------------------------------------

--
-- Table structure for table `event_text_output`
--

CREATE TABLE `event_text_output` (
  `event_text_output_id` int(11) NOT NULL,
  `event_option_id` int(11) NOT NULL,
  `order_no` int(11) NOT NULL DEFAULT 1,
  `text_output` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_text_output`
--

INSERT INTO `event_text_output` (`event_text_output_id`, `event_option_id`, `order_no`, `text_output`) VALUES
(4, 5, 1, 'tess1'),
(5, 6, 1, 'The well woke up from its slumber, and gave you a serving of fresh water.'),
(6, 7, 1, '\"\\Thanks\"\\'),
(7, 8, 1, 'null'),
(8, 9, 1, 'null'),
(9, 10, 1, 'You shrugs and left the well..'),
(10, 11, 1, 'babababa');

-- --------------------------------------------------------

--
-- Table structure for table `item`
--

CREATE TABLE `item` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `item_type` varchar(200) NOT NULL,
  `item_desc` text NOT NULL,
  `buy_price` int(11) NOT NULL,
  `sell_price` int(11) NOT NULL,
  `sprite` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item`
--

INSERT INTO `item` (`item_id`, `item_name`, `item_type`, `item_desc`, `buy_price`, `sell_price`, `sprite`) VALUES
(1, 'Green Herb', 'consumable', 'Heals for 5 HP', 5, 2, 'green_herb.png'),
(2, 'Red Herb', 'consumable', 'Heals for 10 HP', 10, 4, 'red_herb.png'),
(3, 'Blue Herb', 'consumable', 'Heals for 20 HP', 30, 12, 'blue_herb.png'),
(4, 'Minor Healing Potion', 'consumable', 'Heals for 50 HP', 100, 40, 'minor_healing_potion.png'),
(5, 'Healing Potion', 'consumable', 'Heals for 150 HP', 200, 80, 'healing_potion.png'),
(6, 'Potent Healing Potion', 'consumable', 'Heals For 200 HP', 300, 120, 'potent_healing_potion.png'),
(7, 'Extreme Healing Potion', 'consumable', 'Heals for 500 HP', 1000, 400, 'extreme_healing_potion.png'),
(8, 'Life Fruit', 'consumable', 'Increases Max HP by 25', 1999, 799, 'life_fruit.png'),
(9, 'Vial of Life', 'consumable', 'Increases Max Hp by 50', 2500, 1000, 'vial_of_life.png'),
(10, 'Golden Apple', 'consumable', 'Heals for 200 HP and increases Max Hp by 50', 3000, 1200, 'golden_apple.png'),
(11, 'Spark Potion', 'consumable', 'Increases Atk by 2 permanently', 1000, 400, 'spark_potion.png'),
(12, 'Vital Gummi', 'consumable', 'Increases Max Hp by 20 and all stats by 1', 4200, 1680, 'vital_gummi.png'),
(13, 'Hearthy Turnip', 'consumable', 'Increases all stats by 2', 7255, 2902, 'hearthy_turnip.png'),
(14, 'Chicken Breast', 'consumable', 'Increases Str by 2', 1500, 600, 'chicken_breast.png'),
(15, 'Lemonade', 'consumable', 'Increases Dex by 2', 500, 200, 'lemonade.png'),
(16, 'Edible Paper', 'consumable', 'Increases Int by 2', 1000, 400, 'edible_paper.png'),
(17, 'Garlic', 'consumable', 'Increases Fth by 2', 1000, 400, 'garlic.png'),
(18, 'Iron Broadsword', 'weapon', 'Description: A sturdy, well-balanced blade favored by vanguard recruits.', 150, 60, 'iron_broadsword.png'),
(19, 'Wooden Sword', 'weapon', 'It still hurts y\\\'know', 20, 8, 'wooden_sword.png'),
(20, 'Vanguard Knight\\\'s Mace', 'weapon', 'Flanged steel designed to crush armor plate. Slightly reduces agility due to its weight.', 220, 88, 'vanguard_knights_mace.png'),
(21, 'Rusty Estoc', 'weapon', 'An old thrusting sword. It doesn\\\'t rely much on raw muscle, but requires a nimble grip.', 90, 36, 'rusty_estoc.png'),
(22, 'Blessed Greatsword', 'weapon', 'A massive two-handed sword etched with holy runes. Boosts physical damage and a tiny bit of faith.', 450, 180, 'blessed_greatsword.png'),
(23, 'Shiny Wooden Bow', 'weapon', 'OOooooo...... Shiny....', 50, 20, 'shiny_wooden_bow.png'),
(24, 'Yew Bow', 'weapon', 'A flexible wooden bow with a smooth draw weight, perfect for quick skirmishes.', 140, 56, 'yew_bow.png'),
(25, 'Portable Crossbow', 'weapon', 'A mechanical crossbow rigged with gears. It takes muscle to crank the winch but fires with lethal force.', 260, 104, 'portable_crossbow.png'),
(26, 'Black Bow', 'weapon', 'A longbow carved from obsidian. Imbued with specialized craftsmanship that allows arrows to be loosed in rapid succession, mimicking a shortbow\\\'s agility.', 1200, 480, 'black_bow.png'),
(27, 'Soul Caliber', 'weapon', 'An unique looking bow, with the power of a balista.', 2000, 800, 'soul_caliber.png'),
(28, 'Wooden Staff', 'weapon', 'Not intended for physical use!', 40, 16, 'wooden_staff.png'),
(29, 'Glintstone Staff', 'weapon', 'A precise wand tipped with a raw glintstone shard. Greatly accelerates spellcasting precision.', 240, 96, 'glintstone_staff.png'),
(30, 'Guide Vodoo Doll', 'weapon', 'You are a terrible person...', 500, 200, 'guide_vodoo_doll.png'),
(31, 'Branch Of Ydgrsill', 'weapon', 'Isn\\\'t this the same as the wooden staff...', 700, 280, 'branch_of_ydgrsill.png'),
(32, 'Comically Large Spoon', 'weapon', 'Only a spoonfull...', 1200, 480, 'comically_large_spoon.png'),
(33, 'Vanguard Kettle Helm', 'helmet', 'A classic steel helm offering an excellent field of vision and solid cranium protection for frontline recruits.', 100, 40, 'vanguard_kettle_helm.png'),
(34, 'Flanged Winged Barbute', 'helmet', 'An elegant, aerodynamically reinforced helmet. The side flanging slightly improves momentum during heavy charges.', 220, 88, 'flanged_winged_barbute.png'),
(35, 'True Knight\\\'s Helm', 'helmet', 'You a real one if you\\\'re wearing this!', 420, 168, 'true_knights_helm.png'),
(36, 'Paladin\\\'s Helmet', 'helmet', 'Protects the mind against curses while physically shielding the skull.', 666, 266, 'paladins_helmet.png'),
(37, 'Leather Cap', 'helmet', 'Protects one from the deadly ray of the sun.', 110, 44, ''),
(38, 'Tactical Helmet', 'helmet', 'Wuh- what?', 500, 200, 'tactical_helmet.png'),
(39, 'Angel\\\'s Halo', 'helmet', 'I don\\\'t have a shotgun tho', 999, 399, 'angels_halo.png'),
(40, 'Archer\\\'s Hood', 'helmet', 'Makes you look cool and all.', 1200, 480, 'archershood.png'),
(41, 'Witch\\\'s Hat ', 'helmet', 'It\\\'s a Witch hat! with two holes on the top...', 120, 48, 'witchs_hat.png'),
(42, 'Time Circlet', 'helmet', 'A brass band with gears that accelerates casting velocity.', 420, 168, 'time_circlet.png'),
(43, 'Sage Crown', 'helmet', 'A crown of heavy glintstone. Grants massive power but drains physical stamina.', 750, 300, 'sage_crown.png'),
(44, 'Leather Tunic', 'armor', 'Works!', 120, 48, 'leather_tunic.png'),
(45, 'Chainmail', 'armor', 'A shirt of linked steel rings. Solid physical protection for a vanguard.', 200, 80, 'chainmail.png'),
(46, 'Leather Coat', 'armor', 'Reinforced leather tailored for flexibility and quick movement.', 180, 72, 'leather_coat.png'),
(47, 'Camo Tunic', 'armor', 'A lightweight, dark fabric tunic that makes it easy to slip through the shadows.', 380, 152, 'camo_tunic.png'),
(48, 'Sage Vestmets', 'armor', 'Elegant robes worn by high-ranking spellcasters. Greatly amplifies mental focus.', 420, 168, 'sage_vestmets.png'),
(49, 'Apprentice Robe', 'armor', 'A simple cloth robe woven with faint traces of mana-infused thread.', 160, 64, 'apprentice_robe.png'),
(50, 'Iron Greaves', 'boots', 'Heavy iron leg guards that protect your shins and anchors your stance.', 90, 36, 'iron_greaves.png'),
(51, 'Steel Boots', 'boots', 'Thick plate boots designed to absorb massive impact forces.', 200, 80, 'steel_boots.png'),
(52, 'Leather Boots', 'boots', 'Soft, broken-in leather boots that allow for silent footfalls.', 80, 32, 'leather_boots.png'),
(53, 'Sandals', 'boots', 'Very aerodynamic', 220, 88, 'sandals.png'),
(54, 'Cloth Shoes', 'boots', 'Simple cloth wrappings that provide minimal physical protection but keep the mind clear.', 75, 30, 'cloth_boots.png'),
(55, 'Squeaky Slippers', 'boots', 'It makes cute noises when squished.', 210, 84, 'squeaky_slippers.png'),
(56, 'Memento', 'accessory', 'This reminds you of someone...', 200, 80, 'memento.png'),
(57, 'Cool Mask', 'accessory', 'Pretty cool looking mask, wouldn\\\'t mind if a thief robbed my house in this.', 400, 160, 'cool_mask.png'),
(58, 'The Perfect Twenty', 'accessory', 'It\\\'s Perfect. Perfect. Down to the last minute detail.', 20000, 8000, 'the_perfect_twenty.png'),
(59, 'Dog Tag', 'accessory', 'An accessory of war', 1500, 600, 'dog_tag.png'),
(60, 'Eyeglasses', 'accessory', 'You can now see better', 2000, 800, 'eyeglasses.png');

-- --------------------------------------------------------

--
-- Table structure for table `item_attributes`
--

CREATE TABLE `item_attributes` (
  `id_item_attributes` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `att_max_hp` int(11) DEFAULT 0,
  `att_heal` int(11) DEFAULT 0,
  `att_str` int(11) DEFAULT 0,
  `att_def` int(11) DEFAULT 0,
  `att_dex` int(11) DEFAULT 0,
  `att_int` int(11) NOT NULL DEFAULT 0,
  `att_fth` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_attributes`
--

INSERT INTO `item_attributes` (`id_item_attributes`, `item_id`, `att_max_hp`, `att_heal`, `att_str`, `att_def`, `att_dex`, `att_int`, `att_fth`) VALUES
(1, 1, 0, 5, 0, 0, 0, 0, 0),
(2, 2, 0, 10, 0, 0, 0, 0, 0),
(3, 3, 0, 20, 0, 0, 0, 0, 0),
(4, 4, 0, 100, 0, 0, 0, 0, 0),
(5, 5, 0, 150, 0, 0, 0, 0, 0),
(6, 6, 0, 200, 0, 0, 0, 0, 0),
(7, 7, 0, 500, 0, 0, 0, 0, 0),
(8, 8, 25, 0, 0, 0, 0, 0, 0),
(9, 9, 50, 0, 0, 0, 0, 0, 0),
(10, 10, 50, 200, 0, 0, 0, 0, 0),
(11, 11, 0, 0, 2, 0, 0, 0, 0),
(12, 12, 20, 0, 1, 1, 1, 1, 1),
(13, 13, 2, 0, 2, 2, 2, 2, 2),
(14, 14, 0, 0, 2, 0, 0, 0, 0),
(15, 15, 0, 0, 0, 0, 2, 0, 0),
(16, 16, 0, 0, 0, 0, 0, 2, 0),
(17, 17, 0, 0, 0, 0, 0, 0, 2),
(18, 18, 0, 0, 5, 0, 1, 0, 0),
(19, 19, 0, 0, 2, 0, 2, 0, 0),
(20, 20, 0, 0, 8, 0, -1, 0, 0),
(21, 21, 0, 0, 2, 0, 4, 0, 0),
(22, 22, 0, 0, 12, 0, -2, 0, 2),
(23, 23, 0, 0, 4, 0, 2, 0, 0),
(24, 24, 0, 0, 2, 0, 8, 0, 0),
(25, 25, 0, 0, 6, 0, 3, 0, 0),
(26, 26, 0, 0, 7, 0, 15, 0, 0),
(27, 27, 0, 0, 20, 0, 5, 8, 0),
(28, 28, 0, 0, 1, 0, 0, 4, 0),
(29, 29, 0, 0, 1, 0, 2, 8, 0),
(30, 30, 0, 0, 0, 0, 0, 15, 2),
(31, 31, 0, 0, 1, 0, 5, 17, 4),
(32, 32, 0, 0, 7, 2, -2, 19, 0),
(33, 33, 15, 0, 0, 3, 0, 0, 0),
(34, 34, 25, 0, 2, 6, 0, 0, 0),
(35, 35, 50, 0, 0, 12, -3, 0, 0),
(36, 36, 60, 0, 0, 9, 0, 0, 4),
(37, 37, 20, 0, 0, 1, 3, 0, 0),
(38, 38, 30, 0, 0, 2, 7, 10, 0),
(39, 39, 20, 0, 0, 0, 15, 4, 7),
(40, 40, 0, 0, 0, 0, 20, 2, 0),
(41, 41, 10, 0, 0, 1, 0, 4, 0),
(42, 42, 10, 0, 0, 0, 4, 8, 0),
(43, 43, 0, 0, -4, 0, 3, 14, 0),
(44, 44, 20, 0, 0, 2, 2, 0, 0),
(45, 45, 25, 0, 0, 6, -1, 0, 0),
(46, 46, 15, 0, 0, 3, 5, 0, 0),
(47, 47, 0, 0, 2, 4, 9, 0, 0),
(48, 48, 0, 0, 0, 2, 0, 10, 5),
(49, 49, 10, 0, 0, 0, 0, 5, 0),
(50, 50, 10, 0, 0, 3, 0, 0, 0),
(51, 51, 25, 0, 1, 6, 0, 0, 0),
(52, 52, 0, 0, 0, 1, 3, 0, 0),
(53, 53, 0, 0, 1, 0, 7, 0, 0),
(54, 54, 5, 0, 0, 0, 0, 2, 0),
(55, 55, 0, 0, 0, -2, 3, 5, 3),
(56, 56, 10, 0, 2, 0, 0, 2, 2),
(57, 57, 10, 0, 0, 0, 4, 5, 0),
(58, 58, 20, 0, 20, 20, 20, 20, 20),
(59, 59, 0, 0, 10, 0, 0, 0, 0),
(60, 60, 0, 0, 0, 0, 0, 10, 0);

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
  `class_id` int(11) NOT NULL,
  `id_skill_attributes` int(22) NOT NULL,
  `skill_name` varchar(200) NOT NULL,
  `lvl_required` int(11) NOT NULL,
  `mana_cost` int(11) NOT NULL DEFAULT 0,
  `cooldown` int(20) NOT NULL,
  `skill_area` int(11) NOT NULL,
  `skill_desc` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `obtainable_skill`
--

INSERT INTO `obtainable_skill` (`skill_id`, `class_id`, `id_skill_attributes`, `skill_name`, `lvl_required`, `mana_cost`, `cooldown`, `skill_area`, `skill_desc`) VALUES
(1, 2, 1, 'Arrow Rain', 3, 2, 3, 5, 'Shoot a barrage of arrows into the air, dealing 80% of attack in a large area.'),
(3, 2, 2, 'Shoot', 1, 0, 0, 1, 'Deal 100% atk to 1 enemy'),
(5, 1, 3, 'Strike', 0, 0, 0, 1, 'Deal dmg worth of 100% atk to one enemy');

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
  `class_id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `name` varchar(10) NOT NULL,
  `level` int(11) NOT NULL,
  `exp` int(11) NOT NULL,
  `gold` int(20) NOT NULL,
  `created_at` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `player`
--

INSERT INTO `player` (`player_id`, `class_id`, `id_user`, `name`, `level`, `exp`, `gold`, `created_at`) VALUES
(1, 2, 1, 'Kyuri', 43, 0, 0, '2026-05-18 23:44:05'),
(3, 1, 1, 'Fuii', 1, 0, 0, '2026-05-19 07:38:14'),
(4, 2, 1, 'Elfya', 1, 0, 0, '2026-05-19 19:52:42'),
(5, 3, 1, 'Kiyti', 1, 0, 0, '2026-05-19 19:52:59'),
(6, 2, 2, 'Rika', 1, 0, 0, '2026-05-20 13:01:20'),
(7, 2, 1, 'Mimi', 13, 82, 0, '2026-05-21 12:46:01'),
(8, 2, 1, 'Cyrea', 2, 41, 0, '2026-06-02 03:36:47'),
(9, 1, 1, 'Fuii', 1, 0, 0, '2026-06-02 07:00:34'),
(12, 2, 1, 'Tester', 1, 0, 200, '2026-06-04 11:46:38'),
(13, 2, 1, 'Tester', 1, 0, 200, '2026-06-04 11:47:33'),
(14, 2, 1, 'das', 1, 0, 200, '2026-06-04 11:49:33'),
(15, 2, 1, 'hi', 1, 0, 200, '2026-06-04 13:06:12'),
(16, 2, 1, 'debug', 1, 0, 120, '2026-06-04 14:07:37'),
(17, 1, 1, 'Game Works', 1, 0, 160, '2026-06-04 14:30:19'),
(18, 2, 4, 'Rika', 1, 0, 144, '2026-06-04 18:56:44'),
(19, 1, 4, 'Carter', 1, 0, 116, '2026-06-04 18:57:52');

-- --------------------------------------------------------

--
-- Table structure for table `player_equipment`
--

CREATE TABLE `player_equipment` (
  `slot_id` int(11) NOT NULL,
  `bag_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `slot_name` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `player_equipment`
--

INSERT INTO `player_equipment` (`slot_id`, `bag_id`, `player_id`, `slot_name`) VALUES
(6, 1, 17, 'helmet'),
(7, 2, 19, 'boots'),
(8, 7, 18, 'armaments');

-- --------------------------------------------------------

--
-- Table structure for table `player_stats`
--

CREATE TABLE `player_stats` (
  `player_stat_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `curr_max_hp` int(11) NOT NULL,
  `curr_str` int(11) NOT NULL,
  `curr_def` int(11) NOT NULL,
  `curr_dex` int(11) NOT NULL,
  `curr_int` int(11) NOT NULL,
  `curr_fth` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `player_stats`
--

INSERT INTO `player_stats` (`player_stat_id`, `player_id`, `curr_max_hp`, `curr_str`, `curr_def`, `curr_dex`, `curr_int`, `curr_fth`) VALUES
(1, 7, 360, 52, -42, 38, 0, 0),
(2, 8, 300, 35, 20, 0, 0, 0),
(3, 9, 400, 20, 35, 0, 0, 0),
(4, 13, 300, 20, 10, 25, 10, 0),
(5, 14, 300, 20, 10, 25, 10, 0),
(6, 15, 300, 20, 10, 25, 10, 0),
(7, 16, 300, 20, 10, 25, 10, 0),
(8, 17, 400, 20, 30, 10, 5, 0),
(9, 18, 300, 20, 10, 25, 10, 0),
(10, 19, 400, 20, 30, 10, 5, 0);

-- --------------------------------------------------------

--
-- Table structure for table `runs`
--

CREATE TABLE `runs` (
  `run_id` int(11) NOT NULL,
  `current_node` varchar(255) NOT NULL,
  `player_id` int(11) NOT NULL,
  `run_seed` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL,
  `started_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `runs`
--

INSERT INTO `runs` (`run_id`, `current_node`, `player_id`, `run_seed`, `status`, `started_at`) VALUES
(1, 'node8', 7, 0, 0, '2026-06-01 17:03:34'),
(2, 'node1', 1, 0, 0, '2026-06-01 17:19:08'),
(3, 'node1', 3, 0, 0, '2026-06-01 17:22:35'),
(4, 'node1', 5, 0, 0, '2026-06-01 17:23:00'),
(5, 'node10', 8, 0, 0, '2026-06-02 09:37:09'),
(6, 'node1', 9, 0, 0, '2026-06-02 13:00:52'),
(7, 'node1', 13, 74325488, 1, '2026-06-04 17:47:33'),
(8, 'node1', 14, 66492351, 0, '2026-06-04 17:49:33'),
(9, 'node3', 14, 53380097, 1, '2026-06-04 17:53:44'),
(10, 'node1', 15, 94473198, 0, '2026-06-04 19:06:12'),
(11, 'node21', 15, 50136608, 1, '2026-06-04 19:06:17'),
(12, 'node1', 16, 80148449, 0, '2026-06-04 20:07:37'),
(13, 'node1', 16, 53498649, 0, '2026-06-04 20:07:44'),
(14, 'node1', 17, 36035888, 0, '2026-06-04 20:30:19'),
(15, 'node16', 17, 45448880, 0, '2026-06-04 20:30:25'),
(16, 'node1', 18, 80102885, 0, '2026-06-05 00:56:44'),
(17, 'node1', 18, 51839395, 0, '2026-06-05 00:56:47'),
(18, 'node1', 19, 72794760, 0, '2026-06-05 00:57:52'),
(19, 'node55', 19, 43076278, 0, '2026-06-05 00:57:57'),
(20, 'node25', 16, 72664134, 0, '2026-06-05 16:44:51'),
(21, 'node1', 16, 99783779, 1, '2026-06-06 09:40:08'),
(22, 'node4', 17, 30430068, 1, '2026-06-06 11:29:57'),
(23, 'node7', 19, 4561876, 0, '2026-06-06 16:25:59'),
(24, 'node1', 19, 881557, 1, '2026-06-07 04:10:18'),
(25, 'node3', 18, 39543612, 1, '2026-06-07 04:10:37');

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
(1, 31, 1),
(2, 49, 1),
(3, 40, 1),
(4, 3, 1),
(5, 47, 1),
(6, 45, 1),
(7, 14, 1),
(8, 54, 1),
(9, 57, 1),
(10, 59, 1),
(11, 16, 1),
(12, 7, 1),
(13, 60, 1),
(14, 34, 1),
(15, 17, 1),
(16, 29, 1),
(17, 10, 1),
(18, 1, 1),
(19, 30, 1),
(20, 5, 1),
(21, 13, 1),
(22, 18, 1),
(23, 50, 1),
(24, 52, 1),
(25, 37, 1),
(26, 46, 1),
(27, 44, 1),
(28, 15, 1),
(29, 8, 1),
(30, 56, 1),
(31, 4, 1),
(32, 36, 1),
(33, 25, 1),
(34, 6, 1),
(35, 2, 1),
(36, 21, 1),
(37, 43, 1),
(38, 48, 1),
(39, 53, 1),
(40, 23, 1),
(41, 27, 1),
(42, 11, 1),
(43, 55, 1),
(44, 51, 1),
(45, 38, 1),
(46, 58, 1),
(47, 42, 1),
(48, 35, 1),
(49, 33, 1),
(50, 20, 1),
(51, 9, 1),
(52, 12, 1),
(53, 41, 1),
(54, 28, 1),
(55, 19, 1),
(56, 24, 1);

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
  `skill_str` int(11) NOT NULL COMMENT 'the numbers meant percentage wise',
  `skill_def` int(11) NOT NULL,
  `skill_heal` int(11) NOT NULL,
  `skill_dex` int(11) NOT NULL,
  `skill_int` int(11) NOT NULL,
  `skill_fth` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skill_attributes`
--

INSERT INTO `skill_attributes` (`id_skill_attributes`, `skill_str`, `skill_def`, `skill_heal`, `skill_dex`, `skill_int`, `skill_fth`) VALUES
(1, 30, 0, 0, 60, 10, 0),
(2, 80, 0, 0, 0, 0, 0),
(3, 100, 0, 0, 0, 0, 0);

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
(2, 'admin1', 'admin1'),
(3, 'Philia', 'seeyoutomorrow'),
(4, 'Seth', '123');

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
-- Indexes for table `choice_reward`
--
ALTER TABLE `choice_reward`
  ADD PRIMARY KEY (`id_choice_reward`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `event_options_id` (`event_options_id`);

--
-- Indexes for table `class`
--
ALTER TABLE `class`
  ADD PRIMARY KEY (`class_id`);

--
-- Indexes for table `detail_dialogue`
--
ALTER TABLE `detail_dialogue`
  ADD PRIMARY KEY (`detail_dialogue_id`),
  ADD KEY `event_dialogue_id` (`event_dialogue_id`);

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
-- Indexes for table `equipment_att_req`
--
ALTER TABLE `equipment_att_req`
  ADD PRIMARY KEY (`equip_req_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `event_dialogue`
--
ALTER TABLE `event_dialogue`
  ADD PRIMARY KEY (`id_event_dialogue`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `event_options`
--
ALTER TABLE `event_options`
  ADD PRIMARY KEY (`event_options_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `event_option_requirements`
--
ALTER TABLE `event_option_requirements`
  ADD PRIMARY KEY (`event_option_req_id`),
  ADD KEY `id_item` (`id_item`,`event_options_id`),
  ADD KEY `event_option_id` (`event_options_id`);

--
-- Indexes for table `event_text_output`
--
ALTER TABLE `event_text_output`
  ADD PRIMARY KEY (`event_text_output_id`),
  ADD KEY `event_option_id` (`event_option_id`);

--
-- Indexes for table `item`
--
ALTER TABLE `item`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `item_attributes`
--
ALTER TABLE `item_attributes`
  ADD PRIMARY KEY (`id_item_attributes`),
  ADD KEY `item_id` (`item_id`);

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
  MODIFY `bag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `choice_reward`
--
ALTER TABLE `choice_reward`
  MODIFY `id_choice_reward` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `class`
--
ALTER TABLE `class`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `detail_dialogue`
--
ALTER TABLE `detail_dialogue`
  MODIFY `detail_dialogue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `dropped_items`
--
ALTER TABLE `dropped_items`
  MODIFY `item_drop_id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `equipment_att_req`
--
ALTER TABLE `equipment_att_req`
  MODIFY `equip_req_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event_dialogue`
--
ALTER TABLE `event_dialogue`
  MODIFY `id_event_dialogue` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event_options`
--
ALTER TABLE `event_options`
  MODIFY `event_options_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `event_option_requirements`
--
ALTER TABLE `event_option_requirements`
  MODIFY `event_option_req_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `event_text_output`
--
ALTER TABLE `event_text_output`
  MODIFY `event_text_output_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `item`
--
ALTER TABLE `item`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `item_attributes`
--
ALTER TABLE `item_attributes`
  MODIFY `id_item_attributes` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `nodes`
--
ALTER TABLE `nodes`
  MODIFY `node_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `obtainable_skill`
--
ALTER TABLE `obtainable_skill`
  MODIFY `skill_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `obtained_skill`
--
ALTER TABLE `obtained_skill`
  MODIFY `obtained_skill_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `player`
--
ALTER TABLE `player`
  MODIFY `player_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `player_equipment`
--
ALTER TABLE `player_equipment`
  MODIFY `slot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `player_stats`
--
ALTER TABLE `player_stats`
  MODIFY `player_stat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `runs`
--
ALTER TABLE `runs`
  MODIFY `run_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `shop_item`
--
ALTER TABLE `shop_item`
  MODIFY `shop_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `shop_pool`
--
ALTER TABLE `shop_pool`
  MODIFY `shop_pool_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `skill_attributes`
--
ALTER TABLE `skill_attributes`
  MODIFY `id_skill_attributes` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Constraints for table `choice_reward`
--
ALTER TABLE `choice_reward`
  ADD CONSTRAINT `choice_reward_ibfk_1` FOREIGN KEY (`event_options_id`) REFERENCES `event_options` (`event_options_id`),
  ADD CONSTRAINT `choice_reward_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`);

--
-- Constraints for table `detail_dialogue`
--
ALTER TABLE `detail_dialogue`
  ADD CONSTRAINT `detail_dialogue_ibfk_1` FOREIGN KEY (`event_dialogue_id`) REFERENCES `event_dialogue` (`id_event_dialogue`);

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
-- Constraints for table `equipment_att_req`
--
ALTER TABLE `equipment_att_req`
  ADD CONSTRAINT `equipment_att_req_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`);

--
-- Constraints for table `event_dialogue`
--
ALTER TABLE `event_dialogue`
  ADD CONSTRAINT `event_dialogue_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`);

--
-- Constraints for table `event_options`
--
ALTER TABLE `event_options`
  ADD CONSTRAINT `event_options_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`);

--
-- Constraints for table `event_option_requirements`
--
ALTER TABLE `event_option_requirements`
  ADD CONSTRAINT `event_option_requirements_ibfk_1` FOREIGN KEY (`id_item`) REFERENCES `item` (`item_id`),
  ADD CONSTRAINT `event_option_requirements_ibfk_2` FOREIGN KEY (`event_options_id`) REFERENCES `event_options` (`event_options_id`);

--
-- Constraints for table `event_text_output`
--
ALTER TABLE `event_text_output`
  ADD CONSTRAINT `event_text_output_ibfk_1` FOREIGN KEY (`event_option_id`) REFERENCES `event_options` (`event_options_id`);

--
-- Constraints for table `item_attributes`
--
ALTER TABLE `item_attributes`
  ADD CONSTRAINT `item_attributes_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`);

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
