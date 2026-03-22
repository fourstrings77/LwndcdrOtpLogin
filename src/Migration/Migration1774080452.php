<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1774080452 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774080452;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `lwndcdr_customer_otp` (
                `id`                    BINARY(16)      NOT NULL,
                `customer_id`           BINARY(16)      NOT NULL,
                `totp_secret`           VARCHAR(512)    NULL,
                `totp_enabled`          TINYINT(1)      NOT NULL DEFAULT 0,
                `email_otp_enabled`     TINYINT(1)      NOT NULL DEFAULT 0,
                `email_otp_code`        VARCHAR(255)    NULL,
                `email_otp_expires_at`  DATETIME(3)     NULL,
                `created_at`            DATETIME(3)     NOT NULL,
                `updated_at`            DATETIME(3)     NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.lwndcdr_customer_otp.customer_id` (`customer_id`),
                CONSTRAINT `fk.lwndcdr_customer_otp.customer_id`
                    FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
