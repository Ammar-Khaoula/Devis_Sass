<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617111751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_6B71CBF496901F54 ON quote');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_QUOTE_USER_NUMBER ON quote (user_id, number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_QUOTE_USER_NUMBER ON quote');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6B71CBF496901F54 ON quote (number)');
    }
}
