<?php

namespace OrangeHRM\Installer\Migration\V5_5_1;

use OrangeHRM\Installer\Util\V1\AbstractMigration;

class Migration extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        // Leave table modification for google event id
        $this->getConnection()
            ->executeStatement("ALTER TABLE orangehrm.ohrm_leave ADD google_event_id varchar(255) DEFAULT NULL NULL;");
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): string
    {
        return '5.5.1';
    }
}
