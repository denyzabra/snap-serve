<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250712124854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE business_hours (id INT AUTO_INCREMENT NOT NULL, restaurant_id INT NOT NULL, day_of_week VARCHAR(10) NOT NULL, open_time TIME DEFAULT NULL, close_time TIME DEFAULT NULL, is_open TINYINT(1) NOT NULL, is24_hours TINYINT(1) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F4FB5A32B1E7706E (restaurant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE verification_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, expires_at DATETIME NOT NULL, is_used TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_C1CC006B5F37A13B (token), INDEX IDX_C1CC006BA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE business_hours ADD CONSTRAINT FK_F4FB5A32B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE verification_token ADD CONSTRAINT FK_C1CC006BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE restaurant ADD slug VARCHAR(255) DEFAULT NULL, ADD city VARCHAR(100) DEFAULT NULL, ADD state VARCHAR(100) DEFAULT NULL, ADD postal_code VARCHAR(20) DEFAULT NULL, ADD country VARCHAR(100) DEFAULT NULL, ADD cover_image_url VARCHAR(500) DEFAULT NULL, ADD cuisine_type VARCHAR(50) DEFAULT NULL, ADD service_types JSON DEFAULT NULL, ADD primary_color VARCHAR(7) DEFAULT NULL, ADD secondary_color VARCHAR(7) DEFAULT NULL, ADD special_instructions LONGTEXT DEFAULT NULL, ADD accepts_reservations TINYINT(1) DEFAULT NULL, ADD has_delivery TINYINT(1) DEFAULT NULL, ADD has_takeout TINYINT(1) DEFAULT NULL, ADD minimum_order_amount NUMERIC(10, 2) DEFAULT NULL, ADD delivery_fee NUMERIC(10, 2) DEFAULT NULL, ADD estimated_delivery_time INT DEFAULT NULL, ADD is_verified TINYINT(1) DEFAULT NULL, CHANGE email email VARCHAR(180) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_EB95123F989D9B62 ON restaurant (slug)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD email_verified TINYINT(1) NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE business_hours DROP FOREIGN KEY FK_F4FB5A32B1E7706E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE verification_token DROP FOREIGN KEY FK_C1CC006BA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE business_hours
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE verification_token
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_EB95123F989D9B62 ON restaurant
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE restaurant DROP slug, DROP city, DROP state, DROP postal_code, DROP country, DROP cover_image_url, DROP cuisine_type, DROP service_types, DROP primary_color, DROP secondary_color, DROP special_instructions, DROP accepts_reservations, DROP has_delivery, DROP has_takeout, DROP minimum_order_amount, DROP delivery_fee, DROP estimated_delivery_time, DROP is_verified, CHANGE email email VARCHAR(180) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP email_verified
        SQL);
    }
}
