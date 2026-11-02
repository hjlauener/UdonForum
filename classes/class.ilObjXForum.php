<?php

declare(strict_types=1);

/**
 * Application class of the forum repository object.
 *
 * For now the object only consists of the standard ILIAS object data
 * (title, description). Forum specific data (threads, posts) will follow.
 */
class ilObjXForum extends ilObjectPlugin
{
    protected function initType(): void
    {
        $this->setType(ilXForumPlugin::ID);
    }

    protected function doCreate(bool $clone_mode = false): void
    {
    }

    protected function doRead(): void
    {
    }

    protected function doUpdate(): void
    {
    }

    protected function doDelete(): void
    {
    }
}
