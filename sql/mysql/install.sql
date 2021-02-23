CREATE TABLE IF NOT EXISTS `#__socialmagick_images` (
    `hash` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `last_access` datetime NOT NULL,
    PRIMARY KEY (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;