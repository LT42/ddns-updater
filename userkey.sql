CREATE TABLE `userkey` (
  `id` int(11) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `lastlogin` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `userkey`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `userkey`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
