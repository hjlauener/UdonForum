<?php

declare(strict_types=1);

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Repository object plugin "Udon Forum" (type xfrm) for ILIAS 11.
 */
class ilUdonForumPlugin extends ilRepositoryObjectPlugin
{
    public const ID = 'xfrm';
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
