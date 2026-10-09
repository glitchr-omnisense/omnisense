<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009071636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE extensionAbstract CHANGE action action ENUM(\'ACTION_DELETE\', \'ACTION_INSERT\', \'ACTION_UPDATE\') NOT NULL COMMENT \'(DC2Type:entity_action) ACTION_DELETE,ACTION_INSERT,ACTION_UPDATE\'');
        $this->addSql('ALTER TABLE layoutAttributeAdapterScore CHANGE operation operation ENUM(\'EQUAL\', \'GREATER\', \'GREATER_EQUAL\', \'LESS\', \'LESS_EQUAL\', \'NOTEQUAL\') NOT NULL COMMENT \'(DC2Type:operation) EQUAL,GREATER,GREATER_EQUAL,LESS,LESS_EQUAL,NOTEQUAL\'');
        $this->addSql('ALTER TABLE layoutImage CHANGE quadrant quadrant ENUM(\'EAST\', \'NORTH\', \'NORTHEAST\', \'NORTHWEST\', \'ORIGIN\', \'SOUTH\', \'SOUTHEAST\', \'SOUTHWEST\', \'WEST\') NOT NULL COMMENT \'(DC2Type:quadrant8) EAST,NORTH,NORTHEAST,NORTHWEST,ORIGIN,SOUTH,SOUTHEAST,SOUTHWEST,WEST\'');
        $this->addSql('ALTER TABLE thread CHANGE state state ENUM(\'STATE_DELETE\', \'STATE_DRAFT\', \'STATE_FUTURE\', \'STATE_PUBLISH\', \'STATE_PUBLISH_ARCHIVE\', \'STATE_PUBLISH_PASSWORD\', \'STATE_PUBLISH_SECRET\') NOT NULL COMMENT \'(DC2Type:thread_state) STATE_DELETE,STATE_DRAFT,STATE_FUTURE,STATE_PUBLISH,STATE_PUBLISH_ARCHIVE,STATE_PUBLISH_PASSWORD,STATE_PUBLISH_SECRET\', CHANGE workflow workflow ENUM(\'WORKFLOW_APPROVED\', \'WORKFLOW_PENDING\', \'WORKFLOW_REJECTED\', \'WORKFLOW_REVIEWING\', \'WORKFLOW_SUBMITTED\', \'WORKFLOW_SUSPENDED\') NOT NULL COMMENT \'(DC2Type:workflow_state) WORKFLOW_APPROVED,WORKFLOW_PENDING,WORKFLOW_REJECTED,WORKFLOW_REVIEWING,WORKFLOW_SUBMITTED,WORKFLOW_SUSPENDED\'');
        $this->addSql('ALTER TABLE user CHANGE roles roles SET(\'ROLE_ADMIN\', \'ROLE_EDITOR\', \'ROLE_SUPERADMIN\', \'ROLE_USER\') NOT NULL COMMENT \'(DC2Type:user_role) ROLE_ADMIN,ROLE_EDITOR,ROLE_SUPERADMIN,ROLE_USER\', CHANGE states states SET(\'USER_APPROVED\', \'USER_BANNED\', \'USER_ENABLED\', \'USER_GHOST\', \'USER_KICKED\', \'USER_LOCKED\', \'USER_NEWCOMER\', \'USER_VERIFIED\') NOT NULL COMMENT \'(DC2Type:user_state) USER_APPROVED,USER_BANNED,USER_ENABLED,USER_GHOST,USER_KICKED,USER_LOCKED,USER_NEWCOMER,USER_VERIFIED\'');
        $this->addSql('ALTER TABLE userConnection CHANGE state state ENUM(\'CONNECTION_CLOSED\', \'CONNECTION_FAILED\', \'CONNECTION_REQUESTED\', \'CONNECTION_SUCCEEDED\') NOT NULL COMMENT \'(DC2Type:connection_state) CONNECTION_CLOSED,CONNECTION_FAILED,CONNECTION_REQUESTED,CONNECTION_SUCCEEDED\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE extensionAbstract CHANGE action action ENUM(\'ACTION_DELETE\', \'ACTION_INSERT\', \'ACTION_UPDATE\') NOT NULL COMMENT \'(DC2Type:entity_action)\'');
        $this->addSql('ALTER TABLE layoutAttributeAdapterScore CHANGE operation operation ENUM(\'EQUAL\', \'GREATER\', \'GREATER_EQUAL\', \'LESS\', \'LESS_EQUAL\', \'NOTEQUAL\') NOT NULL COMMENT \'(DC2Type:operation)\'');
        $this->addSql('ALTER TABLE layoutImage CHANGE quadrant quadrant ENUM(\'EAST\', \'NORTH\', \'NORTHEAST\', \'NORTHWEST\', \'ORIGIN\', \'SOUTH\', \'SOUTHEAST\', \'SOUTHWEST\', \'WEST\') NOT NULL COMMENT \'(DC2Type:quadrant8)\'');
        $this->addSql('ALTER TABLE thread CHANGE state state ENUM(\'STATE_DELETE\', \'STATE_DRAFT\', \'STATE_FUTURE\', \'STATE_PUBLISH\', \'STATE_PUBLISH_ARCHIVE\', \'STATE_PUBLISH_PASSWORD\', \'STATE_PUBLISH_SECRET\') NOT NULL COMMENT \'(DC2Type:thread_state)\', CHANGE workflow workflow ENUM(\'WORKFLOW_APPROVED\', \'WORKFLOW_PENDING\', \'WORKFLOW_REJECTED\', \'WORKFLOW_REVIEWING\', \'WORKFLOW_SUBMITTED\', \'WORKFLOW_SUSPENDED\') NOT NULL COMMENT \'(DC2Type:workflow_state)\'');
        $this->addSql('ALTER TABLE user CHANGE roles roles SET(\'ROLE_ADMIN\', \'ROLE_EDITOR\', \'ROLE_SUPERADMIN\', \'ROLE_USER\') NOT NULL COMMENT \'(DC2Type:user_role)\', CHANGE states states SET(\'USER_APPROVED\', \'USER_BANNED\', \'USER_ENABLED\', \'USER_GHOST\', \'USER_KICKED\', \'USER_LOCKED\', \'USER_NEWCOMER\', \'USER_VERIFIED\') NOT NULL COMMENT \'(DC2Type:user_state)\'');
        $this->addSql('ALTER TABLE userConnection CHANGE state state ENUM(\'CONNECTION_CLOSED\', \'CONNECTION_FAILED\', \'CONNECTION_REQUESTED\', \'CONNECTION_SUCCEEDED\') NOT NULL COMMENT \'(DC2Type:connection_state)\'');
    }
}
