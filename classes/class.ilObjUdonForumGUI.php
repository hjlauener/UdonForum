<?php

declare(strict_types=1);

use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GUI class of the forum repository object.
 *
 * @ilCtrl_isCalledBy ilObjUdonForumGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls      ilObjUdonForumGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 */
class ilObjUdonForumGUI extends ilObjectPluginGUI
{
    public const CMD_SHOW_CONTENT = 'showContent';
    public const CMD_EDIT_PROPERTIES = 'editProperties';
    public const CMD_SAVE_PROPERTIES = 'saveProperties';

    public const TAB_CONTENT = 'content';
    public const TAB_PROPERTIES = 'properties';

    private UIFactory $udf_ui_factory;
    private UIRenderer $udf_ui_renderer;
    private ServerRequestInterface $udf_request;

    protected function afterConstructor(): void
    {
        global $DIC;

        $this->udf_ui_factory = $DIC->ui()->factory();
        $this->udf_ui_renderer = $DIC->ui()->renderer();
        $this->udf_request = $DIC->http()->request();
    }

    final public function getType(): string
    {
        return ilUdonForumPlugin::ID;
    }

    public function getAfterCreationCmd(): string
    {
        return self::CMD_SHOW_CONTENT;
    }

    public function getStandardCmd(): string
    {
        return self::CMD_SHOW_CONTENT;
    }

    public function performCommand(string $cmd): void
    {
        switch ($cmd) {
            case self::CMD_EDIT_PROPERTIES:
            case self::CMD_SAVE_PROPERTIES:
                $this->checkPermission('write');
                $this->$cmd();
                break;

            case self::CMD_SHOW_CONTENT:
            default:
                $this->checkPermission('read');
                $this->showContent();
                break;
        }
    }

    protected function setTabs(): void
    {
        $ref_id = $this->object->getRefId();

        if ($this->access->checkAccess('read', '', $ref_id)) {
            $this->tabs->addTab(
                self::TAB_CONTENT,
                $this->txt('content'),
                $this->ctrl->getLinkTarget($this, self::CMD_SHOW_CONTENT)
            );
        }

        $this->addInfoTab();

        if ($this->access->checkAccess('write', '', $ref_id)) {
            $this->tabs->addTab(
                self::TAB_PROPERTIES,
                $this->txt('properties'),
                $this->ctrl->getLinkTarget($this, self::CMD_EDIT_PROPERTIES)
            );
        }

        $this->addPermissionTab();
    }

    /**
     * Start page of the forum. Threads and posts will be listed here.
     */
    protected function showContent(): void
    {
        $this->tabs->activateTab(self::TAB_CONTENT);

        $panel = $this->udf_ui_factory->panel()->standard(
            $this->txt('threads'),
            $this->udf_ui_factory->messageBox()->info($this->txt('no_threads_yet'))
        );

        $this->tpl->setContent($this->udf_ui_renderer->render($panel));
    }

    protected function editProperties(): void
    {
        $this->tabs->activateTab(self::TAB_PROPERTIES);
        $this->tpl->setContent($this->udf_ui_renderer->render($this->buildPropertiesForm()));
    }

    protected function saveProperties(): void
    {
        $this->tabs->activateTab(self::TAB_PROPERTIES);

        $form = $this->buildPropertiesForm()->withRequest($this->udf_request);
        $data = $form->getData();

        if ($data === null) {
            $this->tpl->setContent($this->udf_ui_renderer->render($form));
            return;
        }

        $this->object->setTitle($data['title']);
        $this->object->setDescription($data['description']);
        $this->object->update();

        $this->tpl->setOnScreenMessage('success', $this->lng->txt('msg_obj_modified'), true);
        $this->ctrl->redirect($this, self::CMD_EDIT_PROPERTIES);
    }

    private function buildPropertiesForm(): StandardForm
    {
        $field = $this->udf_ui_factory->input()->field();

        $inputs = [
            'title' => $field->text($this->lng->txt('title'))
                ->withRequired(true)
                ->withValue($this->object->getTitle()),
            'description' => $field->textarea($this->lng->txt('description'))
                ->withValue($this->object->getLongDescription()),
        ];

        return $this->udf_ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormAction($this, self::CMD_SAVE_PROPERTIES),
            $inputs
        );
    }
}
