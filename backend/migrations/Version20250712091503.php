<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250712091503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE menu_item ADD is_active TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD table_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD CONSTRAINT FK_F5299398ECFF285C FOREIGN KEY (table_id) REFERENCES `table` (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F5299398ECFF285C ON `order` (table_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE restaurant ADD logo_url VARCHAR(500) DEFAULT NULL, CHANGE string address VARCHAR(500) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_F6298F467D8B1FB5 ON `table` (qr_code)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD first_name VARCHAR(100) DEFAULT NULL, ADD last_name VARCHAR(100) DEFAULT NULL, ADD phone_number VARCHAR(20) DEFAULT NULL, ADD is_active TINYINT(1) NOT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, ADD last_login_at DATETIME DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE restaurant ADD string VARCHAR(500) DEFAULT NULL, DROP address, DROP logo_url
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398ECFF285C
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_F5299398ECFF285C ON `order`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` DROP table_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_F6298F467D8B1FB5 ON `table`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE menu_item DROP is_active
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP first_name, DROP last_name, DROP phone_number, DROP is_active, DROP created_at, DROP updated_at, DROP last_login_at
        SQL);
    }
}
