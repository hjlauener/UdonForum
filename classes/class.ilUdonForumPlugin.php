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
        // Tables created in sql/dbupdate.php are removed here once they exist.
    }

    public function allowCopy(): bool
    {
        return false;
    }
}
