<?php

declare(strict_types=1);

/**
 * List GUI of the Udon Forum repository object (presentation in the
 * repository container lists).
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
                'cmd' => 'showContent',
                'default' => true,
            ],
            [
                'permission' => 'write',
                'cmd' => 'editProperties',
                'txt' => $this->txt('edit'),
                'default' => false,
            ],
        ];
    }

    public function getProperties(): array
    {
        $props = [];

        if (!ilObjUdonForumAccess::checkOnline($this->obj_id)) {
            $props[] = [
                'alert' => true,
                'property' => $this->txt('status'),
                'value' => $this->txt('offline'),
            ];
        }

        return $props;
    }
}
