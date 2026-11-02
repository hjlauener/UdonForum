<?php

declare(strict_types=1);

/**
 * List GUI of the forum repository object (presentation in the repository).
 */
class ilObjUdonForumListGUI extends ilObjectPluginListGUI
{
    public function initType(): void
    {
        $this->setType(ilUdonForumPlugin::ID);
    }

    public function getGuiClass(): string
    {
        return ilObjUdonForumGUI::class;
    }

    public function initCommands(): array
    {
        return [
            [
                'permission' => 'read',
                'cmd' => ilObjUdonForumGUI::CMD_SHOW_CONTENT,
                'default' => true,
            ],
            [
                'permission' => 'write',
                'cmd' => ilObjUdonForumGUI::CMD_EDIT_PROPERTIES,
                'txt' => $this->txt('edit'),
                'default' => false,
            ],
        ];
    }
}
