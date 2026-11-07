<?php

declare(strict_types=1);

use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;
use ILIAS\UI\Component\Modal\Interruptive;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GUI class of the Udon Forum repository object.
 *
 * Reddit-like forum: a feed of posts (cards) with votes, sorting and
 * categories, and a detail view per post with replies (one level).
 *
 * Permissions:
 * - read:  read the forum, write posts and replies, vote, edit/delete own ones
 * - write: settings, visibility, categories, delete posts/replies of others
 * - udf_statistics ("Statistik einsehen"): tab "Statistik"
 *
 * @ilCtrl_isCalledBy ilObjUdonForumGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls      ilObjUdonForumGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 */
class ilObjUdonForumGUI extends ilObjectPluginGUI
{
    private const TAB_CONTENT = 'content';
    private const TAB_PROPERTIES = 'properties';
    private const TAB_CATEGORIES = 'categories';
    private const TAB_STATISTICS = 'statistics';
    private const SUBTAB_STAT_STANDARD = 'stat_standard';
    private const SUBTAB_STAT_NETWORK = 'stat_network';

    private const PARAM_POST = 'post_id';
    private const PARAM_REPLY = 'reply_id';
    private const PARAM_PAGE = 'page';
    private const PARAM_CATEGORY = 'cat_id';
    /** feed filter: category */
    private const PARAM_FILTER_CAT = 'fcat';
    /** feed sorting: see ilUdfRepository::SORT_* */
    private const PARAM_SORT = 'fsort';
    /** period for "best rated" */
    private const PARAM_PERIOD = 'fper';
    private const PARAM_VOTE_ID = 'vid';
    private const PARAM_RETURN = 'ret';
    private const POST_FIELD_DIRECTION = 'udf_dir';
    private const POST_FIELD_ASYNC = 'udf_async';
    private const CREATE_FIELD_ONLINE = 'udf_online';
    private const CREATE_FIELD_MARKDOWN = 'udf_markdown';

    private const PERIOD_WEEK = 'week';
    private const PERIOD_MONTH = 'month';
    private const PERIOD_ALL = 'all';

    private const POSTS_PER_PAGE = 25;
    private const PREVIEW_LENGTH = 300;
    private const CATEGORY_COLORS = ['#e3ecf7', '#fdecc8', '#e6f4ea', '#f3e5f5', '#fde2e1', '#e0f2f1'];

    /** Commands that need write permission */
    private const WRITE_COMMANDS = [
        'editProperties',
        'updateProperties',
        'showCategories',
        'createCategory',
        'saveCategory',
        'editCategory',
        'updateCategory',
        'deleteCategory',
        'moveCategoryUp',
        'moveCategoryDown',
    ];

    /** Commands that need the permission "Statistik einsehen" */
    private const STATISTICS_COMMANDS = [
        'showStatistics',
        'showNetwork',
    ];

    /** Commands that need read permission (further checks inside) */
    private const READ_COMMANDS = [
        'showContent',
        'showPost',
        'createPost',
        'savePost',
        'editPost',
        'updatePost',
        'deletePost',
        'cancelPost',
        'saveReply',
        'editReply',
        'updateReply',
        'deleteReply',
        'renderMarkdownPreview',
        'vote',
        'markAllRead',
    ];

    private ilUdfRepository $udf_repo;
    private Refinery $udf_refinery;
    private ArrayBasedRequestWrapper $udf_query;
    private ArrayBasedRequestWrapper $udf_post;
    private ilObjUser $udf_user;
    private ServerRequestInterface $udf_request;
    private ?ilUdfMarkdownRenderer $udf_markdown = null;

    /** @var array<int, string> user id => escaped name */
    private array $udf_name_cache = [];
    /** @var array<int, bool> user id => has write permission */
    private array $udf_lecturer_cache = [];
    /** @var ilUdfCategory[]|null */
    private ?array $udf_categories = null;

    protected function afterConstructor(): void
    {
        global $DIC;

        $this->udf_repo = new ilUdfRepository($DIC->database());
        $this->udf_refinery = $DIC->refinery();
        $this->udf_query = $DIC->http()->wrapper()->query();
        $this->udf_post = $DIC->http()->wrapper()->post();
        $this->udf_user = $DIC->user();
        $this->udf_request = $DIC->http()->request();
    }

    final public function getType(): string
    {
        return ilUdonForumPlugin::ID;
    }

    public function getAfterCreationCmd(): string
    {
        // "Online" and "Markdown-Editor" are already set in the creation form
        return 'showContent';
    }

    /**
     * Creation form: title and description (as in ILIAS) plus the settings
     * "Online" (off) and "Markdown-Editor" (on).
     */
    protected function initCreateForm(string $new_type): StandardForm|ilPropertyFormGUI|array
    {
        $field = $this->ui_factory->input()->field();

        $fields = [];
        $fields['title_and_description'] = (new ilObject())->getObjectProperties()
            ->getPropertyTitleAndDescription()
            ->toForm($this->lng, $field, $this->refinery);

        $didactic_templates = $this->didacticTemplatesToForm();
        if ($didactic_templates !== null) {
            $fields['didactic_templates'] = $didactic_templates;
        }

        $fields[self::CREATE_FIELD_ONLINE] = $field->checkbox($this->txt('online'), $this->txt('online_info'))
            ->withValue(false);
        $fields[self::CREATE_FIELD_MARKDOWN] = $field->checkbox($this->txt('use_markdown'), $this->txt('use_markdown_info'))
            ->withValue(true);

        return $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormAction($this, 'save'),
            $fields
        )->withSubmitLabel($this->txt(ilUdonForumPlugin::ID . '_add'));
    }

    /**
     * After creating: store "Online" and "Markdown-Editor" from the creation form.
     */
    protected function afterSave(ilObject $new_object): void
    {
        if ($new_object instanceof ilObjUdonForum) {
            $data = $this->initCreateForm(ilUdonForumPlugin::ID)->withRequest($this->udf_request)->getData();
            if (is_array($data)) {
                $new_object->setOnline((bool) ($data[self::CREATE_FIELD_ONLINE] ?? false));
                $new_object->setUseMarkdown((bool) ($data[self::CREATE_FIELD_MARKDOWN] ?? true));
                $new_object->update();
            }
        }
        parent::afterSave($new_object);
    }

    public function getStandardCmd(): string
    {
        return 'showContent';
    }

    public function performCommand(string $cmd): void
    {
        if (in_array($cmd, self::WRITE_COMMANDS, true)) {
            $this->checkPermission('write');
            $this->ensureDefaultCategories();
            $this->$cmd();
            return;
        }

        if (in_array($cmd, self::STATISTICS_COMMANDS, true)) {
            $this->checkPermission(ilUdonForumPlugin::OP_STATISTICS);
            $this->$cmd();
            return;
        }

        $this->checkPermission('read');
        $this->ensureDefaultCategories();
        if (in_array($cmd, self::READ_COMMANDS, true)) {
            $this->$cmd();
            return;
        }
        $this->showContent();
    }

    protected function setTabs(): void
    {
        $ref_id = $this->object->getRefId();

        if ($this->access->checkAccess('read', '', $ref_id)) {
            $this->tabs->addTab(
                self::TAB_CONTENT,
                $this->txt('content'),
                $this->ctrl->getLinkTarget($this, 'showContent')
            );
        }

        $this->addInfoTab();

        if ($this->access->checkAccess('write', '', $ref_id)) {
            $this->tabs->addTab(
                self::TAB_PROPERTIES,
                $this->txt('properties'),
                $this->ctrl->getLinkTarget($this, 'editProperties')
            );
            $this->tabs->addTab(
                self::TAB_CATEGORIES,
                $this->txt('categories'),
                $this->ctrl->getLinkTarget($this, 'showCategories')
            );
        }

        if ($this->canViewStatistics()) {
            $this->tabs->addTab(
                self::TAB_STATISTICS,
                $this->txt('statistics'),
                $this->ctrl->getLinkTarget($this, 'showStatistics')
            );
        }

        $this->addPermissionTab();
    }

    // ------------------------------------------------------------------
    // Settings of the repository object
    // ------------------------------------------------------------------

    protected function editProperties(?ilPropertyFormGUI $form = null): void
    {
        $this->tabs->activateTab(self::TAB_PROPERTIES);

        if ($form === null) {
            $form = $this->initPropertiesForm();
            $form->setValuesByArray([
                'title' => $this->object->getTitle(),
                'desc' => $this->object->getLongDescription(),
                'online' => $this->getForum()->isOnline(),
                'use_markdown' => $this->getForum()->usesMarkdown(),
            ]);
        }

        $this->tpl->setContent($form->getHTML());
    }

    protected function updateProperties(): void
    {
        $form = $this->initPropertiesForm();
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->editProperties($form);
            return;
        }

        $forum = $this->getForum();
        $forum->setTitle((string) $form->getInput('title'));
        $forum->setDescription((string) $form->getInput('desc'));
        $forum->setOnline((bool) $form->getInput('online'));
        $forum->setUseMarkdown((bool) $form->getInput('use_markdown'));
        $forum->update();

        $this->tpl->setOnScreenMessage('success', $this->lng->txt('msg_obj_modified'), true);
        $this->ctrl->redirect($this, 'editProperties');
    }

    private function initPropertiesForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->txt('properties'));
        $form->setFormAction($this->ctrl->getFormAction($this, 'updateProperties'));

        $title = new ilTextInputGUI($this->lng->txt('title'), 'title');
        $title->setRequired(true);
        $title->setMaxLength(ilObject::TITLE_LENGTH);
        $form->addItem($title);

        $desc = new ilTextAreaInputGUI($this->lng->txt('description'), 'desc');
        $desc->setRows(2);
        $form->addItem($desc);

        $online = new ilCheckboxInputGUI($this->txt('online'), 'online');
        $online->setInfo($this->txt('online_info'));
        $form->addItem($online);

        $markdown = new ilCheckboxInputGUI($this->txt('use_markdown'), 'use_markdown');
        $markdown->setInfo($this->txt('use_markdown_info'));
        $form->addItem($markdown);

        $form->addCommandButton('updateProperties', $this->lng->txt('save'));

        return $form;
    }

    // ------------------------------------------------------------------
    // Feed
    // ------------------------------------------------------------------

    protected function showContent(): void
    {
        $this->tabs->activateTab(self::TAB_CONTENT);
        $this->showOfflineInfo();

        $state = $this->getFeedState();

        if ($this->canParticipate()) {
            // within a category, the new post gets this category preselected
            $this->toolbar->addComponent($this->ui_factory->button()->primary(
                $this->txt('new_post'),
                $this->linkWithParams(
                    'createPost',
                    $state[self::PARAM_FILTER_CAT] > 0 ? [self::PARAM_FILTER_CAT => $state[self::PARAM_FILTER_CAT]] : []
                )
            ));
            $this->toolbar->addComponent($this->ui_factory->button()->standard(
                $this->txt('mark_all_read'),
                $this->ctrl->getLinkTarget($this, 'markAllRead')
            ));
        }

        if ($this->isLoggedIn()) {
            // the first visit defines from when on posts count as new
            $this->getReadSince();
        }

        $since = 0;
        if ($state[self::PARAM_SORT] === ilUdfRepository::SORT_TOP && $state[self::PARAM_PERIOD] !== self::PERIOD_ALL) {
            $since = time() - ($state[self::PARAM_PERIOD] === self::PERIOD_WEEK ? 7 : 30) * 86400;
        }

        $posts = $this->udf_repo->getPostsForObject(
            $this->object->getId(),
            self::POSTS_PER_PAGE + 1,
            $state[self::PARAM_PAGE] * self::POSTS_PER_PAGE,
            $state[self::PARAM_FILTER_CAT],
            $state[self::PARAM_SORT],
            $since
        );
        $has_more = count($posts) > self::POSTS_PER_PAGE;
        $posts = array_slice($posts, 0, self::POSTS_PER_PAGE);

        $html = $this->getStyles() . '<div class="udf">';
        $html .= $this->renderFeedControls($state);

        if ($posts === []) {
            $filtered = $state[self::PARAM_FILTER_CAT] > 0 || $since > 0;
            $html .= '<p class="udf-empty">' . $this->esc($this->txt($filtered ? 'no_posts_filtered' : 'no_posts')) . '</p></div>';
            $this->tpl->setContent($html);
            return;
        }

        $post_ids = array_map(static fn(ilUdfPost $p): int => $p->getId(), $posts);
        $my_votes = $this->udf_repo->getUserVotes(ilUdfRepository::ITEM_POST, $post_ids, $this->udf_user->getId());
        $unread = $this->getUnreadInfo($posts);

        /** @var Interruptive[] $modals */
        $modals = [];
        foreach ($posts as $post) {
            $html .= $this->renderPostCard(
                $post,
                $my_votes[$post->getId()] ?? 0,
                isset($unread[$post->getId()]),
                $state,
                $modals
            );
        }
        $html .= $this->renderPager($state, $has_more);
        $html .= '</div>';

        $this->addVoteScript();
        $this->tpl->setContent($html . $this->ui_renderer->render($modals));
    }

    /**
     * @param array<string, int|string> $state  feed state (for the way back after voting)
     * @param Interruptive[]            $modals collects the delete overlays
     */
    private function renderPostCard(
        ilUdfPost $post,
        int $my_vote,
        bool $is_new,
        array $state,
        array &$modals
    ): string {
        $link = $this->linkToPost('showPost', $post);

        $preview = $post->isMarkdown()
            ? $this->getMarkdownRenderer()->toPlainText($post->getBody())
            : trim((string) preg_replace('/\s+/u', ' ', $post->getBody()));

        if (mb_strlen($preview) > self::PREVIEW_LENGTH) {
            $preview = rtrim(mb_substr($preview, 0, self::PREVIEW_LENGTH)) . ' …';
        }

        return '<article class="udf-card udf-card-voted' . ($is_new ? ' udf-unread' : '') . '" id="udf-p' . $post->getId() . '">'
            . $this->renderVote(
                $post->getId(),
                $post->getScore(),
                $my_vote,
                $this->canVote($post->getUserId()),
                ['ret' => 'feed'] + $this->getFeedParams($state)
            )
            . '<div class="udf-card-main">'
            . $this->renderPostMenu($post, $modals)
            . $this->renderPostLabels($post, $is_new)
            . '<h3 class="udf-title"><a href="' . $this->esc($link) . '">' . $this->esc($post->getTitle()) . '</a></h3>'
            . '<div class="udf-meta">' . $this->renderMeta($post->getUserId(), $post->getCreated(), $post->isEdited()) . '</div>'
            . ($preview !== '' ? '<div class="udf-preview">' . $this->esc($preview) . '</div>' : '')
            . '</div></article>';
    }

    /**
     * Menu of a post card (edit, delete) for the author and lecturers.
     *
     * @param Interruptive[] $modals collects the delete overlays
     */
    private function renderPostMenu(ilUdfPost $post, array &$modals): string
    {
        $items = [];
        if ($this->isAuthor($post->getUserId())) {
            $items[] = $this->ui_factory->button()->shy($this->txt('edit_post'), $this->linkToPost('editPost', $post));
        }
        if ($this->canDeletePost($post)) {
            $modal = $this->buildDeletePostModal($post);
            $modals[] = $modal;
            $items[] = $this->ui_factory->button()->shy($this->txt('delete_post'), '')->withOnClick($modal->getShowSignal());
        }
        if ($items === []) {
            return '';
        }
        return '<div class="udf-card-menu">' . $this->ui_renderer->render(
            $this->ui_factory->dropdown()->standard($items)->withAriaLabel($this->txt('actions'))
        ) . '</div>';
    }

    private function canDeletePost(ilUdfPost $post): bool
    {
        return $this->isAuthor($post->getUserId()) || $this->isModerator();
    }

    private function buildDeletePostModal(ilUdfPost $post): Interruptive
    {
        return $this->buildConfirmModal(
            $this->txt('confirm_delete_post'),
            $this->linkToPost('deletePost', $post, true),
            $post->getId(),
            $post->getTitle(),
            $this->lng->txt('delete')
        );
    }

    /**
     * "New" badge and category badge above the title.
     */
    private function renderPostLabels(ilUdfPost $post, bool $is_new = false): string
    {
        $labels = [];
        if ($is_new) {
            $labels[] = '<span class="udf-new">' . $this->txt('new') . '</span>';
        }
        $cat_title = $this->getCategoryTitle($post->getCatId());
        if ($cat_title !== null) {
            $labels[] = '<span class="udf-cat" style="background:' . $this->getCategoryColor($post->getCatId()) . '">'
                . $this->esc($cat_title) . '</span>';
        }
        return $labels !== [] ? '<div class="udf-labels">' . implode(' ', $labels) . '</div>' : '';
    }

    /**
     * Filter chips (all / categories) on the left, sorting chips
     * (popular / new / best rated + period) on the right.
     *
     * @param array<string, int|string> $state
     */
    private function renderFeedControls(array $state): string
    {
        $filters = [$this->renderChip(
            $this->txt('all_posts'),
            $this->linkToFeed($state, [self::PARAM_FILTER_CAT => 0, self::PARAM_PAGE => 0]),
            $state[self::PARAM_FILTER_CAT] === 0
        )];
        foreach ($this->getCategories() as $cat) {
            $filters[] = $this->renderChip(
                $cat->getTitle(),
                $this->linkToFeed($state, [self::PARAM_FILTER_CAT => $cat->getId(), self::PARAM_PAGE => 0]),
                $state[self::PARAM_FILTER_CAT] === $cat->getId()
            );
        }

        $sorts = [];
        foreach ([
            ilUdfRepository::SORT_POPULAR => 'sort_popular',
            ilUdfRepository::SORT_NEW => 'sort_new',
            ilUdfRepository::SORT_TOP => 'sort_top',
        ] as $sort => $label) {
            $sorts[] = $this->renderChip(
                $this->txt($label),
                $this->linkToFeed($state, [self::PARAM_SORT => $sort, self::PARAM_PAGE => 0]),
                $state[self::PARAM_SORT] === $sort
            );
        }
        $periods = [];
        if ($state[self::PARAM_SORT] === ilUdfRepository::SORT_TOP) {
            foreach ([self::PERIOD_WEEK => 'period_week', self::PERIOD_MONTH => 'period_month', self::PERIOD_ALL => 'period_all'] as $period => $label) {
                $periods[] = $this->renderChip(
                    $this->txt($label),
                    $this->linkToFeed($state, [self::PARAM_PERIOD => $period, self::PARAM_PAGE => 0]),
                    $state[self::PARAM_PERIOD] === $period,
                    true
                );
            }
        }

        return '<div class="udf-controls">'
            . '<nav class="udf-filters" aria-label="' . $this->esc($this->txt('filter')) . '">' . implode('', $filters) . '</nav>'
            . '<nav class="udf-filters udf-sorting" aria-label="' . $this->esc($this->txt('sorting')) . '">'
            . implode('', $sorts)
            . ($periods !== [] ? '<span class="udf-sorting-periods">' . implode('', $periods) . '</span>' : '')
            . '</nav></div>';
    }

    private function renderChip(string $label, string $url, bool $active, bool $small = false): string
    {
        return '<a class="udf-chip' . ($active ? ' udf-chip-active' : '') . ($small ? ' udf-chip-small' : '')
            . '" href="' . $this->esc($url) . '"' . ($active ? ' aria-current="true"' : '') . '>' . $this->esc($label) . '</a>';
    }

    /**
     * @param array<string, int|string> $state
     */
    private function renderPager(array $state, bool $has_more): string
    {
        $page = (int) $state[self::PARAM_PAGE];
        if ($page === 0 && !$has_more) {
            return '';
        }

        $buttons = [];
        if ($page > 0) {
            $buttons[] = $this->ui_factory->button()->standard(
                $this->txt('previous_page'),
                $this->linkToFeed($state, [self::PARAM_PAGE => $page - 1])
            );
        }
        if ($has_more) {
            $buttons[] = $this->ui_factory->button()->standard(
                $this->txt('next_page'),
                $this->linkToFeed($state, [self::PARAM_PAGE => $page + 1])
            );
        }

        return '<div class="udf-pager">' . $this->ui_renderer->render($buttons) . '</div>';
    }

    /**
     * Current feed settings (sorting, category filter, page).
     *
     * Category and sorting are remembered per forum in the ILIAS session: when
     * the user comes back to the forum (tab, back link, ...), the same
     * category and sorting are shown again – until logout.
     *
     * @return array<string, int|string>
     */
    private function getFeedState(): array
    {
        $saved = ilSession::get($this->getFeedSessionKey());
        if (!is_array($saved)) {
            $saved = [];
        }

        $sort = $this->udf_query->has(self::PARAM_SORT)
            ? $this->getRequestedString($this->udf_query, self::PARAM_SORT)
            : (string) ($saved[self::PARAM_SORT] ?? '');
        if (!in_array($sort, [ilUdfRepository::SORT_POPULAR, ilUdfRepository::SORT_NEW, ilUdfRepository::SORT_TOP], true)) {
            $sort = ilUdfRepository::SORT_POPULAR;
        }

        $period = $this->udf_query->has(self::PARAM_PERIOD)
            ? $this->getRequestedString($this->udf_query, self::PARAM_PERIOD)
            : (string) ($saved[self::PARAM_PERIOD] ?? '');
        if (!in_array($period, [self::PERIOD_WEEK, self::PERIOD_MONTH, self::PERIOD_ALL], true)) {
            $period = self::PERIOD_WEEK;
        }

        $cat = $this->udf_query->has(self::PARAM_FILTER_CAT)
            ? $this->getRequestedInt($this->udf_query, self::PARAM_FILTER_CAT)
            : (int) ($saved[self::PARAM_FILTER_CAT] ?? 0);
        if ($cat > 0 && $this->getCategoryTitle($cat) === null) {
            $cat = 0;
        }

        ilSession::set($this->getFeedSessionKey(), [
            self::PARAM_SORT => $sort,
            self::PARAM_PERIOD => $period,
            self::PARAM_FILTER_CAT => $cat,
        ]);

        return [
            self::PARAM_SORT => $sort,
            self::PARAM_PERIOD => $period,
            self::PARAM_FILTER_CAT => $cat,
            self::PARAM_PAGE => $this->getRequestedInt($this->udf_query, self::PARAM_PAGE),
        ];
    }

    private function getFeedSessionKey(): string
    {
        return 'udf_feed_' . $this->object->getId();
    }

    /**
     * Parameters of a feed link. Category and sorting are always part of the
     * link, so that switching back to "all" or "popular" is remembered too.
     *
     * @param array<string, int|string> $state
     * @return array<string, int|string>
     */
    private function getFeedParams(array $state): array
    {
        $params = [
            self::PARAM_FILTER_CAT => (int) $state[self::PARAM_FILTER_CAT],
            self::PARAM_SORT => (string) $state[self::PARAM_SORT],
        ];
        if ($state[self::PARAM_SORT] === ilUdfRepository::SORT_TOP) {
            $params[self::PARAM_PERIOD] = (string) $state[self::PARAM_PERIOD];
        }
        if ((int) $state[self::PARAM_PAGE] > 0) {
            $params[self::PARAM_PAGE] = (int) $state[self::PARAM_PAGE];
        }
        return $params;
    }

    /**
     * @param array<string, int|string> $state
     * @param array<string, int|string> $changes
     */
    private function linkToFeed(array $state, array $changes = []): string
    {
        return $this->linkWithParams('showContent', $this->getFeedParams(array_merge($state, $changes)));
    }

    /**
     * @param array<string, int|string> $params
     */
    private function linkWithParams(string $cmd, array $params, bool $form_action = false): string
    {
        foreach ($params as $key => $value) {
            $this->ctrl->setParameter($this, $key, $value);
        }
        $link = $form_action ? $this->ctrl->getFormAction($this, $cmd) : $this->ctrl->getLinkTarget($this, $cmd);
        foreach (array_keys($params) as $key) {
            $this->ctrl->clearParameterByClass(self::class, $key);
        }
        return $link;
    }

    // ------------------------------------------------------------------
    // Read status
    // ------------------------------------------------------------------

    /**
     * Time from which on posts count as new for the current
     * user. On the first visit everything existing counts as read.
     */
    private function getReadSince(): int
    {
        $user_id = $this->udf_user->getId();
        $since = $this->udf_repo->getForumReadTime($user_id, $this->object->getId());
        if ($since === null) {
            $since = time();
            if ($this->isLoggedIn()) {
                $this->udf_repo->setReadTime($user_id, $this->object->getId(), 0, $since);
            }
        }
        return $since;
    }

    /**
     * Posts of others that were created after the read time and not opened yet.
     *
     * @param ilUdfPost[] $posts
     * @return array<int, true> post id => true
     */
    private function getUnreadInfo(array $posts): array
    {
        if (!$this->isLoggedIn() || $posts === []) {
            return [];
        }
        $user_id = $this->udf_user->getId();
        $since = $this->getReadSince();
        $read = $this->udf_repo->getReadTimes(
            $user_id,
            $this->object->getId(),
            array_map(static fn(ilUdfPost $p): int => $p->getId(), $posts)
        );

        $new = [];
        foreach ($posts as $post) {
            if (!isset($read[$post->getId()]) && $post->getCreated() > $since && $post->getUserId() !== $user_id) {
                $new[$post->getId()] = true;
            }
        }
        return $new;
    }

    protected function markAllRead(): void
    {
        if ($this->isLoggedIn()) {
            $this->udf_repo->setReadTime($this->udf_user->getId(), $this->object->getId(), 0, time());
            $this->tpl->setOnScreenMessage('success', $this->txt('marked_all_read'), true);
        }
        $this->ctrl->redirect($this, 'showContent');
    }

    // ------------------------------------------------------------------
    // Votes
    // ------------------------------------------------------------------

    /**
     * ▲ score ▼ – a small form, so that voting also works without JavaScript.
     * With JavaScript the vote is sent in the background (see addVoteScript()).
     *
     * @param array<string, int|string> $return_params where to go back without JavaScript
     */
    private function renderVote(
        int $item_id,
        int $score,
        int $my_vote,
        bool $can_vote,
        array $return_params
    ): string {
        $classes = 'udf-vote'
            . ($my_vote > 0 ? ' udf-up-active' : '') . ($my_vote < 0 ? ' udf-down-active' : '');
        $score_html = '<span class="udf-score" title="' . $this->esc($this->txt('points')) . '">' . $score . '</span>';

        if (!$can_vote) {
            return '<div class="' . $classes . ' udf-vote-disabled">'
                . '<span class="udf-arrow" aria-hidden="true">▲</span>' . $score_html
                . '<span class="udf-arrow" aria-hidden="true">▼</span></div>';
        }

        $action = $this->linkWithParams(
            'vote',
            [self::PARAM_VOTE_ID => $item_id] + $return_params,
            true
        );

        return '<form class="' . $classes . '" method="post" action="' . $this->esc($action) . '">'
            . '<button type="submit" class="udf-arrow udf-up" name="' . self::POST_FIELD_DIRECTION . '" value="1"'
            . ' title="' . $this->esc($this->txt('vote_up')) . '" aria-label="' . $this->esc($this->txt('vote_up')) . '">▲</button>'
            . $score_html
            . '<button type="submit" class="udf-arrow udf-down" name="' . self::POST_FIELD_DIRECTION . '" value="-1"'
            . ' title="' . $this->esc($this->txt('vote_down')) . '" aria-label="' . $this->esc($this->txt('vote_down')) . '">▼</button>'
            . '</form>';
    }

    /**
     * Voting again in the same direction removes the vote (like Reddit).
     */
    protected function vote(): void
    {
        $async = $this->udf_post->has(self::POST_FIELD_ASYNC);
        $type = ilUdfRepository::ITEM_POST;
        $id = $this->getRequestedInt($this->udf_query, self::PARAM_VOTE_ID);
        $direction = $this->getRequestedSignedInt($this->udf_post, self::POST_FIELD_DIRECTION);

        // the post must belong to this forum
        $post = $id > 0 ? $this->udf_repo->getPost($id) : null;
        $valid = $post !== null
            && $post->getObjId() === $this->object->getId()
            && in_array($direction, [1, -1], true)
            && $this->canVote($post->getUserId());

        if (!$valid) {
            if ($async) {
                $this->sendJson(['error' => $this->txt('no_permission')]);
            }
            $this->denyAndReturn($post !== null && $post->getObjId() === $this->object->getId() ? $post : null);
        }

        $user_id = $this->udf_user->getId();
        $current = $this->udf_repo->getUserVotes($type, [$id], $user_id)[$id] ?? 0;
        $new = $current === $direction ? 0 : $direction;
        $score = $this->udf_repo->vote($type, $id, $user_id, $new);

        if ($async) {
            $this->sendJson(['score' => $score, 'vote' => $new]);
        }

        if ($this->getRequestedString($this->udf_query, self::PARAM_RETURN) === 'feed') {
            $params = $this->getFeedParams($this->getFeedState());
            foreach ($params as $key => $value) {
                $this->ctrl->setParameter($this, $key, $value);
            }
            $this->ctrl->redirect($this, 'showContent', 'udf-p' . $post->getId());
        }
        $this->redirectToPost($post);
    }

    /**
     * Logged-in participants can vote, but not for their own posts.
     */
    private function canVote(int $author_id): bool
    {
        return $this->canParticipate() && !$this->isAuthor($author_id);
    }

    /**
     * @param array<string, mixed> $data
     * @return never
     */
    private function sendJson(array $data): void
    {
        global $DIC;
        $http = $DIC->http();
        $http->saveResponse(
            $http->response()
                ->withHeader('Content-Type', 'application/json')
                ->withBody(\ILIAS\Filesystem\Stream\Streams::ofString((string) json_encode($data)))
        );
        $http->sendResponse();
        $http->close();
    }

    /**
     * Sends votes in the background and updates the score without reloading.
     */
    private function addVoteScript(): void
    {
        $this->tpl->addOnLoadCode(<<<'JS'
if (!window.udfVoteInit) {
    window.udfVoteInit = true;
    document.addEventListener('submit', function (e) {
        var form = e.target, button = e.submitter;
        if (!form.classList || !form.classList.contains('udf-vote') || !button || !window.fetch || !window.FormData) {
            return;
        }
        e.preventDefault();
        var data = new FormData(form);
        data.append(button.name, button.value);
        data.append('udf_async', '1');
        fetch(form.action, {method: 'POST', body: data, credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (typeof j.score !== 'number') { window.location.reload(); return; }
                form.querySelector('.udf-score').textContent = j.score;
                form.classList.toggle('udf-up-active', j.vote === 1);
                form.classList.toggle('udf-down-active', j.vote === -1);
            })
            .catch(function () { window.location.reload(); });
    });
}
JS);
    }

    // ------------------------------------------------------------------
    // Post detail view
    // ------------------------------------------------------------------

    protected function showPost(?StandardForm $reply_form = null): void
    {
        $post = $this->requirePost();
        $this->tabs->activateTab(self::TAB_CONTENT);
        $this->showOfflineInfo();

        /** @var Interruptive[] $modals */
        $modals = [];

        $html = $this->getStyles() . '<div class="udf">';
        $html .= '<p class="udf-back">' . $this->ui_renderer->render(
            $this->ui_factory->link()->standard('← ' . $this->txt('back_to_forum'), $this->ctrl->getLinkTarget($this, 'showContent'))
        ) . '</p>';

        // the post itself: edit and delete as buttons in the toolbar
        if ($this->isAuthor($post->getUserId())) {
            $this->toolbar->addComponent($this->ui_factory->button()->standard(
                $this->txt('edit_post'),
                $this->linkToPost('editPost', $post)
            ));
        }
        if ($this->canDeletePost($post)) {
            $modal = $this->buildDeletePostModal($post);
            $modals[] = $modal;
            $this->toolbar->addComponent(
                $this->ui_factory->button()->standard($this->txt('delete_post'), '')->withOnClick($modal->getShowSignal())
            );
        }

        $user_id = $this->udf_user->getId();
        $post_vote = $this->udf_repo->getUserVotes(ilUdfRepository::ITEM_POST, [$post->getId()], $user_id)[$post->getId()] ?? 0;
        $html .= '<article class="udf-card udf-card-full udf-card-voted" id="udf-p' . $post->getId() . '">'
            . $this->renderVote(
                $post->getId(),
                $post->getScore(),
                $post_vote,
                $this->canVote($post->getUserId()),
                [self::PARAM_POST => $post->getId()]
            )
            . '<div class="udf-card-main">'
            . $this->renderPostLabels($post)
            . '<h2 class="udf-title">' . $this->esc($post->getTitle()) . '</h2>'
            . '<div class="udf-meta">' . $this->renderMeta($post->getUserId(), $post->getCreated(), $post->isEdited()) . '</div>'
            . '<div class="udf-body udf-md">' . $this->renderBody($post->getBody(), $post->isMarkdown()) . '</div>'
            . '</div></article>';

        $html .= $this->renderReplies($post, $modals, $reply_form);
        $html .= '</div>';

        if ($this->isLoggedIn()) {
            $this->udf_repo->setReadTime($user_id, $this->object->getId(), $post->getId(), time());
        }

        $this->addVoteScript();
        $this->tpl->setContent($html . $this->ui_renderer->render($modals));
    }






    // ------------------------------------------------------------------
    // Create / edit / delete posts
    // ------------------------------------------------------------------

    protected function createPost(?StandardForm $form = null): void
    {
        $this->requireParticipation();
        $this->tabs->activateTab(self::TAB_CONTENT);

        if ($form === null) {
            // coming from a category of the feed: preselect it
            $cat_id = $this->getRequestedInt($this->udf_query, self::PARAM_FILTER_CAT);
            $form = $this->buildPostForm(null, $this->getCategoryTitle($cat_id) !== null ? $cat_id : 0);
        }
        $this->showPostForm($form, $this->txt('new_post'));
    }

    protected function savePost(): void
    {
        $this->requireParticipation();
        $form = $this->buildPostForm(null, 0)->withRequest($this->udf_request);
        $data = $form->getData();
        if ($data === null) {
            $this->createPost($form);
            return;
        }

        $now = time();
        $post = new ilUdfPost();
        $post->setObjId($this->object->getId());
        $post->setUserId($this->udf_user->getId());
        $this->applyPostData($post, $data);
        $post->setCreated($now);
        $post->setUpdated($now);
        $this->udf_repo->savePost($post);

        $this->tpl->setOnScreenMessage('success', $this->txt('post_created'), true);
        $this->redirectToPost($post);
    }

    protected function editPost(?StandardForm $form = null): void
    {
        $post = $this->requirePost();
        $this->requireAuthor($post->getUserId(), $post);
        $this->tabs->activateTab(self::TAB_CONTENT);

        $form ??= $this->buildPostForm($post, $post->getCatId());
        $this->showPostForm($form, $this->txt('edit_post'));
    }

    protected function updatePost(): void
    {
        $post = $this->requirePost();
        $this->requireAuthor($post->getUserId(), $post);

        $form = $this->buildPostForm($post, $post->getCatId())->withRequest($this->udf_request);
        $data = $form->getData();
        if ($data === null) {
            $this->editPost($form);
            return;
        }

        $this->applyPostData($post, $data);
        $post->setUpdated(time());
        $this->udf_repo->savePost($post);

        $this->tpl->setOnScreenMessage('success', $this->txt('post_updated'), true);
        $this->redirectToPost($post);
    }

    protected function deletePost(): void
    {
        $post = $this->requirePost();
        if (!$this->canDeletePost($post)) {
            $this->denyAndReturn($post);
        }

        $this->udf_repo->deletePost($post);
        $this->tpl->setOnScreenMessage('success', $this->txt('post_deleted'), true);
        $this->ctrl->redirect($this, 'showContent');
    }

    /**
     * "Abbrechen" in the post and reply forms: back to the post (when editing)
     * or to the forum (new post), nothing is saved.
     */
    protected function cancelPost(): void
    {
        $id = $this->getRequestedInt($this->udf_query, self::PARAM_POST);
        $post = $id > 0 ? $this->udf_repo->getPost($id) : null;
        if ($post !== null && $post->getObjId() === $this->object->getId()) {
            $reply_id = $this->getRequestedInt($this->udf_query, self::PARAM_REPLY);
            $this->redirectToPost($post, $reply_id > 0 ? 'udf-r' . $reply_id : '');
        }
        $this->ctrl->redirect($this, 'showContent');
    }

    /**
     * Async endpoint for the preview tab of the Markdown field.
     */
    protected function renderMarkdownPreview(): void
    {
        $this->getMarkdownRenderer()->renderAsync();
    }

    /**
     * Form of a post (ILIAS UI form): category, title and text with the
     * Markdown editor (formatting buttons and preview).
     */
    private function buildPostForm(?ilUdfPost $post, int $cat_id): StandardForm
    {
        $field = $this->ui_factory->input()->field();
        $inputs = [];

        $categories = $this->getCategories();
        if ($categories !== []) {
            $options = [];
            foreach ($categories as $cat) {
                $options[(string) $cat->getId()] = $cat->getTitle();
            }
            $select = $field->select($this->txt('category'), $options)->withRequired(true);
            if (count($options) === 1) {
                // only one category: nothing to choose
                $cat_id = (int) array_key_first($options);
            }
            if (isset($options[(string) $cat_id])) {
                $select = $select->withValue((string) $cat_id);
            }
            $inputs['cat_id'] = $select;
        }

        $inputs['title'] = $field->text($this->txt('post_title'))
            ->withRequired(true)
            ->withMaxLength(255)
            ->withValue($post !== null ? $post->getTitle() : '');

        // text is required; only spaces do not count as text
        $body = $this->getForum()->usesMarkdown()
            ? $field->markdown($this->getMarkdownRenderer(), $this->txt('post_body'), $this->txt('post_body_info'))
            : $field->textarea($this->txt('post_body'));
        $inputs['body'] = $body
            ->withRequired(true)
            ->withValue($post !== null ? $post->getBody() : '')
            ->withAdditionalTransformation($this->udf_refinery->custom()->constraint(
                static fn($value): bool => trim((string) $value) !== '',
                $this->txt('body_required')
            ));

        $action = $post === null
            ? $this->ctrl->getFormAction($this, 'savePost')
            : $this->linkToPost('updatePost', $post, true);
        $cancel = $post === null
            ? $this->ctrl->getFormAction($this, 'cancelPost')
            : $this->linkToPost('cancelPost', $post, true);

        return $this->ui_factory->input()->container()->form()->standard($action, $inputs)
            ->withAdditionalFormAction($cancel, $this->lng->txt('cancel'))
            ->withSubmitLabel($post === null ? $this->txt('publish') : $this->lng->txt('save'));
    }

    /**
     * @param array<string, mixed> $data result of the post form
     */
    private function applyPostData(ilUdfPost $post, array $data): void
    {
        $cat_id = (int) ($data['cat_id'] ?? 0);
        $post->setCatId($this->getCategoryTitle($cat_id) !== null ? $cat_id : 0);
        $post->setTitle(trim((string) ($data['title'] ?? '')));
        $post->setBody(trim((string) ($data['body'] ?? '')));
        $post->setBodyFormat($this->getForum()->usesMarkdown() ? ilUdfPost::FORMAT_MARKDOWN : ilUdfPost::FORMAT_TEXT);
    }

    private function showPostForm(StandardForm $form, string $title): void
    {
        $this->addFormKeyboardScript();
        $this->tpl->setContent(
            $this->getStyles()
            . '<div class="udf udf-post-form">'
            . '<h2 class="udf-heading">' . $this->esc($title) . '</h2>'
            . $this->ui_renderer->render($form)
            . '</div>'
        );
    }

    /**
     * Keyboard in the post form:
     * - Tab in the title jumps directly into the text field (not to the
     *   formatting buttons in between)
     * - choosing a category moves the cursor to the title
     * - on opening, the cursor is in the title if there is nothing to choose
     *   (no category, only one, or already preselected), otherwise in the category
     */
    private function addFormKeyboardScript(): void
    {
        $this->tpl->addOnLoadCode(<<<'JS'
(function () {
    var form = document.querySelector('.udf-post-form form.c-form');
    if (!form) { return; }
    var title = form.querySelector('[data-il-ui-component="text-field-input"] input');
    var text = form.querySelector('[data-il-ui-component="markdown-field-input"] textarea, [data-il-ui-component="textarea-field-input"] textarea');
    var select = form.querySelector('[data-il-ui-component="select-field-input"] select');
    if (title && text) {
        title.addEventListener('keydown', function (e) {
            if (e.key === 'Tab' && !e.shiftKey && !e.altKey && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                text.focus();
            }
        });
    }
    if (select && title) {
        select.addEventListener('change', function () {
            if (select.value !== '') { title.focus(); }
        });
    }
    if (form.querySelector('.c-input__error-msg')) { return; }
    if (title && (!select || select.value !== '')) {
        title.focus();
        title.setSelectionRange(title.value.length, title.value.length);
    } else if (select) {
        select.focus();
    }
})();
JS);
    }

    /**
     * Text of a post or reply as HTML: Markdown or plain text, depending on how it was written.
     */
    private function renderBody(string $body, bool $is_markdown): string
    {
        return $is_markdown
            ? $this->getMarkdownRenderer()->render($body)
            : $this->getMarkdownRenderer()->renderPlain($body);
    }

    // ------------------------------------------------------------------
    // Replies
    // ------------------------------------------------------------------

    /**
     * Replies below a post (oldest first) and the reply field.
     *
     * @param Interruptive[] $modals collects the delete overlays
     */
    private function renderReplies(ilUdfPost $post, array &$modals, ?StandardForm $reply_form): string
    {
        $replies = $this->udf_repo->getRepliesForPost($post->getId());
        $count = count($replies);

        $html = '<section class="udf-replies" id="udf-replies">'
            . '<h3 class="udf-heading">' . $this->esc(
                $count === 1 ? $this->txt('reply_count_one') : sprintf($this->txt('reply_count'), $count)
            ) . '</h3>';

        foreach ($replies as $reply) {
            $actions = [];
            if ($this->isAuthor($reply->getUserId())) {
                $actions[] = '<a href="' . $this->esc($this->linkToReply('editReply', $post, $reply)) . '">'
                    . $this->esc($this->txt('edit')) . '</a>';
            }
            if ($this->canDeleteReply($reply)) {
                $modal = $this->buildConfirmModal(
                    $this->txt('confirm_delete_reply'),
                    $this->linkToReply('deleteReply', $post, $reply, true),
                    $reply->getId(),
                    $this->shorten($this->getMarkdownRenderer()->toPlainText($reply->getBody()), 80),
                    $this->lng->txt('delete')
                );
                $modals[] = $modal;
                $actions[] = $this->ui_renderer->render(
                    $this->ui_factory->button()->shy($this->lng->txt('delete'), '')->withOnClick($modal->getShowSignal())
                );
            }

            $html .= '<article class="udf-reply" id="udf-r' . $reply->getId() . '">'
                . '<div class="udf-meta">' . $this->renderMeta($reply->getUserId(), $reply->getCreated(), $reply->isEdited()) . '</div>'
                . '<div class="udf-body udf-md">' . $this->renderBody($reply->getBody(), $reply->isMarkdown()) . '</div>'
                . ($actions !== [] ? '<div class="udf-reply-actions">' . implode(' · ', $actions) . '</div>' : '')
                . '</article>';
        }

        if ($count === 0) {
            $html .= '<p class="udf-empty">' . $this->esc($this->txt('no_replies')) . '</p>';
        }

        if ($this->canParticipate()) {
            $reply_form ??= $this->buildReplyForm($post, null);
            $html .= '<div class="udf-reply-form udf-post-form">' . $this->ui_renderer->render($reply_form) . '</div>';
        }

        return $html . '</section>';
    }

    /**
     * Reply field: Markdown editor or simple text field (setting), text required.
     */
    private function buildReplyForm(ilUdfPost $post, ?ilUdfReply $reply): StandardForm
    {
        $field = $this->ui_factory->input()->field();
        $label = $this->txt('your_reply');
        $body = $this->getForum()->usesMarkdown()
            ? $field->markdown($this->getMarkdownRenderer(), $label)
            : $field->textarea($label);
        $body = $body
            ->withRequired(true)
            ->withValue($reply !== null ? $reply->getBody() : '')
            ->withAdditionalTransformation($this->udf_refinery->custom()->constraint(
                static fn($value): bool => trim((string) $value) !== '',
                $this->txt('body_required')
            ));

        if ($reply === null) {
            return $this->ui_factory->input()->container()->form()
                ->standard($this->linkToPost('saveReply', $post, true), ['body' => $body])
                ->withSubmitLabel($this->txt('send_reply'));
        }
        return $this->ui_factory->input()->container()->form()
            ->standard($this->linkToReply('updateReply', $post, $reply, true), ['body' => $body])
            ->withAdditionalFormAction($this->linkToReply('cancelPost', $post, $reply, true), $this->lng->txt('cancel'))
            ->withSubmitLabel($this->lng->txt('save'));
    }

    protected function saveReply(): void
    {
        $post = $this->requirePost();
        $this->requireParticipation($post);

        $form = $this->buildReplyForm($post, null)->withRequest($this->udf_request);
        $data = $form->getData();
        if ($data === null) {
            $this->showPost($form);
            return;
        }

        $now = time();
        $reply = new ilUdfReply();
        $reply->setPostId($post->getId());
        $reply->setUserId($this->udf_user->getId());
        $reply->setBody(trim((string) ($data['body'] ?? '')));
        $reply->setBodyFormat($this->getForum()->usesMarkdown() ? ilUdfPost::FORMAT_MARKDOWN : ilUdfPost::FORMAT_TEXT);
        $reply->setCreated($now);
        $reply->setUpdated($now);
        $this->udf_repo->saveReply($reply);

        $this->tpl->setOnScreenMessage('success', $this->txt('reply_saved'), true);
        $this->redirectToPost($post, 'udf-r' . $reply->getId());
    }

    protected function editReply(?StandardForm $form = null): void
    {
        $post = $this->requirePost();
        $reply = $this->requireReply($post);
        $this->requireAuthor($reply->getUserId(), $post);
        $this->tabs->activateTab(self::TAB_CONTENT);

        $form ??= $this->buildReplyForm($post, $reply);
        $this->tpl->setContent(
            $this->getStyles()
            . '<div class="udf udf-post-form">'
            . '<h2 class="udf-heading">' . $this->esc($this->txt('edit_reply')) . '</h2>'
            . $this->ui_renderer->render($form)
            . '</div>'
        );
    }

    protected function updateReply(): void
    {
        $post = $this->requirePost();
        $reply = $this->requireReply($post);
        $this->requireAuthor($reply->getUserId(), $post);

        $form = $this->buildReplyForm($post, $reply)->withRequest($this->udf_request);
        $data = $form->getData();
        if ($data === null) {
            $this->editReply($form);
            return;
        }

        $reply->setBody(trim((string) ($data['body'] ?? '')));
        $reply->setBodyFormat($this->getForum()->usesMarkdown() ? ilUdfPost::FORMAT_MARKDOWN : ilUdfPost::FORMAT_TEXT);
        $reply->setUpdated(time());
        $this->udf_repo->saveReply($reply);

        $this->tpl->setOnScreenMessage('success', $this->txt('reply_updated'), true);
        $this->redirectToPost($post, 'udf-r' . $reply->getId());
    }

    protected function deleteReply(): void
    {
        $post = $this->requirePost();
        $reply = $this->requireReply($post);
        if (!$this->canDeleteReply($reply)) {
            $this->denyAndReturn($post);
        }

        $this->udf_repo->deleteReply($reply);
        $this->tpl->setOnScreenMessage('success', $this->txt('reply_deleted'), true);
        $this->redirectToPost($post, 'udf-replies');
    }

    private function canDeleteReply(ilUdfReply $reply): bool
    {
        return $this->isAuthor($reply->getUserId()) || $this->isModerator();
    }

    /**
     * Reply from the request; it must belong to the given post.
     */
    private function requireReply(ilUdfPost $post): ilUdfReply
    {
        $id = $this->getRequestedInt($this->udf_query, self::PARAM_REPLY);
        $reply = $id > 0 ? $this->udf_repo->getReply($id) : null;
        if ($reply === null || $reply->getPostId() !== $post->getId()) {
            $this->tpl->setOnScreenMessage('failure', $this->txt('reply_not_found'), true);
            $this->redirectToPost($post);
        }
        return $reply;
    }

    private function linkToReply(string $cmd, ilUdfPost $post, ilUdfReply $reply, bool $form_action = false): string
    {
        $this->ctrl->setParameter($this, self::PARAM_REPLY, $reply->getId());
        $link = $this->linkToPost($cmd, $post, $form_action);
        $this->ctrl->clearParameterByClass(self::class, self::PARAM_REPLY);
        return $link;
    }

    private function shorten(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length)) . ' …' : $text;
    }

    // ------------------------------------------------------------------
    // Statistics
    // ------------------------------------------------------------------

    private function canViewStatistics(): bool
    {
        return $this->access->checkAccess(ilUdonForumPlugin::OP_STATISTICS, '', $this->object->getRefId());
    }

    private function setStatisticsSubTabs(string $active): void
    {
        $this->tabs->activateTab(self::TAB_STATISTICS);
        $this->tabs->addSubTab(
            self::SUBTAB_STAT_STANDARD,
            $this->txt('stat_standard'),
            $this->ctrl->getLinkTarget($this, 'showStatistics')
        );
        $this->tabs->addSubTab(
            self::SUBTAB_STAT_NETWORK,
            $this->txt('stat_network'),
            $this->ctrl->getLinkTarget($this, 'showNetwork')
        );
        $this->tabs->activateSubTab($active);
    }

    /**
     * Tab "Statistik", sub tab "Standard": table of everybody who was active in
     * the forum (opened a post or replied), sortable by every column header.
     */
    protected function showStatistics(): void
    {
        $this->setStatisticsSubTabs(self::SUBTAB_STAT_STANDARD);

        $records = $this->buildStatisticsRecords();
        if ($records === []) {
            $this->tpl->setOnScreenMessage('info', $this->txt('stat_no_activity'));
            return;
        }

        $columns_factory = $this->ui_factory->table()->column();
        $columns = [
            ilUdfStatisticsTable::COL_LASTNAME => $columns_factory->text($this->txt('stat_lastname')),
            ilUdfStatisticsTable::COL_FIRSTNAME => $columns_factory->text($this->txt('stat_firstname')),
            ilUdfStatisticsTable::COL_EMAIL => $columns_factory->eMail($this->txt('stat_email')),
            ilUdfStatisticsTable::COL_POSTS => $columns_factory->number($this->txt('stat_posts')),
            ilUdfStatisticsTable::COL_REPLIES => $columns_factory->number($this->txt('stat_replies')),
            ilUdfStatisticsTable::COL_WORDS => $columns_factory->number($this->txt('stat_words')),
        ];

        $table = $this->ui_factory->table()->data(
            new ilUdfStatisticsTable($records),
            $this->txt('stat_participants'),
            $columns
        )->withId('udf_stat_' . $this->object->getId())
         ->withRequest($this->udf_request);

        // the sort button of the table is hidden; sorting via the column headers stays
        $this->tpl->setContent($this->getStyles() . '<div class="udf-stat">' . $this->ui_renderer->render($table) . '</div>');
    }

    /**
     * Tab "Statistik", sub tab "Spinnennetz": who wrote how many posts (circle)
     * and who replied to whom (lines).
     */
    protected function showNetwork(): void
    {
        $this->setStatisticsSubTabs(self::SUBTAB_STAT_NETWORK);

        $texts = $this->udf_repo->getTextsPerUser($this->object->getId());
        if ($texts === []) {
            $this->tpl->setOnScreenMessage('info', $this->txt('stat_no_activity'));
            return;
        }

        $nodes = [];
        foreach ($texts as $user_id => $user_texts) {
            $posts = count($user_texts['posts'] ?? []);
            $replies = count($user_texts['replies'] ?? []);
            $name = html_entity_decode($this->getUserName($user_id), ENT_QUOTES, 'UTF-8');
            $nodes[$user_id] = [
                'label' => $name,
                'tooltip' => sprintf($this->txt('network_node_tooltip'), $name, $posts, $replies),
                'posts' => $posts,
            ];
        }

        $edges = [];
        foreach (ilUdfNetworkGraph::mergeInteractions($this->udf_repo->getReplyInteractions($this->object->getId())) as [$a, $b, $weight]) {
            if (!isset($nodes[$a], $nodes[$b])) {
                continue;
            }
            $edges[] = [$a, $b, $weight, sprintf($this->txt('network_edge_tooltip'), $nodes[$a]['label'], $nodes[$b]['label'], $weight)];
        }

        $graph = new ilUdfNetworkGraph($nodes, $edges);
        $this->tpl->setContent(
            $this->getStyles()
            . '<div class="udf-stat">'
            . '<p class="udf-network-legend">'
            . '<span class="udf-legend-ring"></span> ' . $this->esc($this->txt('network_legend_ring'))
            . '<span class="udf-legend-line"></span> ' . $this->esc($this->txt('network_legend_line'))
            . '</p>'
            . '<div class="udf-network-box">' . $graph->toSvg($this->txt('stat_network')) . '</div>'
            . '</div>'
        );
    }

    /**
     * @return array<int, array<string, string|int>> user id => record
     */
    private function buildStatisticsRecords(): array
    {
        // only people who were active in the forum: opened a post or replied
        $texts = $this->udf_repo->getTextsPerUser($this->object->getId());
        $user_ids = array_keys($texts);

        $renderer = $this->getMarkdownRenderer();
        $records = [];
        foreach ($user_ids as $user_id) {
            $name = ilObjUser::_lookupName($user_id);

            $words = 0;
            foreach (array_merge($texts[$user_id]['posts'] ?? [], $texts[$user_id]['replies'] ?? []) as [$body, $format]) {
                $plain = $format === ilUdfPost::FORMAT_TEXT ? $body : $renderer->toPlainText($body);
                $words += ilUdfStatisticsTable::countWords($plain);
            }

            $records[$user_id] = [
                ilUdfStatisticsTable::COL_LASTNAME => ($name['login'] ?? '') !== '' ? (string) $name['lastname'] : $this->txt('deleted_user'),
                ilUdfStatisticsTable::COL_FIRSTNAME => (string) ($name['firstname'] ?? ''),
                ilUdfStatisticsTable::COL_EMAIL => ($name['login'] ?? '') !== '' ? ilObjUser::_lookupEmail($user_id) : '',
                ilUdfStatisticsTable::COL_POSTS => count($texts[$user_id]['posts'] ?? []),
                ilUdfStatisticsTable::COL_REPLIES => count($texts[$user_id]['replies'] ?? []),
                ilUdfStatisticsTable::COL_WORDS => $words,
            ];
        }
        return $records;
    }

    private function getMarkdownRenderer(): ilUdfMarkdownRenderer
    {
        if ($this->udf_markdown === null) {
            global $DIC;
            $this->udf_markdown = new ilUdfMarkdownRenderer(
                $this->ctrl->getLinkTarget($this, 'renderMarkdownPreview', null, true),
                $this->udf_refinery,
                $DIC->http()
            );
        }
        return $this->udf_markdown;
    }









    // ------------------------------------------------------------------
    // Categories (lecturers)
    // ------------------------------------------------------------------

    /**
     * Creates the default categories once per forum.
     */
    private function ensureDefaultCategories(): void
    {
        $forum = $this->getForum();
        if ($forum->getId() <= 0 || $forum->areCategoriesInitialized()) {
            return;
        }

        if ($this->udf_repo->getCategories($forum->getId()) === []) {
            foreach (['default_cat_general', 'default_cat_questions', 'default_cat_exam'] as $key) {
                $cat = new ilUdfCategory();
                $cat->setObjId($forum->getId());
                $cat->setTitle($this->txt($key));
                $this->udf_repo->saveCategory($cat);
            }
        }

        $this->udf_repo->markCategoriesInitialized($forum->getId());
        $forum->setCategoriesInitialized(true);
        $this->udf_categories = null;
    }

    protected function showCategories(): void
    {
        $this->tabs->activateTab(self::TAB_CATEGORIES);

        $this->toolbar->addComponent($this->ui_factory->button()->primary(
            $this->txt('new_category'),
            $this->ctrl->getLinkTarget($this, 'createCategory')
        ));

        $categories = $this->getCategories();
        if ($categories === []) {
            $this->tpl->setOnScreenMessage('info', $this->txt('no_categories'));
            return;
        }

        $counts = $this->udf_repo->countPostsPerCategory($this->object->getId());
        $items = [];
        $modals = [];
        $last = count($categories) - 1;
        foreach ($categories as $i => $cat) {
            $actions = [
                $this->ui_factory->button()->shy($this->txt('edit'), $this->linkToCategory('editCategory', $cat)),
            ];
            if ($i > 0) {
                $actions[] = $this->ui_factory->button()->shy($this->txt('move_up'), $this->linkToCategory('moveCategoryUp', $cat));
            }
            if ($i < $last) {
                $actions[] = $this->ui_factory->button()->shy($this->txt('move_down'), $this->linkToCategory('moveCategoryDown', $cat));
            }
            $modal = $this->buildConfirmModal(
                $this->txt('confirm_delete_category'),
                $this->linkToCategory('deleteCategory', $cat, true),
                $cat->getId(),
                $cat->getTitle(),
                $this->lng->txt('delete')
            );
            $modals[] = $modal;
            $actions[] = $this->ui_factory->button()->shy($this->lng->txt('delete'), '')
                ->withOnClick($modal->getShowSignal());

            $items[] = $this->ui_factory->item()->standard($this->esc($cat->getTitle()))
                ->withProperties([$this->txt('posts') => (string) ($counts[$cat->getId()] ?? 0)])
                ->withActions($this->ui_factory->dropdown()->standard($actions));
        }

        $panel = $this->ui_factory->panel()->listing()->standard(
            $this->txt('categories'),
            [$this->ui_factory->item()->group('', $items)]
        );

        $this->tpl->setContent($this->ui_renderer->render(array_merge([$panel], $modals)));
    }

    protected function createCategory(?ilPropertyFormGUI $form = null): void
    {
        $this->tabs->activateTab(self::TAB_CATEGORIES);
        $form ??= $this->initCategoryForm(null);
        $this->tpl->setContent($form->getHTML());
    }

    protected function saveCategory(): void
    {
        $form = $this->initCategoryForm(null);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->createCategory($form);
            return;
        }

        $cat = new ilUdfCategory();
        $cat->setObjId($this->object->getId());
        $cat->setTitle(trim((string) $form->getInput('title')));
        $this->udf_repo->saveCategory($cat);

        $this->tpl->setOnScreenMessage('success', $this->txt('category_saved'), true);
        $this->ctrl->redirect($this, 'showCategories');
    }

    protected function editCategory(?ilPropertyFormGUI $form = null): void
    {
        $cat = $this->requireCategory();
        $this->tabs->activateTab(self::TAB_CATEGORIES);
        if ($form === null) {
            $form = $this->initCategoryForm($cat);
            $form->setValuesByArray(['title' => $cat->getTitle()]);
        }
        $this->tpl->setContent($form->getHTML());
    }

    protected function updateCategory(): void
    {
        $cat = $this->requireCategory();
        $form = $this->initCategoryForm($cat);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->editCategory($form);
            return;
        }

        $cat->setTitle(trim((string) $form->getInput('title')));
        $this->udf_repo->saveCategory($cat);

        $this->tpl->setOnScreenMessage('success', $this->txt('category_saved'), true);
        $this->ctrl->redirect($this, 'showCategories');
    }

    protected function deleteCategory(): void
    {
        $cat = $this->requireCategory();
        $this->udf_repo->deleteCategory($cat);

        $this->tpl->setOnScreenMessage('success', $this->txt('category_deleted'), true);
        $this->ctrl->redirect($this, 'showCategories');
    }

    protected function moveCategoryUp(): void
    {
        $this->udf_repo->moveCategory($this->requireCategory(), -1);
        $this->ctrl->redirect($this, 'showCategories');
    }

    protected function moveCategoryDown(): void
    {
        $this->udf_repo->moveCategory($this->requireCategory(), 1);
        $this->ctrl->redirect($this, 'showCategories');
    }

    private function initCategoryForm(?ilUdfCategory $cat): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        if ($cat === null) {
            $form->setTitle($this->txt('new_category'));
            $form->setFormAction($this->ctrl->getFormAction($this, 'saveCategory'));
            $form->addCommandButton('saveCategory', $this->lng->txt('save'));
        } else {
            $form->setTitle($this->txt('edit_category'));
            $form->setFormAction($this->linkToCategory('updateCategory', $cat, true));
            $form->addCommandButton('updateCategory', $this->lng->txt('save'));
        }
        $form->addCommandButton('showCategories', $this->lng->txt('cancel'));

        $title = new ilTextInputGUI($this->txt('category_title'), 'title');
        $title->setRequired(true);
        $title->setMaxLength(255);
        $form->addItem($title);

        return $form;
    }

    private function requireCategory(): ilUdfCategory
    {
        $id = $this->getRequestedInt($this->udf_query, self::PARAM_CATEGORY);
        $cat = $id > 0 ? $this->udf_repo->getCategory($id) : null;
        if ($cat === null || $cat->getObjId() !== $this->object->getId()) {
            $this->tpl->setOnScreenMessage('failure', $this->txt('category_not_found'), true);
            $this->ctrl->redirect($this, 'showCategories');
        }
        return $cat;
    }

    private function linkToCategory(string $cmd, ilUdfCategory $cat, bool $form_action = false): string
    {
        $this->ctrl->setParameter($this, self::PARAM_CATEGORY, $cat->getId());
        $link = $form_action ? $this->ctrl->getFormAction($this, $cmd) : $this->ctrl->getLinkTarget($this, $cmd);
        $this->ctrl->clearParameterByClass(self::class, self::PARAM_CATEGORY);
        return $link;
    }

    /**
     * @return ilUdfCategory[]
     */
    private function getCategories(): array
    {
        if ($this->udf_categories === null) {
            $this->udf_categories = $this->udf_repo->getCategories($this->object->getId());
        }
        return $this->udf_categories;
    }

    /**
     * Title of a category of this forum, null if it does not exist (any more).
     */
    private function getCategoryTitle(int $cat_id): ?string
    {
        foreach ($this->getCategories() as $cat) {
            if ($cat->getId() === $cat_id) {
                return $cat->getTitle();
            }
        }
        return null;
    }

    private function getCategoryColor(int $cat_id): string
    {
        foreach ($this->getCategories() as $i => $cat) {
            if ($cat->getId() === $cat_id) {
                return self::CATEGORY_COLORS[$i % count(self::CATEGORY_COLORS)];
            }
        }
        return self::CATEGORY_COLORS[0];
    }


    // ------------------------------------------------------------------
    // Permissions
    // ------------------------------------------------------------------

    /**
     * Logged-in users with read permission can write posts and vote.
     */
    private function canParticipate(): bool
    {
        return $this->isLoggedIn()
            && $this->access->checkAccess('read', '', $this->object->getRefId());
    }

    private function isLoggedIn(): bool
    {
        $user_id = $this->udf_user->getId();
        return $user_id > 0 && $user_id !== ANONYMOUS_USER_ID;
    }

    private function isModerator(): bool
    {
        return $this->access->checkAccess('write', '', $this->object->getRefId());
    }

    private function isAuthor(int $user_id): bool
    {
        return $user_id === $this->udf_user->getId() && $user_id !== ANONYMOUS_USER_ID;
    }

    private function requireParticipation(?ilUdfPost $post = null): void
    {
        if (!$this->canParticipate()) {
            $this->denyAndReturn($post);
        }
    }

    private function requireAuthor(int $user_id, ilUdfPost $post): void
    {
        if (!$this->isAuthor($user_id)) {
            $this->denyAndReturn($post);
        }
    }

    /**
     * @return never
     */
    private function denyAndReturn(?ilUdfPost $post): void
    {
        $this->tpl->setOnScreenMessage('failure', $this->txt('no_permission'), true);
        if ($post !== null) {
            $this->redirectToPost($post);
        }
        $this->ctrl->redirect($this, 'showContent');
    }

    // ------------------------------------------------------------------
    // Request helpers
    // ------------------------------------------------------------------

    private function getRequestedInt(ArrayBasedRequestWrapper $wrapper, string $key): int
    {
        return max(0, $this->getRequestedSignedInt($wrapper, $key));
    }

    /**
     * Integer from the request; 0 if missing or not a number.
     */
    private function getRequestedSignedInt(ArrayBasedRequestWrapper $wrapper, string $key): int
    {
        if (!$wrapper->has($key)) {
            return 0;
        }
        try {
            return $wrapper->retrieve($key, $this->udf_refinery->kindlyTo()->int());
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function getRequestedString(ArrayBasedRequestWrapper $wrapper, string $key): string
    {
        if (!$wrapper->has($key)) {
            return '';
        }
        try {
            return $wrapper->retrieve($key, $this->udf_refinery->kindlyTo()->string());
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Post from the request; it must belong to this forum.
     */
    private function requirePost(): ilUdfPost
    {
        $id = $this->getRequestedInt($this->udf_query, self::PARAM_POST);
        $post = $id > 0 ? $this->udf_repo->getPost($id) : null;
        if ($post === null || $post->getObjId() !== $this->object->getId()) {
            $this->tpl->setOnScreenMessage('failure', $this->txt('post_not_found'), true);
            $this->ctrl->redirect($this, 'showContent');
        }
        return $post;
    }


    private function linkToPost(string $cmd, ilUdfPost $post, bool $form_action = false): string
    {
        $this->ctrl->setParameter($this, self::PARAM_POST, $post->getId());
        $link = $form_action ? $this->ctrl->getFormAction($this, $cmd) : $this->ctrl->getLinkTarget($this, $cmd);
        $this->ctrl->clearParameterByClass(self::class, self::PARAM_POST);
        return $link;
    }


    /**
     * @return never
     */
    private function redirectToPost(ilUdfPost $post, string $anchor = ''): void
    {
        $this->ctrl->setParameter($this, self::PARAM_POST, $post->getId());
        $this->ctrl->redirect($this, 'showPost', $anchor !== '' ? $anchor : null);
    }

    // ------------------------------------------------------------------
    // Rendering helpers
    // ------------------------------------------------------------------

    private function getForum(): ilObjUdonForum
    {
        /** @var ilObjUdonForum $forum */
        $forum = $this->object;
        return $forum;
    }

    private function showOfflineInfo(): void
    {
        if (!$this->getForum()->isOnline()) {
            $this->tpl->setOnScreenMessage('question', $this->txt('object_offline_info'));
        }
    }

    /**
     * Confirmation overlay (interruptive modal) as used in the ILIAS core.
     */
    private function buildConfirmModal(
        string $message,
        string $form_action,
        int $item_id,
        string $item_title,
        string $action_label
    ): Interruptive {
        return $this->ui_factory->modal()->interruptive(
            $this->txt('confirm'),
            $message,
            $form_action
        )->withAffectedItems([
            $this->ui_factory->modal()->interruptiveItem()->standard(
                (string) $item_id,
                $this->esc($item_title)
            ),
        ])->withActionButtonLabel($action_label);
    }

    /**
     * "Firstname Lastname [Lecturer] · 2 hours ago · edited"
     */
    private function renderMeta(int $user_id, int $created, bool $edited): string
    {
        $parts = ['<span class="udf-author">' . $this->getUserName($user_id) . '</span>'
            . ($this->isLecturer($user_id) ? ' <span class="udf-badge">' . $this->txt('lecturer') . '</span>' : '')];
        $parts[] = '<span title="' . $this->esc($this->formatDate($created)) . '">' . $this->esc($this->formatRelativeTime($created)) . '</span>';
        if ($edited) {
            $parts[] = '<span class="udf-edited">' . $this->txt('edited') . '</span>';
        }
        return implode(' · ', $parts);
    }




    private function formatRelativeTime(int $timestamp): string
    {
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return $this->txt('time_just_now');
        }
        if ($diff < 3600) {
            return sprintf($this->txt('time_minutes'), intdiv($diff, 60));
        }
        if ($diff < 86400) {
            return sprintf($this->txt('time_hours'), intdiv($diff, 3600));
        }
        if ($diff < 2 * 86400) {
            return $this->txt('time_yesterday');
        }
        if ($diff < 7 * 86400) {
            return sprintf($this->txt('time_days'), intdiv($diff, 86400));
        }
        return $this->formatDate($timestamp);
    }

    private function formatDate(int $timestamp): string
    {
        return ilDatePresentation::formatDate(new ilDateTime($timestamp, IL_CAL_UNIX));
    }

    /**
     * Users with write permission on this forum are marked as lecturers.
     */
    private function isLecturer(int $user_id): bool
    {
        if (!isset($this->udf_lecturer_cache[$user_id])) {
            $this->udf_lecturer_cache[$user_id] = $user_id !== ANONYMOUS_USER_ID
                && $this->access->checkAccessOfUser($user_id, 'write', '', $this->object->getRefId());
        }
        return $this->udf_lecturer_cache[$user_id];
    }

    /**
     * @return string escaped "Firstname Lastname"
     */
    private function getUserName(int $user_id): string
    {
        if (!isset($this->udf_name_cache[$user_id])) {
            $name = ilObjUser::_lookupName($user_id);
            if (($name['login'] ?? '') === '') {
                $this->udf_name_cache[$user_id] = $this->txt('deleted_user');
            } else {
                $this->udf_name_cache[$user_id] = $this->esc(trim(
                    ($name['firstname'] ?? '') . ' ' . ($name['lastname'] ?? '')
                ));
            }
        }
        return $this->udf_name_cache[$user_id];
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private function getStyles(): string
    {
        return <<<'CSS'
<style>
.udf { max-width: 860px; }
/* post form: full width, the text field (Markdown) with the label above it and taller */
.udf.udf-post-form { max-width: none; }
.udf-post-form .c-input[data-il-ui-component="markdown-field-input"],
.udf-post-form .c-input[data-il-ui-component="textarea-field-input"] { grid-template-columns: 100%; grid-template-areas: "label" "field" "error" "help"; }
.udf-post-form .c-input[data-il-ui-component="markdown-field-input"] > label,
.udf-post-form .c-input[data-il-ui-component="textarea-field-input"] > label { margin-bottom: 0.5rem; }
.udf-post-form [data-il-ui-component="textarea-field-input"] .c-input__field textarea { height: 26rem !important; min-height: 16rem; width: 100%; }
.udf-post-form [data-il-ui-component="markdown-field-input"] .c-input__field .c-field-markdown textarea { height: 26rem !important; min-height: 16rem; width: 100%; }
.udf-post-form [data-il-ui-component="markdown-field-input"] .c-field-markdown__preview { min-height: 26rem; }
.udf-heading { font-size: 1.1rem; margin: 0.5rem 0 0.75rem; }
.udf-card { border: 1px solid #d6d6d6; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 0.75rem; background: #fff; }
.udf-card:hover { border-color: #f0b27a; }
.udf-card-full:hover { border-color: #d6d6d6; }
.udf-title { font-size: 1.1rem; font-weight: 600; margin: 0 0 0.25rem; }
.udf-card-full .udf-title { font-size: 1.4rem; }
.udf-title a { color: inherit; text-decoration: none; }
.udf-title a:hover { text-decoration: underline; color: #a94208; }
.udf-meta { font-size: 0.85rem; color: #6f6f6f; }
.udf-author { font-weight: 600; color: #333; }
.udf-badge { display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 0 0.4rem; border-radius: 4px; background: #fff0e0; color: #a94208; }
.udf-edited { font-style: italic; }
.udf-preview, .udf-body { margin: 0.5rem 0; line-height: 1.5; overflow-wrap: anywhere; }
.udf-md > :first-child { margin-top: 0; }
.udf-md > :last-child { margin-bottom: 0; }
.udf-md p, .udf-md ul, .udf-md ol, .udf-md blockquote, .udf-md pre { margin: 0 0 0.6rem; }
.udf-md ul, .udf-md ol { padding-left: 1.5rem; }
.udf-md h1, .udf-md h2, .udf-md h3, .udf-md h4 { font-size: 1.1rem; font-weight: 700; margin: 0.8rem 0 0.4rem; }
.udf-md h1 { font-size: 1.3rem; }
.udf-md blockquote { border-left: 3px solid #f0b27a; padding-left: 0.75rem; color: #555; }
.udf-md code { background: #f3f3f3; padding: 0 0.25rem; border-radius: 3px; font-size: 0.9em; }
.udf-md pre { background: #f6f6f6; padding: 0.6rem 0.8rem; border-radius: 6px; overflow-x: auto; }
.udf-md pre code { background: none; padding: 0; }
.udf-replies { margin-top: 1.5rem; }
.udf-reply { border-left: 3px solid #f0b27a; padding: 0.4rem 0 0.4rem 0.9rem; margin: 0 0 1rem 0.25rem; }
.udf-reply .udf-body { margin: 0.3rem 0; }
.udf-reply-actions { font-size: 0.85rem; }
.udf-reply-actions a, .udf-reply-actions button { font-weight: 600; }
.udf-reply-form { margin-top: 1rem; }
/* statistics: no sort button above the table (sorting via column headers) */
.udf-stat .il-viewcontrol-sortation { display: none !important; }
.udf-network-box { border: 1px solid #f0b27a; border-radius: 8px; background: #fff; padding: 0.5rem; }
.udf-network { width: 100%; height: auto; display: block; }
.udf-network-node:hover circle { stroke-width: 3; }
.udf-network-legend { font-size: 0.85rem; color: #555; display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem 0.6rem; }
.udf-legend-ring { display: inline-block; width: 16px; height: 16px; border-radius: 50%; border: 2px solid #c95100; background: radial-gradient(circle, #000 0 3px, rgba(247,166,74,0.35) 3.5px); }
.udf-legend-line { display: inline-block; width: 28px; height: 5px; border-radius: 3px; background: rgba(169,66,8,0.45); margin-left: 1rem; }
.udf-reply-form [data-il-ui-component="markdown-field-input"] .c-input__field .c-field-markdown textarea,
.udf-reply-form [data-il-ui-component="textarea-field-input"] .c-input__field textarea { height: 10rem !important; min-height: 6rem; }
.udf-reply-form [data-il-ui-component="markdown-field-input"] .c-field-markdown__preview { min-height: 10rem; }
.udf-back { margin-bottom: 0.75rem; }
.udf-empty { color: #6f6f6f; }
.udf-pager { display: flex; gap: 0.5rem; margin-top: 1rem; }
.udf-controls { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 0.5rem 1rem; margin: 0.25rem 0 1rem; }
.udf-filters { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
.udf-sorting { justify-content: flex-end; margin-left: auto; }
.udf-sorting-periods { display: flex; flex-wrap: wrap; gap: 0.4rem; flex-basis: 100%; justify-content: flex-end; }
.udf-chip { display: inline-block; padding: 0.2rem 0.75rem; border: 1px solid #f0b27a; border-radius: 999px; font-size: 0.85rem; color: #a94208; text-decoration: none; background: #fff; }
.udf-chip:hover, .udf-chip:focus { border-color: #c95100; background: #fff4e6; color: #a94208; text-decoration: none; }
.udf-chip-active, .udf-chip-active:hover, .udf-chip-active:focus { background: #c95100; border-color: #c95100; color: #fff; }
/* orange inside the forum: links in posts and replies, buttons in the forms */
.udf .udf-body a, .udf .udf-reply-actions a, .udf .udf-reply-actions button.btn-link { color: #a94208; }
.udf .udf-body a:hover, .udf .udf-reply-actions a:hover { color: #7f3105; }
.udf .c-form__actions .btn:not([formaction]) { background-color: #c95100 !important; border-color: #c95100 !important; color: #fff !important; }
.udf .c-form__actions .btn:not([formaction]):hover, .udf .c-form__actions .btn:not([formaction]):focus { background-color: #a94208 !important; border-color: #a94208 !important; }
.udf .c-form__actions .btn[formaction] { background-color: #fff !important; border-color: #c95100 !important; color: #a94208 !important; }
.udf .c-form__actions .btn[formaction]:hover, .udf .c-form__actions .btn[formaction]:focus { background-color: #fff4e6 !important; }
.udf .c-field-markdown__actions .btn { background-color: #c95100 !important; border-color: #c95100 !important; color: #fff !important; }
.udf .c-field-markdown__actions .btn:hover, .udf .c-field-markdown__actions .btn:focus { background-color: #a94208 !important; border-color: #a94208 !important; }
.udf .c-field-markdown__actions .btn .glyph, .udf .c-field-markdown__actions .btn span { color: #fff !important; }
.udf .il-viewcontrol-mode .btn.engaged { border-color: #c95100 !important; color: #a94208 !important; }
.udf .il-viewcontrol-mode .btn:not(.engaged) { color: #a94208 !important; }
/* toolbar of the forum pages: other buttons with orange outline */
.c-toolbar .btn.btn-default:not(.btn-primary) { background-color: #fff !important; border-color: #c95100 !important; color: #a94208 !important; }
.c-toolbar .btn.btn-default:not(.btn-primary):hover, .c-toolbar .btn.btn-default:not(.btn-primary):focus { background-color: #fff4e6 !important; }
/* "Neuer Beitrag" (primary button in the toolbar of the forum) in orange */
.c-toolbar .btn.btn-primary { background-color: #c95100 !important; border-color: #c95100 !important; color: #fff !important; }
.c-toolbar .btn.btn-primary:hover, .c-toolbar .btn.btn-primary:focus { background-color: #a94208 !important; border-color: #a94208 !important; color: #fff !important; }
.udf-labels { margin-bottom: 0.3rem; }
.udf-cat { display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 0.05rem 0.5rem; border-radius: 999px; color: #333; }
.udf-card-voted { display: flex; gap: 0.75rem; align-items: flex-start; }
.udf-card-main { flex: 1; min-width: 0; position: relative; }
.udf-card-menu { float: right; margin-left: 0.5rem; }
.udf-vote { display: flex; flex-direction: column; align-items: center; min-width: 2.2rem; margin: 0; }
.udf-arrow { background: none; border: 0; padding: 0 0.2rem; font-size: 1rem; line-height: 1.2; color: #8a8a8a; cursor: pointer; border-radius: 4px; }
button.udf-arrow:hover { background: #eef1f5; }
.udf-up-active .udf-up { color: #d9480f; }
.udf-down-active .udf-down { color: #3b5bdb; }
.udf-up-active .udf-score { color: #d9480f; }
.udf-down-active .udf-score { color: #3b5bdb; }
.udf-vote-disabled .udf-arrow { cursor: default; opacity: 0.4; }
.udf-score { font-weight: 700; font-size: 0.9rem; color: #333; min-width: 1.2rem; text-align: center; }
.udf-new { display: inline-block; font-size: 0.75rem; font-weight: 700; padding: 0.05rem 0.5rem; border-radius: 999px; background: #fff0e0; color: #c2410c; }
.udf-card.udf-unread { border-left: 4px solid #f08c00; }
.udf-chip-small { font-size: 0.8rem; padding: 0.1rem 0.6rem; }
@media (max-width: 600px) { .udf-card { padding: 0.6rem 0.7rem; } .udf-card-voted { gap: 0.5rem; } }
</style>
CSS;
    }
}
