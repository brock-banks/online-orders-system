-- Create Map table to store places
DROP TABLE IF EXISTS `Map`;

CREATE TABLE `Map` (
  `PlaceID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `PlaceName` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`PlaceID`),
  UNIQUE KEY `ux_map_placename` (`PlaceName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed examples
INSERT INTO `Map` (PlaceName) VALUES ('Shop1'), ('Shop2'), ('Shop3'), ('Cargo');