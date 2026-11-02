<?php

declare(strict_types=1);

/**
 * Repository object plugin "Udon Forum" (type xudf) for ILIAS 11.
 */
class ilUdonForumPlugin extends ilRepositoryObjectPlugin
{
    public const ID = 'xudf';
    public const NAME = 'UdonForum';

    public function getPluginName(): string
    {
        return self::NAME;
    }

    protected function uninstallCustom(): void
    {
        global $DIC;
        $db = $DIC->database();

        foreach (ilUdfRepository::TABLES as $table) {
            if ($db->tableExists($table)) {
                $db->dropTable($table, false);
            }
            if ($db->sequenceExists($table)) {
                $db->dropSequence($table);
            }
        }
    }

    /**
     * An Udon Forum can only be created directly in courses and groups.
     *
     * @return string[]
     */
    public function getParentTypes(): array
    {
        return ['crs', 'grp'];
    }

    public function allowCopy(): bool
    {
        return false;
    }
}
