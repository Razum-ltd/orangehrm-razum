<?php

namespace OrangeHRM\Installer\Migration\V5_5_1;

use OrangeHRM\Installer\Util\V1\AbstractMigration;

class Migration extends AbstractMigration
{
    protected ?LangStringHelper $langStringHelper = null;
    protected ?TranslationHelper $translationHelper = null;

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->execSqlFile('google-events.sql');
        $this->execSqlFile('attendance-regulation.sql');

        // lang strings
        $this->getLangStringHelper()->insertOrUpdateLangStrings("attendance");
        // translations
        $langCodes = ["en_US", "sl_SI"];
        foreach ($langCodes as $langCode) {
            $this->getTranslationHelper()->addTranslations($langCode);
        }
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

    /**
     * @return LangStringHelper
     */
    public function getLangStringHelper(): LangStringHelper
    {
        if (is_null($this->langStringHelper)) {
            $this->langStringHelper = new LangStringHelper($this->getConnection());
        }
        return $this->langStringHelper;
    }

    /**
     * @return TranslationHelper
     */
    public function getTranslationHelper(): TranslationHelper
    {
        if (is_null($this->translationHelper)) {
            $this->translationHelper = new TranslationHelper($this->getConnection());
        }
        return $this->translationHelper;
    }
}
