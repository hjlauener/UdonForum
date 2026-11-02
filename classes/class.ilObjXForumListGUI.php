<?php

declare(strict_types=1);

/**
 * List GUI of the forum repository object (presentation in the repository).
 */
class ilObjXForumListGUI extends ilObjectPluginListGUI
{
    public function initType(): void
    {
        $this->setType(ilXForumPlugin::ID);
    }

    public function getGuiClass(): string
    {
        return ilObjXForumGUI::class;
    }

    public function initCommands(): array
    {
        return [
            [
                'permission' => 'read',
                'cmd' => ilObjXForumGUI::CMD_SHOW_CONTENT,
                'default' => true,
            ],
            [
                'permission' => 'write',
                'cmd' => ilObjXForumGUI::CMD_EDIT_PROPERTIES,
                'txt' => $this->txt('edit'),
                'default' => false,
            ],
        ];
    }
}
