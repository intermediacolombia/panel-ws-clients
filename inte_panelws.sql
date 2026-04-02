-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 02-04-2026 a las 16:01:06
-- Versión del servidor: 10.11.15-MariaDB-log
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `inte_panelws`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agents`
--

CREATE TABLE `agents` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `wa_alerts` tinyint(1) NOT NULL DEFAULT 0,
  `role` enum('supervisor','agente') NOT NULL DEFAULT 'agente',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_seen` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fcm_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `agents`
--

INSERT INTO `agents` (`id`, `username`, `password`, `name`, `email`, `phone`, `wa_alerts`, `role`, `status`, `last_seen`, `created_at`, `updated_at`, `fcm_token`) VALUES
(1, 'intermedia', '$2y$10$p/ooQDpKLx2q6lcCuDlEKuEdl2dpFncfnvDtOp52t0cXsqcjiHz5q', 'Intermedia Host', 'admin@intermediahost.co', '573147165269', 1, 'supervisor', 'active', '2026-04-02 11:01:05', '2026-03-28 16:17:51', '2026-04-02 11:01:05', 'djhOK7F7SDyPCu4DlPghvc:APA91bHfBSY1BQcjbOPksYXiRTjkgZhXntIhDBJsVCMnQVGy6_xENw3FonR5HcXvwJ4Sm6e4jsL6PzqOSi6wuhUtbzlI6IeE88991-XJc6DFgkNccBZbkeg'),
(2, 'ventas1', '$2y$10$CCNsNHQ2s/v1cRWsc8hsQ.7KhiP7pRxQ6KWMkpYxEKepDfPL3E7He', 'Asesor Ventas 1', 'ventas1@intermediahost.co', NULL, 0, 'agente', 'active', '2026-03-29 19:15:11', '2026-03-28 16:17:51', '2026-03-29 19:15:11', 'fYpkWiwjSWCkpHesCpQ_4a:APA91bFm2d7egb357Xj5x7XQBmWQnOxJLtW8TVDy1pEbgXdXiecOiXjNEJNfC8DYrHZ6hfoiBi0c3Rp1UsdLTH9jJgm5fMGsgMu0q4DhBshnfUHMGLwEoeg'),
(3, 'ventas2', '$2y$10$hpXo4UfYc1qm0zIouHjriOiswy6j0f6JqY4P.k0GGJcxZRkiY/era', 'Asesor Ventas 2', 'ventas2@intermediahost.co', NULL, 0, 'agente', 'active', NULL, '2026-03-28 16:17:51', '2026-03-28 16:17:51', NULL),
(4, 'soporte1', '$2y$10$.9wxPUWUeKTsjXiE35nWuufeyu5IaDrYayL1Os0oHnrXi.cL9uS.O', 'Soporte Técnico 1', 'soporte1@intermediahost.co', NULL, 0, 'agente', 'active', NULL, '2026-03-28 16:17:51', '2026-03-28 16:17:51', NULL),
(5, 'soporte2', '$2y$10$.9wxPUWUeKTsjXiE35nWuufeyu5IaDrYayL1Os0oHnrXi.cL9uS.O', 'Soporte Técnico 2', 'soporte2@intermediahost.co', NULL, 0, 'agente', 'active', NULL, '2026-03-28 16:17:51', '2026-03-28 16:17:51', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agent_departments`
--

CREATE TABLE `agent_departments` (
  `agent_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `agent_departments`
--

INSERT INTO `agent_departments` (`agent_id`, `department_id`) VALUES
(1, 1),
(1, 2),
(2, 1),
(3, 1),
(4, 2),
(5, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agent_sessions`
--

CREATE TABLE `agent_sessions` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `agent_sessions`
--

INSERT INTO `agent_sessions` (`id`, `agent_id`, `token`, `ip`, `user_agent`, `expires_at`, `created_at`) VALUES
(4, 1, '5f4121c825c0d12637b35c9d4358ec300478bc2ccbd9c057ccd2d95c3819e576f8a8ea81f4dabba52bd2e210f4862911e1abaa60ec3073b05f65c8bbd96b903e', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 00:33:45', '2026-03-28 16:33:45'),
(5, 1, 'ffc07aebd6319991c4c81d19b060f9ea8d4881f997d1927ddf072ea246a797075d9adc417f17d3d7d68753668eb5a2e96aa79e15007881a3daf524a5c2a9aebe', '190.65.182.115', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-29 01:07:52', '2026-03-28 17:07:52'),
(6, 1, 'a7c0364dd48d43ca24a52654f81fdcc71fff9a62cd3b0e37ce55b819408825a1ec783ce27a2bd17228fc48d7bf7c05364c54660eae313526ded684a6592a22ef', '190.65.182.115', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Brave/1 Mobile/15E148 Safari/604.1', '2026-03-29 01:26:55', '2026-03-28 17:26:55'),
(7, 1, 'fc51936969898c9c40947b617507d369957df862c9b74c83f91663095deccb8e1b5ed2df554b37c6a49e8ba46e91097983d730abcf29454e7f1462688e727494', '190.65.182.115', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-29 01:28:22', '2026-03-28 17:28:22'),
(8, 2, 'b0ac24b9dfe4c4d83405c55a54808d60660806201457e5344936e62bb7a111ce57b81cbc124863d59b5780c372aed43e093fc0f6c71b46af68d47e09ca15f5ba', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 01:35:19', '2026-03-28 17:35:19'),
(11, 1, 'da1373f10039e2ffcd44f84f66e55d48073dd341de2ec0e276f9bff09a848f745b97992b4bcf099d20ddedc11e496c6d1df878787256620d7acb223f0a2d0265', '186.82.14.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 05:54:04', '2026-03-28 21:54:04'),
(13, 1, '5ce7547193afb556b94a6e1cb9e1629ce083498dc34128593a118781c87ed69749a0a06dcb8bd23cf0e61abd84d8473e7bad06b445ffc7552ab32ebf531f2b42', '186.82.14.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 06:02:45', '2026-03-28 22:02:45'),
(14, 1, '1771498391717d09bb33a97774ddd0ad7e8db11e256ba9c6abc09eb2507677b5007f2e738e74587ac59282bb0a03769c5f952ece689f68ed8ebc6b55ddf05c61', '186.82.14.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 06:10:37', '2026-03-28 22:10:37'),
(15, 1, '4430ddc007c49bd2ed9ded3da25ad5b8b408739764ca6be574bb10c219175640db4dbce3faca2133ea3ea6339ab7a72016200ddca99460b2cd3f7c5c174e4164', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 06:26:54', '2026-03-28 22:26:54'),
(16, 2, '43eb55473190fd7651f0d6cf0c45f1e8ca48c4a5a07f62d4043de01222cdd3a5d90791a3b4e088c7c551ecb7bec87d5c040efad2bee98cbd061b305b152da99f', '186.82.14.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 06:27:44', '2026-03-28 22:27:44'),
(18, 1, '54715568119b840029efe450d0e5993f48f97d588f3f97f88618b416e48a125e95f17ab35eba7ba25ec1591d6553de7838cbd6d7d42cc13b3780db37860f99af', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 06:49:59', '2026-03-28 22:49:59'),
(20, 1, 'd8ca038171da2a7f5076225462cbac2b71168d88e5c4cb02ca27d6697f03531cd10b946f13819e3d23ef725fe96c5e8119643a1807abbb11bde4925f83c532de', '186.82.14.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 10:43:23', '2026-03-29 02:43:23'),
(21, 1, '7de9fc7e9103d8ca468014c4227add2eda23ebb51268c42b87c82dffe7e5e25bdc1725578ef8d7db2f8c9bb64f2b12c28a75badfa8ade28ebcb584f643ff196b', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 10:46:38', '2026-03-29 02:46:38'),
(22, 1, '7deb7d6b84eb84f2a6878dc58d36f8e610148dbab557ef0c66888e87e294f838aed102a5c1c77c6ea19389ea4e37d406a6c7f45a21f7e755045967b613d9431d', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 10:48:00', '2026-03-29 02:48:00'),
(24, 1, '1a290a7584d4058a70829393e5a01a3f97db03296b2a55b09b911c631a62cc47ded53bfd87062ec9f2ee7d8eb454b802d8c049e03d254aa8eb4ab2003cff199a', '186.82.14.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-29 11:19:36', '2026-03-29 03:19:36'),
(25, 1, 'd11e3d859e6e426ff62faffa169cd229ea71421a17a5afa6a322ba3c6e2f76949c59b22dc451a44c224779cf885eccb3f6dfbfe44b40e31f46260d6b28b7f2f6', '186.82.14.97', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-29 11:27:54', '2026-03-29 03:27:54'),
(26, 1, 'e04626d78c5fc90c17d283662842b5273b6508e3053a2422a6e6915035ffb41f66cf6d68016739dc16f81540685a9bf67048f457c3e28b43bb5b8a5ec3d9ef08', '186.82.14.97', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-29 11:31:16', '2026-03-29 03:31:16'),
(27, 1, 'b7d073f308e59be51cac5688428ef60649b71f4738afb55b046a8cbbc0d6c836bcb8f1a2b3df9f7755ec9321162a85e06aecf9101f19449205a6b3dc4bdf910f', '190.65.182.115', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Brave/1 Mobile/15E148 Safari/604.1', '2026-03-29 12:20:08', '2026-03-29 04:20:08'),
(28, 1, '0e48e56d0121abf06ab41e9f6a111af05ee651f68847189cc6b181a8e26dfcc2b4f1cf08a481057e57a1cce8b69a9e2a4c48c92c7ad7785d531ce2cc146f76d2', '190.65.182.115', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-29 19:30:57', '2026-03-29 11:30:57'),
(29, 1, 'ead27e0b80e757c4bf36a92a05405a369a28550e0f37047bc10554e1951d8164262e4d5028933f004c93094736eb0e7d5566ece3af6edb8ac64ce5fa54418110', '190.65.182.115', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-30 03:20:54', '2026-03-29 11:31:48'),
(32, 1, '3ad887e3b9102f0c231f3488eaaeaa2cf17d2099f2be042a01791c14daaebd1941ec55e25a5d9fdf5ed42baa7e848b839944d55adb32f9cf356ae7f2106db3eb', '190.130.109.206', 'Dart/3.11 (dart:io)', '2026-03-29 21:56:49', '2026-03-29 13:56:49'),
(34, 1, '7cb867fa65e95dd8feb6f3d4610c3cd36653879597fcb4a61ce4a05b25ca99a6f5e9306f462f4cf6f56d0ea6cb19ebd19e8337bbc9d47c15e790ad4eba928c65', '190.130.109.206', 'Dart/3.11 (dart:io)', '2026-03-29 23:47:22', '2026-03-29 15:47:22'),
(36, 1, '4b726b04cc5a4d0b5dedd32f6fe701ba334aa9ff200c55e0a192a2e0f1d01441bb4e754aa1d91f31ca71f0ff1f1659759778d319bc21dfa966a6f5c2d01c6d0a', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 00:45:15', '2026-03-29 16:45:15'),
(41, 2, '80a1c1df47bdc3dcd2d84f5d93c0d06f1bc63e6382c8055eaaac60ecf7cdcf030bccd9421c9f5325e22d510ae4994307b14cd3856a450fb5fbb4008bf64d02e5', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 02:49:48', '2026-03-29 18:49:48'),
(43, 1, '36a195d1b8913e86b770e0794e165ed83362b832f4bbc70d944e2b07708ccf7db51ef53b5c413fdd1a18998c7fa4bab058f3d29a82c9830e2c2851eb3abd4939', '190.65.182.115', 'Dart/3.11 (dart:io)', '2026-03-30 03:04:06', '2026-03-29 19:04:06'),
(44, 1, 'a751ca2d3ebe40a1a83557748762ce123f3bd19aadc753f8f686b6b0da92babbb9eb5c985accda1f950899246b0af9ed9fe42298e87e14db1b8592374d450252', '190.65.182.115', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Brave/1 Mobile/15E148 Safari/604.1', '2026-03-30 07:03:10', '2026-03-29 23:03:10'),
(45, 1, '496b9b3805fc32ccae0a99aa3c2d7d02970061f82069e5441dc710c240b1270e6c990adf80b77a41ac9aae94a3399e602da67d5f194ec2e5e5344a91dabed7c8', '190.65.182.115', 'Dart/3.11 (dart:io)', '2026-04-29 13:42:37', '2026-03-30 08:47:36'),
(46, 1, '32818ce351d5bb6b6cca3e4011e6bf361b22c62f331459251da82d71fdcd2bb2a47c9309b2ccfaac84bc3edf7559a627a77bee3d1babcfd8ecac134cd692749a', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-29 10:13:02', '2026-03-30 10:13:02'),
(48, 1, 'e39737f4cda99641456dbc504251c28bc051d4c6b7d63fef8fd0d7942087f7ec88e5c16f57fba642494cacfed81aa0076a401cb4c24f9bcf26e81ab4ae30374a', '190.65.182.115', 'Dart/3.11 (dart:io)', '2026-04-29 15:12:42', '2026-03-30 15:12:42'),
(49, 1, 'f33eff3ea4a86f19244a615879f3bd71cbc2181acc1f541b27ebfb4c67fed0b12f34873714b3b5d24b9ee2a5271404dc46258d562d3cc6c8e972e1ba3f38ec63', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 13:23:06', '2026-03-31 13:23:06'),
(50, 1, '294b73b7f30a3e165786bea748f72931106a1e71b447747b55f6f9f6c06ef180090f2a5b91a8d6393b23006faeffb5b08d6480bfebfb34080f073e322daf5bc1', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 13:24:06', '2026-03-31 13:24:06'),
(51, 1, '55d74ee6d9be9dac776f205448c94f52c10b02337366f6dc2d296bb2faec1e9acc99e02a716ff24e73f99aa033746cacce02c0c13bf07e5ae0b200eecb8384e5', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 14:37:11', '2026-03-31 14:37:11'),
(52, 1, 'ec854511cc569f1fdbfff8bdfcabec9135f76d5068dbb82c7cabdbf3693acd20a1bd399ed9836e9d8fb1e20370c88030e8191487062cbb615d12c8188561c5f0', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 14:39:53', '2026-03-31 14:39:53'),
(53, 1, 'fac5be776adbac3d2562319379c3dbc30e39fca9e58bf872a60e9c21e1cb5c3c0eea09b82a3a8997619982f6495b96f54f7a3b1abf55d9159fa75b9ac411ab80', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 14:44:54', '2026-03-31 14:44:54'),
(54, 1, 'cf6c9118b9d6fe9bed306ad1b2312a0d85c9eb037b44ef2360356b71ce7adcc6be26e922c9aabc14b97d590b69084e2fa5b9c85337b96bf5cc6ef16ccd531cc0', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 14:48:40', '2026-03-31 14:48:40'),
(55, 1, 'c16d0de9680ac89d76bd5fc47daf0095b74bb2ce56ecc36b4cf0e3d1d71f1e4972c8bc6b26c747095b4d2c44e0cd71b999f21ee384b3550c6dc4983446bed0e5', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 14:53:02', '2026-03-31 14:53:02'),
(56, 1, '477f17e8c2c2b295f2b35b9226fd2722929edcb479180c7cab26def47fa5a571b9335dd71ae505a6165a5e61c2342468e7aeaf50574d4351f7d7474706144a57', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 14:56:34', '2026-03-31 14:56:34'),
(57, 1, '07df3759e066cb8d8d6bc7d677444b3adf336f8650e4435057c607fb1b84e5fb4750db26cceafe0d490d93bd0f3e527e8a56c005266a5a36f6f2dea4b97f705e', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 15:02:22', '2026-03-31 15:02:22'),
(58, 1, '8722d166926f6ff4811294cc89d056807b007b349876a0b87219f866afe44347dd0ed79f7596a683cb669f22a4b542ed6eab7f8011e285622935ad6b8a2dac7b', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 15:13:28', '2026-03-31 15:13:28'),
(59, 1, '396d6f70fa570dca448dc9af5a9bdda77eafcf8f0bdfa4c4f5fd9f8435af3e8f99182be261acf5f673f1a6d0f28028268aad64d731f7f62133fa88dbe17349a8', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 15:18:33', '2026-03-31 15:18:33'),
(60, 1, '62b24ee483257a923d5f44312ab8d4ec7dd3225f6620e070bce8419b41e3fd9d9ccb9d4191e375cc80c17023035072e2f53a6f4d336e0b0161b9da717194158e', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-30 15:23:41', '2026-03-31 15:23:41'),
(62, 1, '608811c1f33a9cf043567641a78807e13c397dcf3a4d97c369ee4cf0ee5afc795febb03088db8911f231120b88ab40de274deebce0beb7a66621aaf4ca5b6c5d', '190.65.182.115', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-30 18:16:53', '2026-03-31 18:16:53'),
(65, 1, '1d3ff1b5be6b51ce86c5b7530d73725329d628cc82467d7c4c72a0525a28b1212220750d8926dd6306ac1633a2597321ce2fa504c1df914cd6af446f65a54727', '190.65.182.115', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-05-01 14:43:18', '2026-04-01 14:43:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bot_estados`
--

CREATE TABLE `bot_estados` (
  `ses_key` varchar(120) NOT NULL,
  `estado` varchar(50) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT json_array() CHECK (json_valid(`data`)),
  `timestamp` int(11) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `bot_estados`
--

INSERT INTO `bot_estados` (`ses_key`, `estado`, `data`, `timestamp`, `updated_at`) VALUES
('51969033671_intermedia', 'menu_principal', '[]', 1774741257, '2026-03-28 18:40:57'),
('573014982176_intermedia', 'asesor', '[]', 1775055160, '2026-04-01 09:52:40'),
('573103009773_intermedia', 'menu_principal', '[]', 1775066626, '2026-04-01 13:03:46'),
('573105050444_intermedia', 'menu_principal', '[]', 1774985364, '2026-03-31 14:29:24'),
('573132607084_intermedia', 'asesor', '{\"servicio\":\"Streaming Radio\",\"area\":\"Soporte - Streaming Radio\"}', 1774999678, '2026-03-31 18:27:58'),
('573147165269_intermedia', 'menu_principal', '[]', 1774747953, '2026-03-28 20:32:33'),
('573148322881_intermedia', 'asesor', '{\"servicio\":\"Streaming Video\",\"area\":\"Ventas - Streaming Video\"}', 1774751562, '2026-03-28 21:32:42'),
('573163945490_intermedia', 'asesor', '[]', 1775054887, '2026-04-01 09:48:07'),
('573172998776_intermedia', 'menu_principal', '[]', 1775139889, '2026-04-02 09:24:49'),
('573181554666_intermedia', 'asesor', '{\"servicio\":\"Streaming AutoDJ\",\"area\":\"Ventas - Streaming AutoDJ\"}', 1775058689, '2026-04-01 10:51:29'),
('573204504609_intermedia', 'menu_principal', '[]', 1774970275, '2026-03-31 10:17:55'),
('573223409082_intermedia', 'asesor', '{\"servicio\":\"Streaming Radio\",\"area\":\"Soporte - Streaming Radio\"}', 1775049916, '2026-04-01 08:25:16'),
('573243845814_intermedia', 'asesor', '{\"consulta\":\"Gracias\",\"area\":\"Otros\"}', 1775088062, '2026-04-01 19:01:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `conv_key` varchar(120) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `contact_name` varchar(100) NOT NULL DEFAULT '',
  `client_id` varchar(50) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `area_label` varchar(150) NOT NULL DEFAULT '',
  `status` enum('pending','attending','resolved','bot') NOT NULL DEFAULT 'pending',
  `agent_id` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `first_contact_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_message_at` datetime NOT NULL DEFAULT current_timestamp(),
  `unread_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `conversations`
--

INSERT INTO `conversations` (`id`, `conv_key`, `phone`, `contact_name`, `client_id`, `department_id`, `area_label`, `status`, `agent_id`, `assigned_at`, `resolved_at`, `resolved_by`, `first_contact_at`, `last_message_at`, `unread_count`, `created_at`, `updated_at`) VALUES
(1, 'intermedia_573181554666', '573181554666', 'Edisson Medina', 'intermedia', 1, 'Ventas - Streaming AutoDJ', 'bot', NULL, NULL, NULL, 1, '2026-03-28 16:38:24', '2026-04-01 10:51:43', 0, '2026-03-28 16:38:24', '2026-04-01 12:18:49'),
(2, 'intermedia_573147165269', '573147165269', 'DJ EDME', 'intermedia', 1, 'Ventas - Streaming Radio', 'bot', NULL, NULL, NULL, 1, '2026-03-28 17:33:33', '2026-03-28 20:32:08', 0, '2026-03-28 17:33:33', '2026-03-28 20:32:18'),
(3, 'intermedia_573148322881', '573148322881', 'Leandra👸🏼', 'intermedia', 1, 'Ventas - Streaming Video', 'bot', NULL, NULL, NULL, NULL, '2026-03-28 21:32:42', '2026-03-28 21:33:30', 0, '2026-03-28 21:32:42', '2026-03-28 21:33:47'),
(8, 'intermedia_573172998776', '573172998776', 'EDME', 'intermedia', NULL, 'Ventas - Streaming Radio', 'bot', NULL, NULL, '2026-04-02 11:00:07', 1, '2026-03-28 22:50:23', '2026-04-02 10:46:16', 0, '2026-03-28 22:50:23', '2026-04-02 11:00:12'),
(10, 'intermedia_573243845814', '573243845814', 'Mi Princesa', 'intermedia', NULL, 'Otros', 'bot', NULL, NULL, NULL, 1, '2026-03-29 18:41:54', '2026-04-01 19:01:02', 0, '2026-03-29 18:41:54', '2026-04-01 19:01:50'),
(11, 'intermedia_573105050444', '573105050444', 'Emisora Cultural De Pereira', 'intermedia', NULL, 'Soporte - Streaming Radio', 'bot', NULL, NULL, '2026-03-31 13:17:13', 1, '2026-03-30 18:42:08', '2026-03-31 14:29:24', 0, '2026-03-30 18:42:08', '2026-03-31 14:30:50'),
(12, 'intermedia_573204504609', '573204504609', 'Luna Stereo', 'intermedia', 4, 'Otros', 'bot', NULL, NULL, NULL, NULL, '2026-03-31 09:38:16', '2026-03-31 10:17:55', 0, '2026-03-31 09:38:16', '2026-03-31 12:33:29'),
(13, 'intermedia_573132607084', '573132607084', 'Jaime Cardozo Xfe Stereo', 'intermedia', 2, 'Soporte - Streaming Radio', 'bot', NULL, NULL, NULL, NULL, '2026-03-31 18:27:58', '2026-03-31 18:27:58', 0, '2026-03-31 18:27:58', '2026-04-01 00:49:58'),
(14, 'intermedia_573014982176', '573014982176', 'Ian Carlos', 'intermedia', 2, 'Soporte - Streaming Radio', 'bot', NULL, NULL, '2026-04-01 09:59:58', 1, '2026-04-01 08:15:26', '2026-04-01 09:52:58', 0, '2026-04-01 08:15:26', '2026-04-01 10:00:01'),
(15, 'intermedia_573223409082', '573223409082', 'jpl publicidad', 'intermedia', 2, 'Soporte - Streaming Radio', 'bot', NULL, NULL, NULL, NULL, '2026-04-01 08:25:16', '2026-04-01 08:30:43', 0, '2026-04-01 08:25:16', '2026-04-01 09:12:22'),
(16, 'intermedia_573163945490', '573163945490', 'Emaurys', 'intermedia', 2, 'Soporte - Streaming Radio', 'bot', NULL, NULL, '2026-04-01 09:59:15', 1, '2026-04-01 09:02:20', '2026-04-01 09:59:18', 0, '2026-04-01 09:02:20', '2026-04-01 09:59:26'),
(17, 'intermedia_573103009773', '573103009773', '𝓗𝓮𝓷𝓻𝔂 𝓟𝓪𝓬𝓱𝓮𝓬𝓸 𝓒𝓪𝓼𝓪𝓭𝓲𝓮𝓰𝓸', 'intermedia', 2, 'Soporte - Streaming Radio', 'bot', NULL, NULL, NULL, NULL, '2026-04-01 11:02:26', '2026-04-01 11:15:38', 0, '2026-04-01 11:02:26', '2026-04-01 11:26:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#25D366',
  `icon` varchar(50) NOT NULL DEFAULT 'headset',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `departments`
--

INSERT INTO `departments` (`id`, `name`, `slug`, `description`, `color`, `icon`, `active`, `created_at`) VALUES
(1, 'Ventas', 'ventas', 'Área comercial', '#25D366', 'shopping-cart', 1, '2026-03-28 16:17:51'),
(2, 'Soporte Técnico', 'soporte', 'Asistencia técnica', '#3498DB', 'wrench', 1, '2026-03-28 16:17:51'),
(3, 'Medios de Pago', 'pagos', '', '#E67E22', 'credit-card', 1, '2026-03-28 16:17:51'),
(4, 'Otros', 'otros', 'Consultas generales', '#9B59B6', 'question-circle', 1, '2026-03-28 16:17:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip`, `attempted_at`) VALUES
(2, '186.82.14.97', '2026-03-28 21:08:08'),
(3, '186.82.14.97', '2026-03-28 21:08:19'),
(4, '186.82.14.97', '2026-03-28 22:28:35'),
(5, '190.130.109.206', '2026-03-29 13:55:36'),
(1, '190.65.182.115', '2026-03-28 16:19:33'),
(6, '190.65.182.115', '2026-03-29 18:32:11'),
(7, '190.65.182.115', '2026-03-29 19:03:56'),
(8, '190.65.182.115', '2026-03-29 23:02:33'),
(9, '190.65.182.115', '2026-03-29 23:03:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `type` enum('text','image','audio','document') NOT NULL DEFAULT 'text',
  `content` text NOT NULL,
  `file_url` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_mime` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `wa_message_id` varchar(100) DEFAULT NULL,
  `status` enum('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `error_detail` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `direction`, `type`, `content`, `file_url`, `file_name`, `file_mime`, `file_size`, `caption`, `agent_id`, `wa_message_id`, `status`, `error_detail`, `created_at`) VALUES
(1, 1, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 16:38:24'),
(2, 1, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 16:43:41'),
(3, 1, 'out', 'text', 'hola buena stardes, bienvenido', NULL, NULL, NULL, NULL, NULL, 1, '3EB093F1EA30598728658F', 'sent', NULL, '2026-03-28 16:45:33'),
(4, 1, 'out', 'text', 'Como podemos ayudarte?', NULL, NULL, NULL, NULL, NULL, 1, '3EB094A1F7431AAFB4164B', 'sent', NULL, '2026-03-28 16:45:52'),
(5, 1, 'out', 'text', 'claro que si, con que puedo ayudarte?', NULL, NULL, NULL, NULL, NULL, 1, '3EB03BE95FE7E11E474906', 'sent', NULL, '2026-03-28 16:48:06'),
(6, 1, 'out', 'image', 'activa_logo (1).png', 'https://panelws.intermediahost.co/uploads/1/f_69c84cde45b969.13442772_activa_logo__1_.png', 'activa_logo (1).png', 'image/png', 21580, NULL, 1, '3EB034077633980D2E0D46', 'sent', NULL, '2026-03-28 16:49:18'),
(7, 1, 'in', 'text', '3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 16:51:16'),
(8, 1, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 16:52:16'),
(9, 1, 'out', 'text', 'Hola Buena stardes', NULL, NULL, NULL, NULL, NULL, 1, '3EB0B941B3BD1F936C834A', 'sent', NULL, '2026-03-28 16:52:23'),
(10, 1, 'out', 'text', 'como podmeos ayudarte', NULL, NULL, NULL, NULL, NULL, 1, '3EB0175C93F9D874E33C2E', 'sent', NULL, '2026-03-28 16:52:39'),
(11, 1, 'in', 'text', 'Gracias 🤩', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 16:52:51'),
(12, 1, 'out', 'image', 'Esta es la Imagen', 'https://panelws.intermediahost.co/uploads/1/f_69c84e27efc880.82545373_portada_menu.png', 'portada menu.png', 'image/png', 154295, 'Esta es la Imagen', 1, '3EB0B298255A04CCEA0BCA', 'sent', NULL, '2026-03-28 16:54:47'),
(13, 1, 'out', 'text', 'Asi mismo puede funcionar', NULL, NULL, NULL, NULL, NULL, 1, '3EB0EEA80320F2C340A773', 'sent', NULL, '2026-03-28 16:55:11'),
(14, 1, 'in', 'image', '[image]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 16:55:28'),
(15, 1, 'in', 'image', '[image]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 17:02:06'),
(16, 1, 'out', 'text', 'hola sigues en linea?', NULL, NULL, NULL, NULL, NULL, 1, '3EB068389290BD7C883FB1', 'sent', NULL, '2026-03-28 17:02:23'),
(17, 1, 'in', 'image', '[image]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 17:02:37'),
(18, 1, 'in', 'image', '[image]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 17:03:22'),
(19, 1, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 17:06:21'),
(20, 1, 'out', 'text', 'esta genial muchas gracias', NULL, NULL, NULL, NULL, NULL, 1, '3EB0978EF54B94F1F626A6', 'sent', NULL, '2026-03-28 17:11:25'),
(21, 1, 'in', 'text', '5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 17:22:11'),
(22, 1, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB0D67380F5DD9C95E6E7', 'sent', NULL, '2026-03-28 17:22:43'),
(23, 1, 'out', 'text', 'como podemos ayudarte', NULL, NULL, NULL, NULL, NULL, 1, '3EB09B38CB6F95336834D7', 'sent', NULL, '2026-03-28 17:22:53'),
(24, 1, 'in', 'text', 'Gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 17:23:05'),
(25, 1, 'in', 'text', 'Ok', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 17:23:25'),
(26, 1, 'in', 'text', '[sticker]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 17:23:28'),
(27, 1, 'out', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB0AFA77CF5F66F993235', 'sent', NULL, '2026-03-28 17:28:52'),
(28, 2, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 17:33:33'),
(29, 1, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 2, '3EB069B0EC4CD6CF9E1F64', 'sent', NULL, '2026-03-28 17:35:35'),
(30, 2, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 19:50:17'),
(31, 1, 'out', 'text', 'buenas noches', NULL, NULL, NULL, NULL, NULL, 2, '3EB0F469A14354F3C6EFAE', 'sent', NULL, '2026-03-28 20:15:47'),
(32, 2, 'in', 'text', '3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 20:17:05'),
(33, 2, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 2, '3EB04CA93DFB5305D26488', 'sent', NULL, '2026-03-28 20:17:33'),
(34, 2, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 20:17:42'),
(35, 2, 'in', 'text', 'Buenas tardes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 20:17:45'),
(36, 2, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 20:18:01'),
(37, 2, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 20:19:21'),
(38, 2, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 2, '3EB0E4697C25653EDEE7E2', 'sent', NULL, '2026-03-28 20:32:08'),
(39, 3, 'in', 'text', '3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:32:42'),
(40, 3, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB0A4565D2ACE5C63DC18', 'sent', NULL, '2026-03-28 21:32:52'),
(41, 3, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:33:03'),
(42, 3, 'in', 'text', 'Necesito una cerveza', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:33:30'),
(43, 1, 'in', 'text', '2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:38:48'),
(44, 1, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB0F6C5518D5091A03207', 'sent', NULL, '2026-03-28 21:42:34'),
(45, 1, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:47:20'),
(46, 1, 'in', 'text', '2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:47:46'),
(47, 1, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB07FDBAEF642BC7CA807', 'sent', NULL, '2026-03-28 21:48:04'),
(48, 1, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:48:24'),
(49, 1, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:48:35'),
(50, 1, 'in', 'text', 'Hola buenas noches', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:48:46'),
(51, 1, 'in', 'text', 'Un gusto', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:49:07'),
(52, 4, 'out', 'text', 'hola buenas noches', NULL, NULL, NULL, NULL, NULL, 1, '3EB0B98CBDCF4B1E812940', 'sent', NULL, '2026-03-28 21:55:01'),
(53, 5, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 21:57:59'),
(57, 1, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 22:29:04'),
(58, 1, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 22:29:43'),
(59, 8, 'out', 'text', 'Hola Buenas noches', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E453909B5A7273F3EC', 'sent', NULL, '2026-03-28 22:50:23'),
(60, 8, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 22:50:48'),
(61, 8, 'in', 'text', 'Graciasss', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-28 22:55:22'),
(62, 8, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB0AABE659FB69A95D546', 'sent', NULL, '2026-03-29 02:40:05'),
(63, 1, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 03:26:46'),
(64, 1, 'in', 'text', 'Gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 03:27:12'),
(65, 1, 'in', 'text', 'Mañana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 03:28:54'),
(66, 1, 'in', 'image', '[comprobante]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 03:30:43'),
(67, 1, 'out', 'image', 'IMG_0127.jpeg', 'https://panelws.intermediahost.co/uploads/1/f_69c8eefa57d7a9.85281076_IMG_0127.jpeg', 'IMG_0127.jpeg', 'image/jpeg', 3381234, NULL, 1, '3EB04D61F911E0851614D9', 'sent', NULL, '2026-03-29 04:20:58'),
(68, 1, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E32CACC33BFBE26512', 'sent', NULL, '2026-03-29 13:52:37'),
(69, 1, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 13:53:12'),
(70, 1, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 13:53:46'),
(71, 1, 'out', 'text', 'hola feliz tarde', NULL, NULL, NULL, NULL, NULL, 1, '3EB0DA8A996634180E92E0', 'sent', NULL, '2026-03-29 13:53:59'),
(72, 1, 'out', 'text', 'como podemos ayudarte?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E90FD0332719BD4781', 'sent', NULL, '2026-03-29 13:54:18'),
(73, 1, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 13:59:13'),
(74, 1, 'out', 'text', 'Hola buenas tardes 🤗', NULL, NULL, NULL, NULL, NULL, 1, '3EB04B0B68AAAA9C9B8B78', 'sent', NULL, '2026-03-29 13:59:51'),
(75, 1, 'out', 'text', 'Como podemos ayudarle?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0BB367F312DB01F4C64', 'sent', NULL, '2026-03-29 14:00:02'),
(76, 1, 'in', 'text', 'Gracias me interesa sus servicios', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 14:00:21'),
(77, 1, 'out', 'text', 'Es con mucho gusto', NULL, NULL, NULL, NULL, NULL, 1, '3EB028D33381C2B2F9622D', 'sent', NULL, '2026-03-29 15:29:52'),
(78, 1, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 15:47:32'),
(79, 1, 'out', 'image', 'scaled_a35299bc-bc7c-4f9f-821e-5522c0c06b2b1981726676807543810.jpg', 'https://panelws.intermediahost.co/uploads/1/f_69c9961ee2b894.19065188_scaled_a35299bc-bc7c-4f9f-821e-5522c0c06b2b1981726676807543810.jpg', 'scaled_a35299bc-bc7c-4f9f-821e-5522c0c06b2b1981726676807543810.jpg', 'image/jpeg', 313753, NULL, 1, '3EB01309456D0E96B572A7', 'sent', NULL, '2026-03-29 16:14:06'),
(80, 1, 'out', 'text', 'Hola, ¿en qué te puedo ayudar? 😊', NULL, NULL, NULL, NULL, NULL, 1, '3EB041AE419E77DFDBDDF1', 'sent', NULL, '2026-03-29 16:14:36'),
(81, 1, 'out', 'text', 'hello ahh merrryy aSKANDLKNSA', NULL, NULL, NULL, NULL, NULL, 1, '3EB006D7A32C61D1577E95', 'sent', NULL, '2026-03-29 16:45:36'),
(90, 10, 'out', 'text', 'Mi cielo te alo', NULL, NULL, NULL, NULL, NULL, 1, '3EB0741F49C04037A5A9F1', 'sent', NULL, '2026-03-29 18:41:54'),
(91, 10, 'in', 'text', 'Te amo mi vida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 18:42:39'),
(92, 10, 'in', 'text', 'Mi vida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 18:52:11'),
(93, 10, 'out', 'text', 'Te amoooo', NULL, NULL, NULL, NULL, NULL, 1, '3EB0A0F1431A6B9BA50673', 'sent', NULL, '2026-03-29 18:52:34'),
(94, 1, 'in', 'text', '2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 18:54:33'),
(95, 1, 'in', 'text', 'Hola buenas noches', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 18:55:02'),
(96, 1, 'out', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB08EBB643989AB3CE3D1', 'sent', NULL, '2026-03-29 19:04:15'),
(97, 1, 'out', 'text', 'Como podemos ayudarle?', NULL, NULL, NULL, NULL, NULL, 1, '3EB05983ACFE1F2A94939D', 'sent', NULL, '2026-03-29 19:04:23'),
(98, 10, 'in', 'text', 'Te amooooo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 19:05:25'),
(99, 10, 'out', 'text', 'Te amoooo', NULL, NULL, NULL, NULL, NULL, 1, '3EB0882CB7151121FACECC', 'sent', NULL, '2026-03-29 19:05:38'),
(100, 10, 'out', 'text', 'Gracias por ser tan linda conmigo 🤩😍🥰🥰', NULL, NULL, NULL, NULL, NULL, 1, '3EB0BF1BC3E8F52F1E0187', 'sent', NULL, '2026-03-29 19:05:59'),
(101, 10, 'out', 'document', 'numeros_1_al_40_en_recuadros.pdf', 'https://panelws.intermediahost.co/uploads/10/f_69c9be7006a7b1.33955293_numeros_1_al_40_en_recuadros.pdf', 'numeros_1_al_40_en_recuadros.pdf', 'application/pdf', 1964, NULL, 1, '3EB08D05DE0BB704681810', 'sent', NULL, '2026-03-29 19:06:08'),
(102, 10, 'in', 'text', 'Gracias a ti mi cielo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 19:06:33'),
(103, 1, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 19:08:43'),
(104, 1, 'in', 'text', '4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-29 19:12:07'),
(105, 1, 'out', 'text', 'Hola buenas noches', NULL, NULL, NULL, NULL, NULL, 1, '3EB0BFB6A190237F837197', 'sent', NULL, '2026-03-29 19:12:43'),
(106, 1, 'out', 'text', 'como puedo ayudarte', NULL, NULL, NULL, NULL, NULL, 2, '3EB014EED4EB164F59D193', 'sent', NULL, '2026-03-29 19:13:18'),
(107, 10, 'out', 'text', 'Te amo mi princesa', NULL, NULL, NULL, NULL, NULL, 1, '3EB0DA2F27CC64281CCBAE', 'sent', NULL, '2026-03-29 19:13:55'),
(108, 1, 'out', 'text', 'Te compartimos los medios de pago disponibles para *Intermedia Host*:\n\n💳 *Transferencia Bancaria:*\n🏦 Bancolombia Ahorros: *29735308295*\n🏦 Davivienda Ahorros: *488413242998*\n\n🔑 *Llave Bre-B:*\nintermediacolombia@gmail.com\n\n📲 *Nequi o Daviplata:*\n3147165269\n\n🌐 *PayPal:*\nintermediacolombia@gmail.com\n\nPor favor, una vez realices el pago, no olvides enviar el *comprobante* para validar el pago y activar o renovar tu servicio.\n\n¡Gracias por confiar en nosotros! 💻✨', NULL, NULL, NULL, NULL, NULL, 1, '3EB0D72412B7C1DB92985D', 'sent', NULL, '2026-03-29 22:44:35'),
(109, 1, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB02692C0F75824A49868', 'sent', NULL, '2026-03-30 13:25:48'),
(110, 1, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB0A3DD16000D9A8B7360', 'sent', NULL, '2026-03-30 15:06:14'),
(111, 1, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-30 15:14:46'),
(112, 1, 'in', 'text', 'Gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-30 15:16:22'),
(113, 1, 'in', 'text', 'Gracias, requiero más información sobre los servicios', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-30 15:16:37'),
(114, 11, 'out', 'text', 'Hola Muy buenas noches queríamos informarte que la aplicación tiene una nueva actualización con funciones modernas y nuevas características entre las que ustedes tienen derecho a utilizar un panel de control donde pueden ver las estadísticas de uso de la app las notificaciones pueden enviarlas también directamente desde el panel de control y una función que es el envío de notas de voz a cabina esto es muy útil para que los oyentes a través de la app tengan una comunicación directa con ustedes las notas de voz tienen una duración máxima de 30 segundos Así que en sus programas para incentivar la descarga de la app pueden informarle a los oyentes que los que ya tienen la aplicación la actualicen inmediatamente para que disfruten de la nueva función la puedan descargar y ustedes puedan sacar las notas de voz al aire, esas notas de voz la encontrarán en el panel de control de la app en la sección notas de voz allí encontrarán todas las que van llegando directamente de la aplicación, si quisiera tener derecho a este panel no tiene ningún costo es completamente gratis y podemos crearle el nombre de usuario y una contraseña para que pueda ingresar a él, si tienen alguna duda algún comentario no duden en contactarnos Muchas gracias 😊', NULL, NULL, NULL, NULL, NULL, 1, '3EB0437F9799F5ED8FED5E', 'sent', NULL, '2026-03-30 18:42:08'),
(115, 11, 'in', 'text', 'Gracias, sí por favor crearlo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-30 18:59:52'),
(116, 12, 'in', 'text', 'Quierop restablecer contraseña de wordpress', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:38:16'),
(117, 12, 'out', 'text', 'Hola buenos días', NULL, NULL, NULL, NULL, NULL, 1, '3EB0333B43FA5BF124A327', 'sent', NULL, '2026-03-31 09:41:15'),
(118, 12, 'in', 'text', 'Buenos dias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:41:29'),
(119, 12, 'out', 'text', 'Podrias indícame porfavor el usuario con el que ingresas a  la plataforma?', NULL, NULL, NULL, NULL, NULL, 1, '3EB082FB1A6651820C771A', 'sent', NULL, '2026-03-31 09:42:04'),
(120, 12, 'in', 'text', 'Quiero restablecer la contraseña de word press para actualizar la pàgina. Dice que envia un codigo pero no sabemos a que telefon o. Posiblemente sea de la persona que se fue', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:42:10'),
(121, 12, 'out', 'text', 'Entiendo, conoces el usuario para ingresar?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0ED948C5260FCDBE690', 'sent', NULL, '2026-03-31 09:43:00'),
(122, 12, 'in', 'image', '[image]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:43:32'),
(123, 12, 'in', 'text', 'me dieron estos datos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:43:41'),
(124, 12, 'out', 'text', 'No puedo ver imágenes 😔', NULL, NULL, NULL, NULL, NULL, 1, '3EB0821CD1DEA32E6802D2', 'sent', NULL, '2026-03-31 09:44:09'),
(125, 12, 'in', 'text', 'usuario lunaestereo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:44:21'),
(126, 12, 'in', 'text', 'contraseña Redd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:44:27'),
(127, 12, 'out', 'text', 'Podrias indicarme solo rl usuario con eso ya te ayudo', NULL, NULL, NULL, NULL, NULL, 1, '3EB0791B994ADD740FF567', 'sent', NULL, '2026-03-31 09:44:28'),
(128, 12, 'in', 'text', 'Contraseña: RedesSociales2025', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:44:39'),
(129, 12, 'out', 'text', 'Perfecto 👌 quires esa misma contraseña o necesitas otra?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E690CB3CB9245A2055', 'sent', NULL, '2026-03-31 09:45:03'),
(130, 12, 'in', 'text', 'esa misma esta bien', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:45:16'),
(131, 12, 'in', 'text', 'o cambiemos 2026', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:45:22'),
(132, 12, 'out', 'text', 'Un segundo Por favor Voy a realizar la acción, continua en linea porfavor', NULL, NULL, NULL, NULL, NULL, 1, '3EB0521174DBD4D592CE1D', 'sent', NULL, '2026-03-31 09:46:22'),
(133, 12, 'in', 'text', 'gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:46:38'),
(134, 12, 'out', 'text', 'Listo podrías intentar con esa contraseña que me indicas pero con 2026', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E2C8317B93E8DBFDB0', 'sent', NULL, '2026-03-31 09:49:02'),
(135, 12, 'in', 'text', 'listo ya te digo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:49:23'),
(136, 12, 'out', 'text', 'Dale estoy atenta', NULL, NULL, NULL, NULL, NULL, 1, '3EB08B81E68B41D995C948', 'sent', NULL, '2026-03-31 09:49:53'),
(137, 12, 'in', 'text', 'No', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:52:22'),
(138, 12, 'in', 'text', 'parece que haz introducido una contraseña incorrecta. Dice', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:52:45'),
(139, 12, 'out', 'text', 'Un segundo porfavor', NULL, NULL, NULL, NULL, NULL, 1, '3EB01CC0A229692659E80D', 'sent', NULL, '2026-03-31 09:52:46'),
(140, 12, 'in', 'text', 'dale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:53:54'),
(141, 12, 'out', 'text', 'Podrías indicarme por favor la URL por la que estás intentando ingresar', NULL, NULL, NULL, NULL, NULL, 1, '3EB02225C92A5889FD9671', 'sent', NULL, '2026-03-31 09:54:30'),
(142, 12, 'in', 'text', 'https://wordpress.com/log-in/es?email_address=lunaestereo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:54:58'),
(143, 12, 'out', 'text', 'Ya sé qué es lo que sucede no es por ese medio ya que esa es la página oficial de wordpress', NULL, NULL, NULL, NULL, NULL, 1, '3EB0AD8B16EBDD8444D3BF', 'sent', NULL, '2026-03-31 09:56:07'),
(144, 12, 'out', 'text', 'https://www.lunaestereo.com/wp-admin/', NULL, NULL, NULL, NULL, NULL, 1, '3EB0A79A9399DBAFD4ED4B', 'sent', NULL, '2026-03-31 09:56:36'),
(145, 12, 'out', 'text', 'Esta es la url que debes utilizar para ingresar intenta con este y me cuentas', NULL, NULL, NULL, NULL, NULL, 1, '3EB0C717BCC26F5285CE06', 'sent', NULL, '2026-03-31 09:57:07'),
(146, 12, 'in', 'text', 'Ah listo muchas gracias. Perfecto', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 09:59:35'),
(147, 12, 'out', 'text', 'Perfecto, algo mas en lo que te pueda ayudar?', NULL, NULL, NULL, NULL, NULL, 1, '3EB067FBD23FA2FC301B0D', 'sent', NULL, '2026-03-31 10:00:02'),
(148, 12, 'in', 'text', 'Me gustaria saber cual es el telefono registrado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 10:00:25'),
(149, 12, 'in', 'text', 'para cambiarlo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 10:00:33'),
(150, 12, 'out', 'text', 'El número registrado en que plataforma? En la de WordPress o en el área de cliente', NULL, NULL, NULL, NULL, NULL, 1, '3EB0C9D7DDB7931E3C5BC9', 'sent', NULL, '2026-03-31 10:01:46'),
(151, 12, 'in', 'text', 'Los dos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 10:03:26'),
(152, 12, 'out', 'text', 'En WordPress no habilitamos números de teléfono, de estar habilitado puedes cambiarlo cuando ingreses al panel en la sesion del perfil', NULL, NULL, NULL, NULL, NULL, 1, '3EB04B110C5827B847A0D3', 'sent', NULL, '2026-03-31 10:04:37'),
(153, 12, 'out', 'text', 'Y en el área de cliente el número registrado es el que te llega en las alertas de facturación', NULL, NULL, NULL, NULL, NULL, 1, '3EB02CA62221866B9298FD', 'sent', NULL, '2026-03-31 10:05:31'),
(154, 12, 'in', 'text', 'Muchas gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 10:17:55'),
(155, 11, 'out', 'text', 'Buenas tardes, enviamos Credenciales y acceso\n\nURL: https://appspanel.intermediahost.co/client_portal.php\nUsuario: mauricio_cardona\nContraseña: EmisoraCulturalP@2026*\n\nSi tienes alguna duda o algun mentario no dudes en contactarnos.', NULL, NULL, NULL, NULL, NULL, 1, '3EB01D75CFAE3F080E3455', 'sent', NULL, '2026-03-31 13:11:14'),
(156, 1, 'out', 'text', 'https://www.lunaestereo.com/wp-admin/', NULL, NULL, NULL, NULL, NULL, 1, '3EB098E1EA5A532CE4AFAC', 'sent', NULL, '2026-03-31 13:38:52'),
(157, 8, 'out', 'text', 'Edme', NULL, NULL, NULL, NULL, NULL, 1, '3EB06C544EBDBB158D76AA', 'sent', NULL, '2026-03-31 14:13:04'),
(158, 8, 'out', 'text', 'Hi', NULL, NULL, NULL, NULL, NULL, 1, '3EB0721A29B6C31E68212F', 'sent', NULL, '2026-03-31 14:13:25'),
(159, 11, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:15:05'),
(160, 11, 'out', 'text', 'Hola buenas tardes como podemos ayudarte?', NULL, NULL, NULL, NULL, NULL, 1, '3EB00224F1005D49600353', 'sent', NULL, '2026-03-31 14:15:38'),
(161, 11, 'in', 'text', 'Gracias, buenas tardes', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:15:54'),
(162, 11, 'in', 'image', '[image]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:15:59'),
(163, 11, 'in', 'text', 'Las credenciales que me enviaron no dan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:16:13'),
(164, 11, 'out', 'text', 'Podrias indicarme tu inconveniente, no puedo ver imagenes por este medio', NULL, NULL, NULL, NULL, NULL, 1, '3EB0B5A205159CD270C37A', 'sent', NULL, '2026-03-31 14:16:33'),
(165, 11, 'out', 'text', 'me dices que no puede iniciar sesión en que modulo?', NULL, NULL, NULL, NULL, NULL, 1, '3EB032001B43D31907FEE9', 'sent', NULL, '2026-03-31 14:17:00'),
(166, 11, 'in', 'text', 'Correcto', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:17:13'),
(167, 11, 'out', 'text', 'te compartieron las credenciales del panel de apps?', NULL, NULL, NULL, NULL, NULL, 1, '3EB07D320CE5B0F61287B1', 'sent', NULL, '2026-03-31 14:17:44'),
(168, 11, 'in', 'text', 'Buenas tardes, enviamos Credenciales y acceso\n\nURL: https://appspanel.intermediahost.co/client_portal.php\nUsuario: mauricio_cardona\nContraseña: EmisoraCulturalP@2026*\n\nSi tienes alguna duda o algun mentario no dudes en contactarnos.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:17:55'),
(169, 11, 'in', 'text', 'Las escribo tal cual y nada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:18:05'),
(170, 11, 'out', 'text', 'Un segundo porfavor', NULL, NULL, NULL, NULL, NULL, 1, '3EB0DB722B4D490B614761', 'sent', NULL, '2026-03-31 14:18:04'),
(171, 11, 'in', 'text', 'Gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:18:14'),
(172, 11, 'out', 'text', 'Las credenciales si me ingresan en este medio', NULL, NULL, NULL, NULL, NULL, 1, '3EB0279883474BCDC865B2', 'sent', NULL, '2026-03-31 14:19:00'),
(173, 11, 'out', 'text', 'si gustas puedo ponerte una mas sencilla que recuerdes o mas comun para ti', NULL, NULL, NULL, NULL, NULL, 1, '3EB03872979249F36DCD48', 'sent', NULL, '2026-03-31 14:19:23'),
(174, 11, 'in', 'text', 'Bueno', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:19:50'),
(175, 11, 'in', 'text', 'Al1cant32649*', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:20:02'),
(176, 11, 'in', 'text', 'Gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:20:14'),
(177, 11, 'out', 'text', 'Un segundo porfavor', NULL, NULL, NULL, NULL, NULL, 1, '3EB0DA88F33CE8A9D92DAB', 'sent', NULL, '2026-03-31 14:20:28'),
(178, 11, 'out', 'text', 'Listo, podrías verificar si ya te ingresa con esas credenciales', NULL, NULL, NULL, NULL, NULL, 1, '3EB015DFF1B9F37F7AE8C8', 'sent', NULL, '2026-03-31 14:21:33'),
(179, 11, 'in', 'text', 'Ok', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:21:47'),
(180, 11, 'out', 'text', 'me confirmas porfavor, sigo pendiente', NULL, NULL, NULL, NULL, NULL, 1, '3EB0863E94A63DE0903B9B', 'sent', NULL, '2026-03-31 14:22:06'),
(181, 11, 'in', 'text', 'Ok ya, muchas gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:23:26'),
(182, 11, 'out', 'text', 'perfecto!!', NULL, NULL, NULL, NULL, NULL, 1, '3EB0151CAB8D27A69952DB', 'sent', NULL, '2026-03-31 14:23:40'),
(183, 11, 'out', 'text', 'algo mas en lo que te pueda ayudar?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0A5B8C2A8873C30F8E3', 'sent', NULL, '2026-03-31 14:23:55'),
(184, 11, 'in', 'text', 'Las notas de voz no se pueden compartir a otra app como Whatsaap?', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:24:57'),
(185, 11, 'out', 'text', 'no señor la funcionalidad es interna de la app, ya que whatsapp es una app independiente', NULL, NULL, NULL, NULL, NULL, 1, '3EB0BF234F1A2F334BF4E4', 'sent', NULL, '2026-03-31 14:26:20'),
(186, 11, 'in', 'text', 'Ok perfecto', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:27:13'),
(187, 11, 'in', 'text', 'Gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:27:15'),
(188, 11, 'out', 'text', 'Tienes alguna otra duda?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0B220AF2D7FC83E823C', 'sent', NULL, '2026-03-31 14:27:35'),
(189, 11, 'in', 'text', 'No ya está todo bien', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:28:45'),
(190, 11, 'out', 'text', 'Es con mucho gusto!!!', NULL, NULL, NULL, NULL, NULL, 1, '3EB0AF618CB22AB51946DF', 'sent', NULL, '2026-03-31 14:29:03'),
(191, 11, 'in', 'text', 'Gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:29:24'),
(192, 10, 'out', 'text', 'Hola Mi amorcito', NULL, NULL, NULL, NULL, NULL, 1, '3EB0FB511EC9A80CFB80E7', 'sent', NULL, '2026-03-31 14:37:26'),
(193, 10, 'out', 'text', 'mi cielo', NULL, NULL, NULL, NULL, NULL, 1, '3EB00E9957B63455E5535F', 'sent', NULL, '2026-03-31 14:40:03'),
(194, 10, 'in', 'text', 'Mi amor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:40:38'),
(195, 10, 'out', 'text', 'como estas mi niña?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0FFD460CD4B4780D47F', 'sent', NULL, '2026-03-31 14:41:58'),
(196, 10, 'out', 'text', 'estoy haciendo pruebitas contigo 😊😊😊', NULL, NULL, NULL, NULL, NULL, 1, '3EB092C3A127CB3776F862', 'sent', NULL, '2026-03-31 14:42:30'),
(197, 10, 'out', 'text', 'puedo?', NULL, NULL, NULL, NULL, NULL, 1, '3EB010602AA85DE71CC4C7', 'sent', NULL, '2026-03-31 14:42:39'),
(198, 10, 'in', 'text', 'Si amor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:43:50'),
(199, 10, 'out', 'text', 'te amoooo', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E12CA423259BFBB892', 'sent', NULL, '2026-03-31 14:44:01'),
(200, 10, 'out', 'text', 'ya te vuelvo a escribir', NULL, NULL, NULL, NULL, NULL, 1, '3EB06D72F37D7DFF9164D6', 'sent', NULL, '2026-03-31 14:44:10'),
(201, 10, 'out', 'text', 'mi cielo', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E14F42F7D38EC5F1D3', 'sent', NULL, '2026-03-31 14:45:15'),
(202, 10, 'out', 'text', 'te amooo muchooo', NULL, NULL, NULL, NULL, NULL, 1, '3EB07EAA96FC808CB5E8C1', 'sent', NULL, '2026-03-31 14:45:22'),
(203, 10, 'in', 'text', 'Te amo mi amor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:45:36'),
(204, 10, 'out', 'text', 'ahh si funciono lo que estaba haciendo jejeje', NULL, NULL, NULL, NULL, NULL, 1, '3EB0DAA744BBECB15C2E24', 'sent', NULL, '2026-03-31 14:45:36'),
(205, 10, 'in', 'text', 'Ah bueno amor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:45:51'),
(206, 8, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:46:27'),
(207, 8, 'in', 'text', 'Buenas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:47:01'),
(208, 10, 'out', 'text', 'estas bien mi cielo?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0A01AB1107CAEACB323', 'sent', NULL, '2026-03-31 14:47:49'),
(209, 8, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:49:01'),
(210, 10, 'out', 'text', 'mi cielo', NULL, NULL, NULL, NULL, NULL, 1, '3EB001D0E125DF164CA46E', 'sent', NULL, '2026-03-31 14:53:13'),
(211, 8, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB05545145F5B8D134375', 'sent', NULL, '2026-03-31 14:53:30'),
(212, 10, 'out', 'text', '?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0D38F906B812274B736', 'sent', NULL, '2026-03-31 14:53:42'),
(213, 10, 'in', 'text', 'Mi amor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:53:48'),
(214, 10, 'in', 'text', 'Si mi cielo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:53:59'),
(215, 10, 'out', 'text', 'a las 3 nos vamos mi cielo para mercar?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0963E5598F373C29310', 'sent', NULL, '2026-03-31 14:54:12'),
(216, 8, 'out', 'text', 'como es?', NULL, NULL, NULL, NULL, NULL, 1, '3EB00ECD06D1B38FE608DC', 'sent', NULL, '2026-03-31 14:54:21'),
(217, 10, 'in', 'text', 'Y vamos a ir los dos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:54:40'),
(218, 10, 'out', 'text', 'si mi cielo', NULL, NULL, NULL, NULL, NULL, 1, '3EB0BBDCB953884C194DEA', 'sent', NULL, '2026-03-31 14:54:57'),
(219, 10, 'out', 'text', 'te vas alistando?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0BCB0C1CA3394BF93A0', 'sent', NULL, '2026-03-31 14:55:06'),
(220, 10, 'in', 'text', 'Y tu papa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:55:10'),
(221, 10, 'out', 'text', 'si mi amor el tambie va', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E2782DEBB278E554B1', 'sent', NULL, '2026-03-31 14:55:22'),
(222, 10, 'in', 'text', 'Ah bueno amor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 14:55:35'),
(223, 8, 'out', 'text', 'diga', NULL, NULL, NULL, NULL, NULL, 1, '3EB022406E64094564E609', 'sent', NULL, '2026-03-31 14:56:40'),
(224, 8, 'out', 'text', 'esta es la prueba 1', NULL, NULL, NULL, NULL, NULL, 1, '3EB0FEFB061F482813B8C5', 'sent', NULL, '2026-03-31 14:58:04'),
(225, 8, 'out', 'text', 'funciona bien el focus', NULL, NULL, NULL, NULL, NULL, 1, '3EB044107646744DEA975E', 'sent', NULL, '2026-03-31 14:58:11'),
(226, 8, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB07BA8197DEEFF0E4963', 'sent', NULL, '2026-03-31 15:02:02'),
(227, 10, 'out', 'text', 'bueno mi cielo, ya voy', NULL, NULL, NULL, NULL, NULL, 1, '3EB0EC8DE138A420283C14', 'sent', NULL, '2026-03-31 15:02:35'),
(228, 8, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB008FFF6028F922DA581', 'sent', NULL, '2026-03-31 15:13:37'),
(229, 10, 'out', 'text', '😍', NULL, NULL, NULL, NULL, NULL, 1, '3EB0B970D00E3B6A90F4B0', 'sent', NULL, '2026-03-31 15:18:53'),
(230, 8, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 17:35:24'),
(231, 8, 'out', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB0F61B141C373870C7B6', 'sent', NULL, '2026-03-31 17:35:37'),
(232, 8, 'out', 'text', 'Test', NULL, NULL, NULL, NULL, NULL, 1, '3EB0CF45288ED6DD669D78', 'sent', NULL, '2026-03-31 17:35:42'),
(233, 8, 'in', 'text', 'Asesor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 17:36:37'),
(234, 8, 'out', 'text', 'Gracias funciona', NULL, NULL, NULL, NULL, NULL, 1, '3EB0F0E085979BC649F07E', 'sent', NULL, '2026-03-31 17:36:51'),
(235, 13, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-03-31 18:27:58'),
(236, 14, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 08:15:26'),
(237, 15, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 08:25:16'),
(238, 15, 'in', 'image', '[image]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 08:25:50'),
(239, 15, 'out', 'text', 'Hola Buenos días Cómo podemos ayudarlo', NULL, NULL, NULL, NULL, NULL, 1, '3EB016CD8D79E048E8F551', 'sent', NULL, '2026-04-01 08:30:33'),
(240, 15, 'out', 'text', 'Podrías indicarme por favor el inconveniente que tienes por este medio no puedo ver imágenes', NULL, NULL, NULL, NULL, NULL, 1, '3EB07EAA1307F5A222772E', 'sent', NULL, '2026-04-01 08:30:43'),
(241, 16, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:02:20'),
(242, 16, 'in', 'text', 'Espero', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:08:34'),
(243, 16, 'out', 'text', 'Hola Muy buenos días señor emauris cuéntame cómo puedo ayudarte', NULL, NULL, NULL, NULL, NULL, 1, '3EB008D3D0ADAA675B3473', 'sent', NULL, '2026-04-01 09:11:27'),
(244, 16, 'in', 'text', '[audio]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:13:31'),
(245, 16, 'out', 'text', 'Por este medio no puedo escuchar audios ni Ver imágenes podrías indicarme por favor cuál es tu inconveniente', NULL, NULL, NULL, NULL, NULL, 1, '3EB07CCD66EDD3B59AD628', 'sent', NULL, '2026-04-01 09:16:40'),
(246, 16, 'in', 'text', 'Te decía que varios oyentes desde hace una semanas me han venido reportando que la señal de la radio se cae es decir cuando se cae la señal hay una especie de chatbut que empieza a hablar en automático en inglés entonces quisiera saber qué es lo que está pasando porque cuando eso ocurre la señal se cae es decir radio voz no logra conectar con la página la página se queda muda la aplicación también se queda muda y luego empieza a salir una persona hablando en inglés entonces no sé qué será eso si me pueden dar información si podemos solventar ese asunto lo más rápido posible porque acaba de ocurrir ahorita hace como 10 minutos acaba de ocurrir eso se fue la señal entró como especie de una persona y no sé si es un chatbot hablando en inglés diciendo unas cosas en inglés duró como 5 minutos y luego retomó otra vez volvió a agarrar la señal internet no es porque pues si fuera internet simplemente se cae y ya pero es que sale una persona hablando en inglés', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:19:04'),
(247, 16, 'in', 'text', 'Cuando eso ocurre RadioBoss. No conecta y en la página y en la app está una especie de chatbot hablando en inglés', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:20:00'),
(248, 16, 'in', 'text', 'No sé si es algo que tenga que ver con el autodj', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:20:16'),
(249, 16, 'out', 'text', 'Muy bien Entonces vamos a hacer todo desde el inicio', NULL, NULL, NULL, NULL, NULL, 1, '3EB08A1D7E7B2D166DAE1B', 'sent', NULL, '2026-04-01 09:20:56'),
(250, 16, 'out', 'text', 'Podrías por favor indicarme el nombre de la estación y en qué panel te encuentras para verificar cómo tienes la programación esto sucede porque es un mensaje por defecto que tiene el panel de control informando sobre el software Esto es para evitar que los directorios de radio eliminen la emisora y las estaciones que tienen aplicación no sean bajadas por medio de Google por falta de usabilidad', NULL, NULL, NULL, NULL, NULL, 1, '3EB02FDDD5A8E9DC2E9264', 'sent', NULL, '2026-04-01 09:21:47'),
(251, 16, 'out', 'text', 'Pero la voz que me indicas es porque efectivamente el sistema se queda sin lista de programación y sin conexión local', NULL, NULL, NULL, NULL, NULL, 1, '3EB06F6DA283CBECE1B276', 'sent', NULL, '2026-04-01 09:22:03'),
(252, 16, 'in', 'text', 'Bajo Nivel Radio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:22:10'),
(253, 16, 'out', 'text', 'En realidad es agua reemplaza el auto DJ Ya que en ese horario debería iniciar la lista de reproducción por eso necesito que me des estos datos para verificar que puede estar pasando y ya te informo por favor no te vayas a desconectar Y estás pendiente del chat', NULL, NULL, NULL, NULL, NULL, 1, '3EB04A4D9E5ED0ABA5FC77', 'sent', NULL, '2026-04-01 09:22:23'),
(254, 16, 'in', 'text', 'eso supuse aunque no he movido nada de las listas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:22:26'),
(255, 16, 'in', 'text', 'pero el tema de la conexion tambien ocurre seguido', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:22:42'),
(256, 16, 'out', 'text', 'Desconexiones No hemos tenido en este momento posiblemente Puede que sea de estudio también podríamos revisar directamente desde tu estudio como estás conectándote Y si podemos hacer alguna operación para verificar que no haya ninguna interrupción en la conexión entre el software de emisión y el servidor', NULL, NULL, NULL, NULL, NULL, 1, '3EB0B8B343682110D961ED', 'sent', NULL, '2026-04-01 09:23:31'),
(257, 16, 'out', 'text', 'También muchas veces esto sucede por algunas configuraciones que tengan directamente en el programa Entonces vamos a revisar la hora voy a revisar Entonces cómo tiene la lista de reproducción y encontramos la raíz del problema', NULL, NULL, NULL, NULL, NULL, 1, '3EB0A269A77DECC4675996', 'sent', NULL, '2026-04-01 09:23:49'),
(258, 16, 'in', 'text', 'por favor!', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:23:51'),
(259, 16, 'in', 'text', 'Muchas gracias!', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:24:18'),
(260, 16, 'in', 'text', 'me comenta por aca unos los chicos que me programa a veces que el parece ser que movio algo en las listas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:24:40'),
(261, 16, 'out', 'text', 'Por favor pendiente del chat que voy a empezar a trabajar y ya te comento tardaré por ahí unos 10 15 minutos en revisar todo', NULL, NULL, NULL, NULL, NULL, 1, '3EB04E72F127FBC4BD9232', 'sent', NULL, '2026-04-01 09:24:40'),
(262, 16, 'in', 'text', 'listo!', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:24:50'),
(263, 16, 'out', 'text', 'Señor Emauris podriamos hacer una prueba?', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E86F6F8564F73CD26E', 'sent', NULL, '2026-04-01 09:33:22'),
(264, 16, 'in', 'text', 'Si claro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:33:33'),
(265, 16, 'in', 'text', 'que requiere', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:33:36'),
(266, 16, 'out', 'text', 'es decir si hay la posibilidad de desconectar de estudio', NULL, NULL, NULL, NULL, NULL, 1, '3EB02B1DE3986BD0EC62F6', 'sent', NULL, '2026-04-01 09:33:53'),
(267, 16, 'in', 'text', 'voy', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:34:03'),
(268, 16, 'in', 'text', 'listo desconectado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:34:16'),
(269, 16, 'out', 'text', 'ya que no evidencio inconsistencias en el panel por el momento', NULL, NULL, NULL, NULL, NULL, 1, '3EB0304A81B1C1798568E3', 'sent', NULL, '2026-04-01 09:34:17'),
(270, 16, 'out', 'text', 'gracias, ya vuelvo con usted', NULL, NULL, NULL, NULL, NULL, 1, '3EB0C639C064BD9C2C62E8', 'sent', NULL, '2026-04-01 09:34:35'),
(271, 16, 'in', 'text', 'ok, ahi esta sonando el AutoDj', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:35:01'),
(272, 16, 'out', 'image', 'Screenshot_2.png', 'https://panelws.intermediahost.co/uploads/16/f_69cd2f70bf60d7.93835882_Screenshot_2.png', 'Screenshot_2.png', 'image/png', 265144, NULL, 1, '3EB0A0FFA51C333E5E84F5', 'sent', NULL, '2026-04-01 09:45:04'),
(273, 16, 'out', 'image', 'Screenshot_3.png', 'https://panelws.intermediahost.co/uploads/16/f_69cd2f7fa37175.53624202_Screenshot_3.png', 'Screenshot_3.png', 'image/png', 150296, NULL, 1, '3EB0E6120B04CD494EDF0A', 'sent', NULL, '2026-04-01 09:45:19'),
(274, 16, 'out', 'text', 'Señor Emaurys, por este medio puede reemplazar este audio por defecto', NULL, NULL, NULL, NULL, NULL, 1, '3EB06A19390C8D9D8B84FC', 'sent', NULL, '2026-04-01 09:45:46'),
(275, 16, 'out', 'text', 'puede subir un archivo personalizado, puede ser un aununcio, un comercial, una cancion', NULL, NULL, NULL, NULL, NULL, 1, '3EB0C9A0298A7F7BEEBA07', 'sent', NULL, '2026-04-01 09:46:20'),
(276, 16, 'in', 'text', 'Ese es el que está causando el corte?', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:46:33'),
(277, 16, 'out', 'text', 'e recomienda que este en el mismo formato de transmision', NULL, NULL, NULL, NULL, NULL, 1, '3EB0AB2DFCD9924F3FC248', 'sent', NULL, '2026-04-01 09:46:35'),
(278, 16, 'in', 'text', 'Ok entendido', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:46:57'),
(279, 16, 'out', 'text', 'no, ya vamos a revisar los registros de su equipo para eso necesitaria su anydesk para ingresar', NULL, NULL, NULL, NULL, NULL, 1, '3EB02C0880333B10E2C713', 'sent', NULL, '2026-04-01 09:47:13'),
(280, 16, 'in', 'text', '1 046 255 455', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:48:07'),
(281, 16, 'out', 'text', 'lo que le acabo d eindicar es para que esa voz en ingles no le salga, y salga es algo por defecto en su estación, es un archivo de respaldo', NULL, NULL, NULL, NULL, NULL, 1, '3EB0F3A5D10A0868409A13', 'sent', NULL, '2026-04-01 09:48:23'),
(282, 16, 'in', 'text', 'a ok ya entendi!  es decir es como el contestador automatico', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:49:12'),
(283, 16, 'out', 'text', 'Listo podría aceptarme por favor', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E9FF615C61F5BD9505', 'sent', NULL, '2026-04-01 09:51:21'),
(284, 14, 'out', 'text', 'muy buenos dias, tenemos una solicitud por parte suya sobre el player, podria indicarme si ya tiene acceso?', NULL, NULL, NULL, NULL, NULL, 1, '3EB06CEF09C5F5105C1A90', 'sent', NULL, '2026-04-01 09:52:24'),
(285, 16, 'in', 'text', 'Listo ya acepté', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:52:26'),
(286, 14, 'in', 'text', 'si ya se arregló', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:52:40'),
(287, 14, 'in', 'text', 'gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:52:41'),
(288, 14, 'out', 'text', 'con mucho gusto, estaremos muy atentos', NULL, NULL, NULL, NULL, NULL, 1, '3EB03C9865623EBF19960E', 'sent', NULL, '2026-04-01 09:52:58'),
(289, 16, 'out', 'text', 'Señor Emauris, el único inconveniente que posiblemente genere impacto es el birate tan alto que usaba en AAC+, aun que el panel soporte esta velocidad, posiblemente el encoder de radio boss no lo soporte, de hecho en 128 es mas que ideal ya que es como si estuviese en 320 en mp3', NULL, NULL, NULL, NULL, NULL, 1, '3EB0E70371089D80D28C8B', 'sent', NULL, '2026-04-01 09:55:57'),
(290, 16, 'in', 'text', 'A ok .. no los sabía', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:56:25'),
(291, 16, 'in', 'text', 'Lo dejaré entonces siempre en 128', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:56:51'),
(292, 16, 'out', 'text', 'lo acabo de cambiara a ese, si tiene una desconexión inmediatamente escribanos que entramos a revisar el estado de su servidor, y desde su estudio', NULL, NULL, NULL, NULL, NULL, 1, '3EB061434EFE4969147174', 'sent', NULL, '2026-04-01 09:57:05'),
(293, 16, 'in', 'text', 'Muy agradecido', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:57:30'),
(294, 16, 'out', 'text', 'Es con mucho gusto!!!', NULL, NULL, NULL, NULL, NULL, 1, '3EB0C758F13A6336FEE763', 'sent', NULL, '2026-04-01 09:57:39'),
(295, 16, 'in', 'text', 'Excelente servicio como siempre ...rápida respuesta', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:57:43'),
(296, 16, 'out', 'text', 'mas en lo que le pueda ayudar?', NULL, NULL, NULL, NULL, NULL, 1, '3EB08A54237C44418BACC7', 'sent', NULL, '2026-04-01 09:57:46'),
(297, 16, 'in', 'text', 'No eso es todo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:58:04'),
(298, 16, 'in', 'text', 'Muchísimas gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:58:07'),
(299, 16, 'out', 'text', 'Es con el mayor de los gustos, mi nombre es Valentina y ha sido un placer poder ayudarle!!! que tengas un lindo dia!!!', NULL, NULL, NULL, NULL, NULL, 1, '3EB0BB4F2746B1B733D773', 'sent', NULL, '2026-04-01 09:58:41'),
(300, 16, 'in', 'text', 'Gracias Valentina por tu ayuda y amabilidad', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:59:15'),
(301, 16, 'in', 'text', 'Feliz día', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 09:59:18'),
(302, 1, 'in', 'text', '2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 10:51:29'),
(303, 1, 'in', 'text', 'Hola', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 10:51:43'),
(304, 17, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 11:02:26'),
(305, 17, 'out', 'text', 'Señor Henrry buenos dias, bienvenido a intermedia host, mi nombre es valentina, como puedo ayudarlo', NULL, NULL, NULL, NULL, NULL, 1, '3EB0EFFBA962FC7970750C', 'sent', NULL, '2026-04-01 11:03:09'),
(306, 17, 'in', 'text', 'Gracias, la página de la,emisora,esta fuera de servicio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 11:06:18'),
(307, 17, 'out', 'text', 'podria indicarme su estacion porfavor y cual es su pagina web', NULL, NULL, NULL, NULL, NULL, 1, '3EB05E788B915A43A26B33', 'sent', NULL, '2026-04-01 11:06:44'),
(308, 17, 'out', 'text', 'señor Henrry sigue en linea?', NULL, NULL, NULL, NULL, NULL, 1, '3EB05E9338DDDD6988D42F', 'sent', NULL, '2026-04-01 11:15:38'),
(309, 8, 'in', 'image', '[image]', 'https://panelws.intermediahost.co/uploads/8/wa_69cd8318b018d7.38269201.jpg', NULL, 'image/jpeg', NULL, '[comprobante]', NULL, NULL, 'sent', NULL, '2026-04-01 15:42:00'),
(310, 8, 'in', 'audio', '[audio]', 'https://panelws.intermediahost.co/uploads/8/wa_69cd832cc221d5.23895627.ogg', NULL, 'audio/ogg; codecs=opus', NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 15:42:20'),
(311, 8, 'in', 'document', '[document]', 'https://panelws.intermediahost.co/uploads/8/wa_69cd834026a418.03390809.pdf', 'CamScanner 04-19-2023 16.43.pdf', 'application/pdf', NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 15:42:40'),
(312, 8, 'in', 'text', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 15:43:25'),
(313, 8, 'in', 'image', '[image]', 'https://panelws.intermediahost.co/uploads/8/wa_69cd8383ab2db7.03995611.jpg', NULL, 'image/jpeg', NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 15:43:47'),
(314, 8, 'out', 'image', 'Screenshot_3.png', 'https://panelws.intermediahost.co/uploads/8/f_69cd83f2e126d7.05673271_Screenshot_3.png', 'Screenshot_3.png', 'image/png', 150296, NULL, 1, '3EB08A3D74418E442AD694', 'sent', NULL, '2026-04-01 15:45:38'),
(315, 8, 'in', 'audio', '[audio]', 'https://panelws.intermediahost.co/uploads/8/wa_69cd84e3053058.37126106.ogg', NULL, 'audio/ogg; codecs=opus', NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 15:49:39'),
(316, 8, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, NULL, 'failed', 'Error de autenticación', '2026-04-01 16:51:03'),
(317, 8, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, NULL, 'failed', 'WhatsApp no está conectado', '2026-04-01 16:58:43'),
(318, 8, 'out', 'text', 'hola', NULL, NULL, NULL, NULL, NULL, 1, '3EB09099EE88882457A8A8', 'sent', NULL, '2026-04-01 16:59:01'),
(319, 10, 'in', 'text', 'Gracias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'sent', NULL, '2026-04-01 19:01:02'),
(320, 8, 'out', 'text', 'hola sdjsabda', NULL, NULL, NULL, NULL, NULL, 1, '3EB0241215095AFCDCE6F5', 'sent', NULL, '2026-04-02 10:46:05'),
(321, 8, 'out', 'text', 'sdfnlskfnlsznf', NULL, NULL, NULL, NULL, NULL, 1, '3EB05DE9DF04C964D37ACD', 'sent', NULL, '2026-04-02 10:46:09'),
(322, 8, 'out', 'text', 'apasionaaa', NULL, NULL, NULL, NULL, NULL, 1, '3EB067E4C084E0FE148165', 'sent', NULL, '2026-04-02 10:46:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `type` enum('new_conversation','new_message','assigned','resolved') NOT NULL DEFAULT 'new_message',
  `message` text NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `notifications`
--

INSERT INTO `notifications` (`id`, `agent_id`, `conversation_id`, `type`, `message`, `read_at`, `created_at`) VALUES
(1, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', '2026-03-28 16:44:14', '2026-03-28 16:38:24'),
(2, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-28 16:38:24'),
(3, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-28 16:38:24'),
(4, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', '2026-03-28 16:44:14', '2026-03-28 16:43:41'),
(5, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-28 16:43:41'),
(6, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-28 16:43:41'),
(7, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Video)', '2026-03-28 17:15:43', '2026-03-28 16:51:16'),
(8, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Video)', NULL, '2026-03-28 16:51:16'),
(9, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Video)', NULL, '2026-03-28 16:51:16'),
(10, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 17:15:43', '2026-03-28 16:52:16'),
(11, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 16:52:16'),
(12, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 16:52:16'),
(13, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 17:15:43', '2026-03-28 16:52:51'),
(14, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 16:52:51'),
(15, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 16:52:51'),
(16, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 17:15:43', '2026-03-28 16:55:28'),
(17, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 16:55:28'),
(18, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 16:55:28'),
(19, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 17:15:43', '2026-03-28 17:02:06'),
(20, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:02:06'),
(21, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:02:06'),
(22, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 17:15:43', '2026-03-28 17:02:37'),
(23, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:02:37'),
(24, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:02:37'),
(25, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 17:15:43', '2026-03-28 17:03:22'),
(26, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:03:22'),
(27, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:03:22'),
(28, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 17:15:47', '2026-03-28 17:06:21'),
(29, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:06:21'),
(30, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:06:21'),
(31, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Dominios)', '2026-03-28 21:27:13', '2026-03-28 17:22:11'),
(32, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Dominios)', NULL, '2026-03-28 17:22:11'),
(33, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Dominios)', NULL, '2026-03-28 17:22:11'),
(34, 2, 1, 'assigned', 'Administrador tomó la conversación de Edisson Medina', NULL, '2026-03-28 17:22:32'),
(35, 3, 1, 'assigned', 'Administrador tomó la conversación de Edisson Medina', NULL, '2026-03-28 17:22:32'),
(36, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 21:27:13', '2026-03-28 17:23:05'),
(37, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:23:05'),
(38, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:23:05'),
(39, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 21:27:13', '2026-03-28 17:23:25'),
(40, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:23:25'),
(41, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:23:25'),
(42, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 17:28:42', '2026-03-28 17:23:28'),
(43, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:23:28'),
(44, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 17:23:28'),
(45, 1, 2, 'new_message', 'Nuevo mensaje de DJ EDME (Ventas - Streaming Radio)', '2026-03-28 21:27:13', '2026-03-28 17:33:33'),
(46, 2, 2, 'new_message', 'Nuevo mensaje de DJ EDME (Ventas - Streaming Radio)', NULL, '2026-03-28 17:33:33'),
(47, 3, 2, 'new_message', 'Nuevo mensaje de DJ EDME (Ventas - Streaming Radio)', NULL, '2026-03-28 17:33:33'),
(48, 1, 2, 'new_message', 'Nuevo mensaje de DJ EDME (Soporte - Streaming Radio)', '2026-03-28 21:27:13', '2026-03-28 19:50:17'),
(49, 2, 2, 'new_message', 'Nuevo mensaje de DJ EDME (Soporte - Streaming Radio)', NULL, '2026-03-28 19:50:17'),
(50, 3, 2, 'new_message', 'Nuevo mensaje de DJ EDME (Soporte - Streaming Radio)', NULL, '2026-03-28 19:50:17'),
(51, 2, 1, 'assigned', 'Administrador te transfirió la conversación de Edisson Medina', NULL, '2026-03-28 20:14:46'),
(52, 1, 2, 'new_message', 'Nuevo mensaje de DJ EDME (Ventas - Streaming Video)', '2026-03-28 21:27:13', '2026-03-28 20:17:05'),
(53, 2, 2, 'new_message', 'Nuevo mensaje de DJ EDME (Ventas - Streaming Video)', NULL, '2026-03-28 20:17:05'),
(54, 3, 2, 'new_message', 'Nuevo mensaje de DJ EDME (Ventas - Streaming Video)', NULL, '2026-03-28 20:17:05'),
(55, 1, 2, 'new_message', 'Nuevo mensaje de DJ EDME', '2026-03-28 21:27:13', '2026-03-28 20:17:42'),
(56, 2, 2, 'new_message', 'Nuevo mensaje de DJ EDME', NULL, '2026-03-28 20:17:42'),
(57, 3, 2, 'new_message', 'Nuevo mensaje de DJ EDME', NULL, '2026-03-28 20:17:42'),
(58, 1, 2, 'new_message', 'Nuevo mensaje de DJ EDME', '2026-03-28 21:27:13', '2026-03-28 20:17:45'),
(59, 2, 2, 'new_message', 'Nuevo mensaje de DJ EDME', NULL, '2026-03-28 20:17:45'),
(60, 3, 2, 'new_message', 'Nuevo mensaje de DJ EDME', NULL, '2026-03-28 20:17:45'),
(61, 1, 2, 'new_message', 'Nuevo mensaje de DJ EDME', '2026-03-28 21:27:13', '2026-03-28 20:18:01'),
(62, 2, 2, 'new_message', 'Nuevo mensaje de DJ EDME', NULL, '2026-03-28 20:18:01'),
(63, 3, 2, 'new_message', 'Nuevo mensaje de DJ EDME', NULL, '2026-03-28 20:18:01'),
(64, 1, 2, 'assigned', 'Asesor Ventas 1 te transfirió la conversación de DJ EDME', '2026-03-28 21:27:13', '2026-03-28 20:18:37'),
(65, 1, 2, 'new_message', 'Nuevo mensaje de DJ EDME', '2026-03-28 21:27:13', '2026-03-28 20:19:21'),
(66, 2, 2, 'new_message', 'Nuevo mensaje de DJ EDME', NULL, '2026-03-28 20:19:21'),
(67, 3, 2, 'new_message', 'Nuevo mensaje de DJ EDME', NULL, '2026-03-28 20:19:21'),
(68, 2, 2, 'assigned', 'Administrador tomó la conversación de DJ EDME', NULL, '2026-03-28 20:20:58'),
(69, 3, 2, 'assigned', 'Administrador tomó la conversación de DJ EDME', NULL, '2026-03-28 20:20:58'),
(70, 1, 3, 'new_message', 'Nuevo mensaje de Leandra👸🏼 (Ventas - Streaming Video)', '2026-03-28 21:57:06', '2026-03-28 21:32:42'),
(71, 2, 3, 'new_message', 'Nuevo mensaje de Leandra👸🏼 (Ventas - Streaming Video)', NULL, '2026-03-28 21:32:42'),
(72, 3, 3, 'new_message', 'Nuevo mensaje de Leandra👸🏼 (Ventas - Streaming Video)', NULL, '2026-03-28 21:32:42'),
(73, 1, 3, 'new_message', 'Nuevo mensaje de Leandra👸🏼', '2026-03-28 21:57:06', '2026-03-28 21:33:03'),
(74, 2, 3, 'new_message', 'Nuevo mensaje de Leandra👸🏼', NULL, '2026-03-28 21:33:03'),
(75, 3, 3, 'new_message', 'Nuevo mensaje de Leandra👸🏼', NULL, '2026-03-28 21:33:03'),
(76, 1, 3, 'new_message', 'Nuevo mensaje de Leandra👸🏼', '2026-03-28 21:57:06', '2026-03-28 21:33:30'),
(77, 2, 3, 'new_message', 'Nuevo mensaje de Leandra👸🏼', NULL, '2026-03-28 21:33:30'),
(78, 3, 3, 'new_message', 'Nuevo mensaje de Leandra👸🏼', NULL, '2026-03-28 21:33:30'),
(79, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', '2026-03-28 21:57:06', '2026-03-28 21:38:48'),
(80, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', NULL, '2026-03-28 21:38:48'),
(81, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', NULL, '2026-03-28 21:38:48'),
(82, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', '2026-03-28 21:57:06', '2026-03-28 21:47:20'),
(83, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-28 21:47:20'),
(84, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-28 21:47:20'),
(85, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', '2026-03-28 21:57:06', '2026-03-28 21:47:46'),
(86, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', NULL, '2026-03-28 21:47:46'),
(87, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', NULL, '2026-03-28 21:47:46'),
(88, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 21:57:06', '2026-03-28 21:48:24'),
(89, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 21:48:24'),
(90, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 21:48:24'),
(91, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 21:57:06', '2026-03-28 21:48:35'),
(92, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 21:48:35'),
(93, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 21:48:35'),
(94, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 21:57:06', '2026-03-28 21:48:46'),
(95, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 21:48:46'),
(96, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 21:48:46'),
(97, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-28 22:16:24', '2026-03-28 21:49:07'),
(98, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 21:49:07'),
(99, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-28 21:49:07'),
(100, 1, 5, 'new_message', 'Nuevo mensaje de DJ EDME 🎧 (Ventas - Streaming Radio)', '2026-03-29 03:32:43', '2026-03-28 21:57:59'),
(101, 2, 5, 'new_message', 'Nuevo mensaje de DJ EDME 🎧 (Ventas - Streaming Radio)', NULL, '2026-03-28 21:57:59'),
(102, 3, 5, 'new_message', 'Nuevo mensaje de DJ EDME 🎧 (Ventas - Streaming Radio)', NULL, '2026-03-28 21:57:59'),
(103, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', '2026-03-29 03:32:43', '2026-03-28 22:29:04'),
(104, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-28 22:29:04'),
(105, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-28 22:29:04'),
(106, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Radio)', '2026-03-29 02:42:56', '2026-03-28 22:29:43'),
(107, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Radio)', NULL, '2026-03-28 22:29:43'),
(108, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Radio)', NULL, '2026-03-28 22:29:43'),
(109, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Radio)', '2026-03-29 03:32:43', '2026-03-29 03:26:46'),
(110, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Radio)', NULL, '2026-03-29 03:26:46'),
(111, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Radio)', NULL, '2026-03-29 03:26:46'),
(112, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-29 03:32:43', '2026-03-29 03:27:12'),
(113, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-29 03:27:12'),
(114, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-29 03:27:12'),
(115, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Otros)', '2026-03-29 03:32:43', '2026-03-29 03:28:54'),
(116, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Otros)', NULL, '2026-03-29 03:28:54'),
(117, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Otros)', NULL, '2026-03-29 03:28:54'),
(118, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Medios de Pago)', '2026-03-29 03:32:43', '2026-03-29 03:30:43'),
(119, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Medios de Pago)', NULL, '2026-03-29 03:30:43'),
(120, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Medios de Pago)', NULL, '2026-03-29 03:30:43'),
(121, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-31 13:25:00', '2026-03-29 13:53:12'),
(122, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-29 13:53:12'),
(123, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-29 13:53:12'),
(124, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', '2026-03-31 13:25:00', '2026-03-29 13:53:46'),
(125, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-29 13:53:46'),
(126, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-29 13:53:46'),
(127, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', '2026-03-31 13:25:00', '2026-03-29 13:59:13'),
(128, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-29 13:59:13'),
(129, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-29 13:59:13'),
(130, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-31 13:25:00', '2026-03-29 14:00:21'),
(131, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-29 14:00:21'),
(132, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-29 14:00:21'),
(133, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Radio)', '2026-03-31 13:25:00', '2026-03-29 15:47:32'),
(134, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Radio)', NULL, '2026-03-29 15:47:32'),
(135, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Soporte - Streaming Radio)', NULL, '2026-03-29 15:47:32'),
(148, 2, 1, 'assigned', 'Intermedia Host te transfirió la conversación de Edisson Medina', NULL, '2026-03-29 18:50:07'),
(149, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', '2026-03-31 13:25:00', '2026-03-29 18:54:33'),
(150, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', NULL, '2026-03-29 18:54:33'),
(151, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', NULL, '2026-03-29 18:54:33'),
(152, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-31 13:25:00', '2026-03-29 18:55:02'),
(153, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-29 18:55:02'),
(154, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-29 18:55:02'),
(155, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-31 13:25:00', '2026-03-29 19:08:43'),
(156, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-29 19:08:43'),
(157, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-29 19:08:43'),
(158, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Hosting Web)', '2026-03-31 13:25:00', '2026-03-29 19:12:07'),
(159, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Hosting Web)', NULL, '2026-03-29 19:12:07'),
(160, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Hosting Web)', NULL, '2026-03-29 19:12:07'),
(161, 2, 1, 'assigned', 'Intermedia Host te transfirió la conversación de Edisson Medina', NULL, '2026-03-29 19:13:05'),
(162, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', '2026-03-31 13:25:00', '2026-03-30 15:14:46'),
(163, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-30 15:14:46'),
(164, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming Radio)', NULL, '2026-03-30 15:14:46'),
(165, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-31 13:25:00', '2026-03-30 15:16:22'),
(166, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-30 15:16:22'),
(167, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-30 15:16:22'),
(168, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-03-31 13:25:00', '2026-03-30 15:16:37'),
(169, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-30 15:16:37'),
(170, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-03-30 15:16:37'),
(171, 1, 13, 'new_message', 'Nuevo mensaje de Radio Por Fe Stereo🔊🔊 (Soporte - Streaming Radio)', '2026-04-01 00:49:08', '2026-03-31 18:27:58'),
(172, 4, 13, 'new_message', 'Nuevo mensaje de Radio Por Fe Stereo🔊🔊 (Soporte - Streaming Radio)', NULL, '2026-03-31 18:27:58'),
(173, 5, 13, 'new_message', 'Nuevo mensaje de Radio Por Fe Stereo🔊🔊 (Soporte - Streaming Radio)', NULL, '2026-03-31 18:27:58'),
(174, 1, 14, 'new_message', 'Nuevo mensaje de Ian Carlos (Soporte - Streaming Radio)', '2026-04-01 11:07:16', '2026-04-01 08:15:26'),
(175, 4, 14, 'new_message', 'Nuevo mensaje de Ian Carlos (Soporte - Streaming Radio)', NULL, '2026-04-01 08:15:26'),
(176, 5, 14, 'new_message', 'Nuevo mensaje de Ian Carlos (Soporte - Streaming Radio)', NULL, '2026-04-01 08:15:26'),
(177, 1, 15, 'new_message', 'Nuevo mensaje de jpl publicidad (Soporte - Streaming Radio)', '2026-04-01 11:07:16', '2026-04-01 08:25:16'),
(178, 4, 15, 'new_message', 'Nuevo mensaje de jpl publicidad (Soporte - Streaming Radio)', NULL, '2026-04-01 08:25:16'),
(179, 5, 15, 'new_message', 'Nuevo mensaje de jpl publicidad (Soporte - Streaming Radio)', NULL, '2026-04-01 08:25:16'),
(180, 1, 15, 'new_message', 'Nuevo mensaje de jpl publicidad', '2026-04-01 11:07:16', '2026-04-01 08:25:50'),
(181, 4, 15, 'new_message', 'Nuevo mensaje de jpl publicidad', NULL, '2026-04-01 08:25:50'),
(182, 5, 15, 'new_message', 'Nuevo mensaje de jpl publicidad', NULL, '2026-04-01 08:25:50'),
(183, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys (Soporte - Streaming Radio)', '2026-04-01 11:07:16', '2026-04-01 09:02:20'),
(184, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys (Soporte - Streaming Radio)', NULL, '2026-04-01 09:02:20'),
(185, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys (Soporte - Streaming Radio)', NULL, '2026-04-01 09:02:20'),
(186, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:08:34'),
(187, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:08:34'),
(188, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:08:34'),
(189, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:13:31'),
(190, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:13:31'),
(191, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:13:31'),
(192, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:19:04'),
(193, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:19:04'),
(194, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:19:04'),
(195, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:20:00'),
(196, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:20:00'),
(197, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:20:00'),
(198, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:20:16'),
(199, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:20:16'),
(200, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:20:16'),
(201, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:22:10'),
(202, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:22:10'),
(203, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:22:10'),
(204, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:22:26'),
(205, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:22:26'),
(206, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:22:26'),
(207, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:22:42'),
(208, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:22:42'),
(209, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:22:42'),
(210, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:23:51'),
(211, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:23:51'),
(212, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:23:51'),
(213, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:24:18'),
(214, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:24:18'),
(215, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:24:18'),
(216, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:24:40'),
(217, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:24:40'),
(218, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:24:40'),
(219, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:24:50'),
(220, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:24:50'),
(221, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:24:50'),
(222, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:33:33'),
(223, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:33:33'),
(224, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:33:33'),
(225, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:33:36'),
(226, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:33:36'),
(227, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:33:36'),
(228, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:34:03'),
(229, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:34:03'),
(230, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:34:03'),
(231, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:34:16'),
(232, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:34:16'),
(233, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:34:16'),
(234, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:35:01'),
(235, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:35:01'),
(236, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:35:01'),
(237, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:46:33'),
(238, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:46:33'),
(239, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:46:33'),
(240, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:46:57'),
(241, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:46:57'),
(242, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:46:57'),
(243, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:48:07'),
(244, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:48:07'),
(245, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:48:07'),
(246, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:49:12'),
(247, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:49:12'),
(248, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:49:12'),
(249, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:52:26'),
(250, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:52:26'),
(251, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:52:26'),
(252, 1, 14, 'new_message', 'Nuevo mensaje de Ian Carlos', '2026-04-01 11:07:16', '2026-04-01 09:52:40'),
(253, 4, 14, 'new_message', 'Nuevo mensaje de Ian Carlos', NULL, '2026-04-01 09:52:40'),
(254, 5, 14, 'new_message', 'Nuevo mensaje de Ian Carlos', NULL, '2026-04-01 09:52:40'),
(255, 1, 14, 'new_message', 'Nuevo mensaje de Ian Carlos', '2026-04-01 11:07:16', '2026-04-01 09:52:41'),
(256, 4, 14, 'new_message', 'Nuevo mensaje de Ian Carlos', NULL, '2026-04-01 09:52:41'),
(257, 5, 14, 'new_message', 'Nuevo mensaje de Ian Carlos', NULL, '2026-04-01 09:52:41'),
(258, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:56:25'),
(259, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:56:25'),
(260, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:56:25'),
(261, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:56:51'),
(262, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:56:51'),
(263, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:56:51'),
(264, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:57:30'),
(265, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:57:30'),
(266, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:57:30'),
(267, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:57:43'),
(268, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:57:43'),
(269, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:57:43'),
(270, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:58:04'),
(271, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:58:04'),
(272, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:58:04'),
(273, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:58:07'),
(274, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:58:07'),
(275, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:58:07'),
(276, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:59:15'),
(277, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:59:15'),
(278, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:59:15'),
(279, 1, 16, 'new_message', 'Nuevo mensaje de Emaurys', '2026-04-01 11:07:16', '2026-04-01 09:59:18'),
(280, 4, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:59:18'),
(281, 5, 16, 'new_message', 'Nuevo mensaje de Emaurys', NULL, '2026-04-01 09:59:18'),
(282, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', '2026-04-01 11:07:16', '2026-04-01 10:51:29'),
(283, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', NULL, '2026-04-01 10:51:29'),
(284, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina (Ventas - Streaming AutoDJ)', NULL, '2026-04-01 10:51:29'),
(285, 1, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', '2026-04-01 11:07:16', '2026-04-01 10:51:43'),
(286, 2, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-04-01 10:51:43'),
(287, 3, 1, 'new_message', 'Nuevo mensaje de Edisson Medina', NULL, '2026-04-01 10:51:43'),
(288, 1, 17, 'new_message', 'Nuevo mensaje de 𝓗𝓮𝓷𝓻𝔂 𝓟𝓪𝓬𝓱𝓮𝓬𝓸 𝓒𝓪𝓼𝓪𝓭𝓲𝓮𝓰𝓸 (Soporte - Streaming Radio)', '2026-04-01 11:04:43', '2026-04-01 11:02:26'),
(289, 4, 17, 'new_message', 'Nuevo mensaje de 𝓗𝓮𝓷𝓻𝔂 𝓟𝓪𝓬𝓱𝓮𝓬𝓸 𝓒𝓪𝓼𝓪𝓭𝓲𝓮𝓰𝓸 (Soporte - Streaming Radio)', NULL, '2026-04-01 11:02:26'),
(290, 5, 17, 'new_message', 'Nuevo mensaje de 𝓗𝓮𝓷𝓻𝔂 𝓟𝓪𝓬𝓱𝓮𝓬𝓸 𝓒𝓪𝓼𝓪𝓭𝓲𝓮𝓰𝓸 (Soporte - Streaming Radio)', NULL, '2026-04-01 11:02:26'),
(291, 1, 17, 'new_message', 'Nuevo mensaje de 𝓗𝓮𝓷𝓻𝔂 𝓟𝓪𝓬𝓱𝓮𝓬𝓸 𝓒𝓪𝓼𝓪𝓭𝓲𝓮𝓰𝓸', '2026-04-01 11:07:16', '2026-04-01 11:06:18'),
(292, 4, 17, 'new_message', 'Nuevo mensaje de 𝓗𝓮𝓷𝓻𝔂 𝓟𝓪𝓬𝓱𝓮𝓬𝓸 𝓒𝓪𝓼𝓪𝓭𝓲𝓮𝓰𝓸', NULL, '2026-04-01 11:06:18'),
(293, 5, 17, 'new_message', 'Nuevo mensaje de 𝓗𝓮𝓷𝓻𝔂 𝓟𝓪𝓬𝓱𝓮𝓬𝓸 𝓒𝓪𝓼𝓪𝓭𝓲𝓮𝓰𝓸', NULL, '2026-04-01 11:06:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`, `updated_at`) VALUES
('business_hours', '{\"1\":{\"open\":true,\"start\":\"08:00\",\"end\":\"18:00\"},\"2\":{\"open\":true,\"start\":\"08:00\",\"end\":\"18:00\"},\"3\":{\"open\":true,\"start\":\"08:00\",\"end\":\"18:00\"},\"4\":{\"open\":true,\"start\":\"08:00\",\"end\":\"18:00\"},\"5\":{\"open\":true,\"start\":\"08:00\",\"end\":\"18:00\"},\"6\":{\"open\":true,\"start\":\"08:00\",\"end\":\"14:00\"},\"7\":{\"open\":false,\"start\":\"00:00\",\"end\":\"00:00\"}}', 'Horarios de atención por día (1=Lun … 7=Dom)', '2026-03-30 13:45:58'),
('force_schedule', 'closed', 'Forzar horario: auto | open | closed', '2026-04-02 09:23:21'),
('out_of_hours_message', '', 'Mensaje personalizado fuera de horario (vacío = mensaje por defecto)', '2026-03-28 16:36:45'),
('timezone', 'America/Bogota', 'Zona horaria usada para evaluar horarios', '2026-03-28 16:36:45');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_username` (`username`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- Indices de la tabla `agent_departments`
--
ALTER TABLE `agent_departments`
  ADD PRIMARY KEY (`agent_id`,`department_id`),
  ADD KEY `fk_ad_dept` (`department_id`);

--
-- Indices de la tabla `agent_sessions`
--
ALTER TABLE `agent_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `fk_sess_agent` (`agent_id`);

--
-- Indices de la tabla `bot_estados`
--
ALTER TABLE `bot_estados`
  ADD PRIMARY KEY (`ses_key`);

--
-- Indices de la tabla `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_conv_key` (`conv_key`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_agent` (`agent_id`),
  ADD KEY `idx_last_msg` (`last_message_at`),
  ADD KEY `fk_conv_resolved` (`resolved_by`);

--
-- Indices de la tabla `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug` (`slug`);

--
-- Indices de la tabla `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip`,`attempted_at`);

--
-- Indices de la tabla `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conv` (`conversation_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `fk_msg_agent` (`agent_id`);

--
-- Indices de la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agent_unread` (`agent_id`,`read_at`),
  ADD KEY `fk_notif_conv` (`conversation_id`);

--
-- Indices de la tabla `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `agents`
--
ALTER TABLE `agents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `agent_sessions`
--
ALTER TABLE `agent_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT de la tabla `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133377;

--
-- AUTO_INCREMENT de la tabla `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=323;

--
-- AUTO_INCREMENT de la tabla `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=294;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `agent_departments`
--
ALTER TABLE `agent_departments`
  ADD CONSTRAINT `fk_ad_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ad_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `agent_sessions`
--
ALTER TABLE `agent_sessions`
  ADD CONSTRAINT `fk_sess_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conv_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conv_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conv_resolved` FOREIGN KEY (`resolved_by`) REFERENCES `agents` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
