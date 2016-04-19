BEGIN TRANSACTION;

CREATE TABLE IF NOT EXISTS `featured_flags` (
  `id` int(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` int(1) DEFAULT 0,
  `start_date` timestamp DEFAULT NULL,
  `end_date` timestamp DEFAULT NULL,
  `params` TEXT DEFAULT NULL,
  `return_params` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`)
);

END TRANSACTION;
