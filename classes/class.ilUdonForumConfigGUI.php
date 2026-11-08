<?php

declare(strict_types=1);

/**
 * Configuration of the plugin (Administration › Erweiterungen › Plugins ›
 * UdonForum › Konfigurieren): category presets that are offered when a new
 * forum is created.
 *
 * @ilCtrl_IsCalledBy ilUdonForumConfigGUI: ilObjComponentSettingsGUI
 */
class ilUdonForumConfigGUI extends ilPluginConfigGUI
{
    public function performCommand(string $cmd): void
    {
        switch ($cmd) {
            case 'save':
                $this->save();
                break;
            case 'configure':
            default:
                $this->configure();
                break;
        }
    }

    private function configure(?ilPropertyFormGUI $form = null): void
    {
        global $DIC;
        if ($form === null) {
            $form = $this->initForm();
            $form->setValuesByArray(['presets' => ilUdfCategoryPresets::loadText()]);
        }
        $DIC->ui()->mainTemplate()->setContent($form->getHTML());
    }

    private function save(): void
    {
        global $DIC;
        $form = $this->initForm();
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->configure($form);
            return;
        }
        ilUdfCategoryPresets::saveText((string) $form->getInput('presets'));
        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $this->txt('config_saved'), true);
        $DIC->ctrl()->redirect($this, 'configure');
    }

    private function initForm(): ilPropertyFormGUI
    {
        global $DIC;
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->txt('config_title'));
        $form->setFormAction($DIC->ctrl()->getFormAction($this, 'save'));

        $presets = new ilTextAreaInputGUI($this->txt('config_presets'), 'presets');
        $presets->setInfo($this->txt('config_presets_info'));
        $presets->setRows(10);
        $form->addItem($presets);

        $form->addCommandButton('save', $DIC->language()->txt('save'));
        return $form;
    }

    private function txt(string $key): string
    {
        return $this->getPluginObject()?->txt($key) ?? $key;
    }
}
