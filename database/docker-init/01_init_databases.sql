CREATE DATABASE IF NOT EXISTS `db_lcs`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `db_siaw`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `db_sincro`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `db_lcs`.*    TO 'sail'@'%';
GRANT ALL PRIVILEGES ON `db_siaw`.*   TO 'sail'@'%';
GRANT ALL PRIVILEGES ON `db_sincro`.* TO 'sail'@'%';

FLUSH PRIVILEGES;
