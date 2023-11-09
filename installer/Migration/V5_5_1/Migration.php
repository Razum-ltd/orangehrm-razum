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
        $this->execSqlFile('google-events.sql');
        $this->execSqlFile('attendance-regulation.sql');
    }

    private function execSqlFile(string $fileName): void
    {
        $script = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $fileName);
        $dbScriptStatements = preg_split('/;\s*$/m', $script);

        if ($dbScriptStatements) {
            foreach ($dbScriptStatements as $statement) {
                if (empty(trim($statement))) {
                    continue;
                }
                try {
                    $this->getConnection()->executeStatement($statement);
                } catch (\Doctrine\DBAL\Exception\NonUniqueFieldNameException $e) {
                    // Log the error message and continue with the next statement
                    error_log($e->getMessage());
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): string
    {
        return '5.5.1';
    }
}
