<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Missing indexes on items: client_id (admin pages join), created_at and expires_at (hot items view, expired intents cleaning)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX `client_id` ON `items` (`client_id`)');
        $this->addSql('CREATE INDEX `created_at` ON `items` (`created_at`)');
        $this->addSql('CREATE INDEX `expires_at` ON `items` (`expires_at`)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX `client_id` ON `items`');
        $this->addSql('DROP INDEX `created_at` ON `items`');
        $this->addSql('DROP INDEX `expires_at` ON `items`');
    }
}
