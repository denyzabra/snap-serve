<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250712142312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE staff_invitation (id INT AUTO_INCREMENT NOT NULL, restaurant_id INT NOT NULL, invited_by_id INT NOT NULL, user_id INT DEFAULT NULL, cancelled_by_id INT DEFAULT NULL, removed_by_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, role VARCHAR(50) NOT NULL, token VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, removed_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_174F71735F37A13B (token), INDEX IDX_174F7173B1E7706E (restaurant_id), INDEX IDX_174F7173A7B4A7E3 (invited_by_id), INDEX IDX_174F7173A76ED395 (user_id), INDEX IDX_174F7173187B2D12 (cancelled_by_id), INDEX IDX_174F71732BD701DA (removed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE staff_invitation ADD CONSTRAINT FK_174F7173B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE staff_invitation ADD CONSTRAINT FK_174F7173A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE staff_invitation ADD CONSTRAINT FK_174F7173A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE staff_invitation ADD CONSTRAINT FK_174F7173187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE staff_invitation ADD CONSTRAINT FK_174F71732BD701DA FOREIGN KEY (removed_by_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE staff_invitation DROP FOREIGN KEY FK_174F7173B1E7706E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE staff_invitation DROP FOREIGN KEY FK_174F7173A7B4A7E3
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE staff_invitation DROP FOREIGN KEY FK_174F7173A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE staff_invitation DROP FOREIGN KEY FK_174F7173187B2D12
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE staff_invitation DROP FOREIGN KEY FK_174F71732BD701DA
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE staff_invitation
        SQL);
    }
}
