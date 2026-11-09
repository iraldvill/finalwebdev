<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261109144915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking_station (id INT AUTO_INCREMENT NOT NULL, station_number INT NOT NULL, booking_date DATE NOT NULL, booking_time TIME NOT NULL, booking_time_end TIME NOT NULL, booking_id INT NOT NULL, INDEX IDX_C587BD913301C60 (booking_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE booking_station ADD CONSTRAINT FK_C587BD913301C60 FOREIGN KEY (booking_id) REFERENCES booking (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_station DROP FOREIGN KEY FK_C587BD913301C60');
        $this->addSql('DROP TABLE booking_station');
    }
}
