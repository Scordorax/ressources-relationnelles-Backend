<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427095415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, start_at DATETIME NOT NULL, end_at DATETIME DEFAULT NULL, max_participants INT DEFAULT NULL, created_at DATETIME NOT NULL, created_by_id INT NOT NULL, INDEX IDX_AC74095AB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, is_reported TINYINT NOT NULL, user_id INT NOT NULL, resource_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_9474526CA76ED395 (user_id), INDEX IDX_9474526C89329D25 (resource_id), INDEX IDX_9474526C727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE refresh_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(128) NOT NULL, user_id INT NOT NULL, expires_at DATETIME NOT NULL, revoked TINYINT NOT NULL, UNIQUE INDEX UNIQ_C74F21955F37A13B (token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE resource (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, visibility VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, author_id INT NOT NULL, category_id INT DEFAULT NULL, INDEX IDX_BC91F416F675F31B (author_id), INDEX IDX_BC91F41612469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE resource_interaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, resource_id INT NOT NULL, INDEX IDX_3336C3F5A76ED395 (user_id), INDEX IDX_3336C3F589329D25 (resource_id), UNIQUE INDEX UNIQ_USER_RESOURCE_TYPE (user_id, resource_id, type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE statistics (id INT AUTO_INCREMENT NOT NULL, views INT NOT NULL, search_count INT NOT NULL, created_at DATETIME NOT NULL, resource_id INT NOT NULL, INDEX IDX_E2D38B2289329D25 (resource_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, firstname VARCHAR(255) NOT NULL, lastname VARCHAR(255) NOT NULL, is_verified TINYINT NOT NULL, created_at DATETIME NOT NULL, france_connect_id VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C89329D25 FOREIGN KEY (resource_id) REFERENCES resource (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C727ACA70 FOREIGN KEY (parent_id) REFERENCES comment (id)');
        $this->addSql('ALTER TABLE resource ADD CONSTRAINT FK_BC91F416F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE resource ADD CONSTRAINT FK_BC91F41612469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE resource_interaction ADD CONSTRAINT FK_3336C3F5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE resource_interaction ADD CONSTRAINT FK_3336C3F589329D25 FOREIGN KEY (resource_id) REFERENCES resource (id)');
        $this->addSql('ALTER TABLE statistics ADD CONSTRAINT FK_E2D38B2289329D25 FOREIGN KEY (resource_id) REFERENCES resource (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095AB03A8386');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C89329D25');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C727ACA70');
        $this->addSql('ALTER TABLE resource DROP FOREIGN KEY FK_BC91F416F675F31B');
        $this->addSql('ALTER TABLE resource DROP FOREIGN KEY FK_BC91F41612469DE2');
        $this->addSql('ALTER TABLE resource_interaction DROP FOREIGN KEY FK_3336C3F5A76ED395');
        $this->addSql('ALTER TABLE resource_interaction DROP FOREIGN KEY FK_3336C3F589329D25');
        $this->addSql('ALTER TABLE statistics DROP FOREIGN KEY FK_E2D38B2289329D25');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE resource');
        $this->addSql('DROP TABLE resource_interaction');
        $this->addSql('DROP TABLE statistics');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
