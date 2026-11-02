<?php

declare(strict_types=1);

use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Component\Modal\Interruptive;

/**
 * GUI class of the Udon Forum repository object.
 *
 * Reddit-like forum: a feed of posts (cards), a detail view per post with
 * nested comments (up to ilUdfComment::MAX_DEPTH levels).
 *
 * Permissions:
 * - read:  read the forum, write posts and comments, edit/delete own ones
 * - write: settings, visibility, delete posts and comments of others
 *
 * @ilCtrl_isCalledBy ilObjUdonForumGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls      ilObjUdonForumGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 */
class ilObjUdonForumGUI extends ilObjectPluginGUI
{
    private const TAB_CONTENT = 'content';
    private const TAB_PROPERTIES = 'properties';

    private const PARAM_POST = 'post_id';
    private const PARAM_COMMENT = 'comment_id';
    private const PARAM_PARENT = 'parent_id';
    private const PARAM_PAGE = 'page';
    private const POST_FIELD_BODY = 'udf_body';

    private const POSTS_PER_PAGE = 25;
    private const PREVIEW_LENGTH = 300;

    /** Commands that need write permission */
    private const WRITE_COMMANDS = [
        'editProperties',
        'updateProperties',
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
        'saveComment',
        'editComment',
        'updateComment',
        'deleteComment',
    ];

    private ilUdfRepository $udf_repo;
    private Refinery $udf_refinery;
    private ArrayBasedRequestWrapper $udf_query;
    private ArrayBasedRequestWrapper $udf_post;
    private ilObjUser $udf_user;

    /** @var array<int, string> user id => escaped name */
    private array $name_cache = [];
    /** @var array<int, bool> user id => has write permission */
    private array $lecturer_cache = [];

    protected function afterConstructor(): void
    {
        global $DIC;

        $this->udf_repo = new ilUdfRepository($DIC->database());
        $this->udf_refinery = $DIC->refinery();
        $this->udf_query = $DIC->http()->wrapper()->query();
        $this->udf_post = $DIC->http()->wrapper()->post();
        $this->udf_user = $DIC->user();
    }

    final public function getType(): string
    {
        return ilUdonForumPlugin::ID;
    }

    public function getAfterCreationCmd(): string
    {
        return 'editProperties';
    }

    public function getStandardCmd(): string
    {
        return 'showContent';
    }

    public function performCommand(string $cmd): void
    {
        if (in_array($cmd, self::WRITE_COMMANDS, true)) {
            $this->checkPermission('write');
            $this->$cmd();
            return;
        }

        $this->checkPermission('read');
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

        if ($this->canParticipate()) {
            $this->toolbar->addComponent($this->ui_factory->button()->primary(
                $this->txt('new_post'),
                $this->ctrl->getLinkTarget($this, 'createPost')
            ));
        }

        $page = $this->getRequestedInt($this->udf_query, self::PARAM_PAGE);
        $posts = $this->udf_repo->getPostsForObject(
            $this->object->getId(),
            self::POSTS_PER_PAGE + 1,
            $page * self::POSTS_PER_PAGE
        );
        $has_more = count($posts) > self::POSTS_PER_PAGE;
        $posts = array_slice($posts, 0, self::POSTS_PER_PAGE);

        if ($posts === []) {
            $this->tpl->setOnScreenMessage('info', $this->txt('no_posts'));
            return;
        }

        $counts = $this->udf_repo->countCommentsForPosts(
            array_map(static fn(ilUdfPost $p): int => $p->getId(), $posts)
        );

        $html = $this->getStyles() . '<div class="udf">';
        $html .= '<h2 class="udf-heading">' . $this->txt('latest_posts') . '</h2>';
        foreach ($posts as $post) {
            $html .= $this->renderPostCard($post, $counts[$post->getId()] ?? 0);
        }
        $html .= $this->renderPager($page, $has_more);
        $html .= '</div>';

        $this->tpl->setContent($html);
    }

    private function renderPostCard(ilUdfPost $post, int $num_comments): string
    {
        $link = $this->linkToPost('showPost', $post);

        $preview = $post->getBody();
        if (mb_strlen($preview) > self::PREVIEW_LENGTH) {
            $preview = rtrim(mb_substr($preview, 0, self::PREVIEW_LENGTH)) . ' …';
        }

        return '<article class="udf-card">'
            . '<h3 class="udf-title"><a href="' . $this->esc($link) . '">' . $this->esc($post->getTitle()) . '</a></h3>'
            . '<div class="udf-meta">' . $this->renderMeta($post->getUserId(), $post->getCreated(), $post->isEdited()) . '</div>'
            . ($preview !== '' ? '<div class="udf-preview">' . $this->esc($preview) . '</div>' : '')
            . '<div class="udf-actions"><a href="' . $this->esc($link) . '#udf-comments">'
            . $this->esc($this->formatCommentCount($num_comments)) . '</a></div>'
            . '</article>';
    }

    private function renderPager(int $page, bool $has_more): string
    {
        if ($page === 0 && !$has_more) {
            return '';
        }

        $buttons = [];
        if ($page > 0) {
            $buttons[] = $this->ui_factory->button()->standard($this->txt('newer_posts'), $this->linkToPage($page - 1));
        }
        if ($has_more) {
            $buttons[] = $this->ui_factory->button()->standard($this->txt('older_posts'), $this->linkToPage($page + 1));
        }

        return '<div class="udf-pager">' . $this->ui_renderer->render($buttons) . '</div>';
    }

    private function linkToPage(int $page): string
    {
        $this->ctrl->setParameter($this, self::PARAM_PAGE, $page);
        $link = $this->ctrl->getLinkTarget($this, 'showContent');
        $this->ctrl->clearParameterByClass(self::class, self::PARAM_PAGE);
        return $link;
    }

    // ------------------------------------------------------------------
    // Post detail view with comments
    // ------------------------------------------------------------------

    protected function showPost(): void
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

        // the post itself
        $actions = [];
        if ($this->isAuthor($post->getUserId())) {
            $actions[] = $this->renderLink($this->txt('edit'), $this->linkToPost('editPost', $post));
        }
        if ($this->isAuthor($post->getUserId()) || $this->isModerator()) {
            $modal = $this->buildConfirmModal(
                $this->txt('confirm_delete_post'),
                $this->linkToPost('deletePost', $post, true),
                $post->getId(),
                $post->getTitle(),
                $this->lng->txt('delete')
            );
            $modals[] = $modal;
            $actions[] = $this->ui_renderer->render(
                $this->ui_factory->button()->shy($this->lng->txt('delete'), '')->withOnClick($modal->getShowSignal())
            );
        }

        $html .= '<article class="udf-card udf-card-full">'
            . '<h2 class="udf-title">' . $this->esc($post->getTitle()) . '</h2>'
            . '<div class="udf-meta">' . $this->renderMeta($post->getUserId(), $post->getCreated(), $post->isEdited()) . '</div>'
            . '<div class="udf-body">' . $this->formatText($post->getBody()) . '</div>'
            . ($actions !== [] ? '<div class="udf-actions">' . implode(' · ', $actions) . '</div>' : '')
            . '</article>';

        // comments
        $comments = $this->udf_repo->getCommentsForPost($post->getId());
        $children = [];
        $visible = 0;
        foreach ($comments as $comment) {
            $children[$comment->getParentId()][] = $comment;
            if (!$comment->isDeleted()) {
                $visible++;
            }
        }

        $html .= '<section class="udf-comments" id="udf-comments">';
        $html .= '<h3 class="udf-heading">' . $this->esc($this->formatCommentCount($visible)) . '</h3>';

        if ($this->canParticipate()) {
            $html .= $this->renderCommentForm($post, 0, $this->txt('write_comment'), $this->txt('send_comment'));
        }

        if ($comments === []) {
            $html .= '<p class="udf-empty">' . $this->txt('no_comments') . '</p>';
        } else {
            $html .= $this->renderCommentTree($post, 0, $children, $modals);
        }

        $html .= '</section></div>';

        $this->tpl->setContent($html . $this->ui_renderer->render($modals));
    }

    /**
     * @param array<int, ilUdfComment[]> $children parent id => comments
     * @param Interruptive[]             $modals   collects the delete overlays
     */
    private function renderCommentTree(ilUdfPost $post, int $parent_id, array $children, array &$modals): string
    {
        if (!isset($children[$parent_id])) {
            return '';
        }

        $html = '';
        foreach ($children[$parent_id] as $comment) {
            $html .= '<details class="udf-comment" open id="udf-c' . $comment->getId() . '">';
            $html .= '<summary class="udf-meta">'
                . ($comment->isDeleted()
                    ? '<span class="udf-deleted">' . $this->txt('deleted_comment') . '</span>'
                    : $this->renderMeta($comment->getUserId(), $comment->getCreated(), $comment->isEdited()))
                . '</summary>';

            if (!$comment->isDeleted()) {
                $html .= '<div class="udf-body">' . $this->formatText($comment->getBody()) . '</div>';
                $html .= $this->renderCommentActions($post, $comment, $modals);
            }

            $html .= $this->renderCommentTree($post, $comment->getId(), $children, $modals);
            $html .= '</details>';
        }
        return $html;
    }

    /**
     * @param Interruptive[] $modals
     */
    private function renderCommentActions(ilUdfPost $post, ilUdfComment $comment, array &$modals): string
    {
        $actions = [];
        $reply_form = '';

        if ($comment->canHaveReplies() && $this->canParticipate()) {
            $reply_form = '<details class="udf-reply"><summary>' . $this->txt('reply') . '</summary>'
                . $this->renderCommentForm($post, $comment->getId(), $this->txt('write_reply'), $this->txt('send_reply'))
                . '</details>';
        }
        if ($this->isAuthor($comment->getUserId())) {
            $actions[] = $this->renderLink($this->txt('edit'), $this->linkToComment('editComment', $post, $comment));
        }
        if ($this->isAuthor($comment->getUserId()) || $this->isModerator()) {
            $preview = mb_strlen($comment->getBody()) > 80
                ? mb_substr($comment->getBody(), 0, 80) . ' …'
                : $comment->getBody();
            $modal = $this->buildConfirmModal(
                $this->txt('confirm_delete_comment'),
                $this->linkToComment('deleteComment', $post, $comment, true),
                $comment->getId(),
                $preview,
                $this->lng->txt('delete')
            );
            $modals[] = $modal;
            $actions[] = $this->ui_renderer->render(
                $this->ui_factory->button()->shy($this->lng->txt('delete'), '')->withOnClick($modal->getShowSignal())
            );
        }

        if ($reply_form === '' && $actions === []) {
            return '';
        }

        return '<div class="udf-actions">' . $reply_form
            . ($actions !== [] ? '<span class="udf-actions-right">' . implode(' · ', $actions) . '</span>' : '')
            . '</div>';
    }

    private function renderCommentForm(ilUdfPost $post, int $parent_id, string $placeholder, string $button): string
    {
        $this->ctrl->setParameter($this, self::PARAM_POST, $post->getId());
        $this->ctrl->setParameter($this, self::PARAM_PARENT, $parent_id);
        $action = $this->ctrl->getFormAction($this, 'saveComment');
        $this->ctrl->clearParameterByClass(self::class, self::PARAM_POST);
        $this->ctrl->clearParameterByClass(self::class, self::PARAM_PARENT);

        return '<form class="udf-form" method="post" action="' . $this->esc($action) . '">'
            . '<textarea class="form-control" name="' . self::POST_FIELD_BODY . '" rows="3" required placeholder="'
            . $this->esc($placeholder) . '"></textarea>'
            . '<button type="submit" class="btn btn-default" name="cmd[saveComment]">' . $this->esc($button) . '</button>'
            . '</form>';
    }

    // ------------------------------------------------------------------
    // Create / edit / delete posts
    // ------------------------------------------------------------------

    protected function createPost(?ilPropertyFormGUI $form = null): void
    {
        $this->requireParticipation();
        $this->tabs->activateTab(self::TAB_CONTENT);
        $form ??= $this->initPostForm(null);
        $this->tpl->setContent($form->getHTML());
    }

    protected function savePost(): void
    {
        $this->requireParticipation();
        $form = $this->initPostForm(null);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->createPost($form);
            return;
        }

        $now = time();
        $post = new ilUdfPost();
        $post->setObjId($this->object->getId());
        $post->setUserId($this->udf_user->getId());
        $post->setTitle(trim((string) $form->getInput('title')));
        $post->setBody(trim((string) $form->getInput('body')));
        $post->setCreated($now);
        $post->setUpdated($now);
        $this->udf_repo->savePost($post);

        $this->tpl->setOnScreenMessage('success', $this->txt('post_created'), true);
        $this->redirectToPost($post);
    }

    protected function editPost(?ilPropertyFormGUI $form = null): void
    {
        $post = $this->requirePost();
        $this->requireAuthor($post->getUserId(), $post);
        $this->tabs->activateTab(self::TAB_CONTENT);

        if ($form === null) {
            $form = $this->initPostForm($post);
            $form->setValuesByArray([
                'title' => $post->getTitle(),
                'body' => $post->getBody(),
            ]);
        }
        $this->tpl->setContent($form->getHTML());
    }

    protected function updatePost(): void
    {
        $post = $this->requirePost();
        $this->requireAuthor($post->getUserId(), $post);

        $form = $this->initPostForm($post);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->editPost($form);
            return;
        }

        $post->setTitle(trim((string) $form->getInput('title')));
        $post->setBody(trim((string) $form->getInput('body')));
        $post->setUpdated(time());
        $this->udf_repo->savePost($post);

        $this->tpl->setOnScreenMessage('success', $this->txt('post_updated'), true);
        $this->redirectToPost($post);
    }

    protected function deletePost(): void
    {
        $post = $this->requirePost();
        if (!$this->isAuthor($post->getUserId()) && !$this->isModerator()) {
            $this->denyAndReturn($post);
        }

        $this->udf_repo->deletePost($post);
        $this->tpl->setOnScreenMessage('success', $this->txt('post_deleted'), true);
        $this->ctrl->redirect($this, 'showContent');
    }

    private function initPostForm(?ilUdfPost $post): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        if ($post === null) {
            $form->setTitle($this->txt('new_post'));
            $form->setFormAction($this->ctrl->getFormAction($this, 'savePost'));
        } else {
            $form->setTitle($this->txt('edit_post'));
            $this->ctrl->setParameter($this, self::PARAM_POST, $post->getId());
            $form->setFormAction($this->ctrl->getFormAction($this, 'updatePost'));
            $this->ctrl->clearParameterByClass(self::class, self::PARAM_POST);
        }

        $title = new ilTextInputGUI($this->txt('post_title'), 'title');
        $title->setRequired(true);
        $title->setMaxLength(255);
        $form->addItem($title);

        $body = new ilTextAreaInputGUI($this->txt('post_body'), 'body');
        $body->setRows(10);
        $form->addItem($body);

        if ($post === null) {
            $form->addCommandButton('savePost', $this->txt('publish'));
        } else {
            $form->addCommandButton('updatePost', $this->lng->txt('save'));
            $form->addCommandButton('showPost', $this->lng->txt('cancel'));
        }

        return $form;
    }

    // ------------------------------------------------------------------
    // Create / edit / delete comments
    // ------------------------------------------------------------------

    protected function saveComment(): void
    {
        $post = $this->requirePost();
        $this->requireParticipation($post);

        $parent_id = $this->getRequestedInt($this->udf_query, self::PARAM_PARENT);
        $depth = 1;
        if ($parent_id > 0) {
            $parent = $this->udf_repo->getComment($parent_id);
            if ($parent === null || $parent->getPostId() !== $post->getId() || !$parent->canHaveReplies()) {
                $this->tpl->setOnScreenMessage('failure', $this->txt('reply_not_possible'), true);
                $this->redirectToPost($post);
            }
            $depth = $parent->getDepth() + 1;
        }

        $body = $this->udf_post->has(self::POST_FIELD_BODY)
            ? trim($this->udf_post->retrieve(self::POST_FIELD_BODY, $this->udf_refinery->kindlyTo()->string()))
            : '';
        if ($body === '') {
            $this->tpl->setOnScreenMessage('failure', $this->txt('comment_empty'), true);
            $this->redirectToPost($post);
        }

        $now = time();
        $comment = new ilUdfComment();
        $comment->setPostId($post->getId());
        $comment->setParentId($parent_id);
        $comment->setDepth($depth);
        $comment->setUserId($this->udf_user->getId());
        $comment->setBody($body);
        $comment->setCreated($now);
        $comment->setUpdated($now);
        $this->udf_repo->saveComment($comment);

        $this->tpl->setOnScreenMessage('success', $this->txt('comment_saved'), true);
        $this->redirectToPost($post, 'udf-c' . $comment->getId());
    }

    protected function editComment(?ilPropertyFormGUI $form = null): void
    {
        $post = $this->requirePost();
        $comment = $this->requireComment($post);
        $this->requireAuthor($comment->getUserId(), $post);
        $this->tabs->activateTab(self::TAB_CONTENT);

        if ($form === null) {
            $form = $this->initCommentForm($post, $comment);
            $form->setValuesByArray(['body' => $comment->getBody()]);
        }
        $this->tpl->setContent($form->getHTML());
    }

    protected function updateComment(): void
    {
        $post = $this->requirePost();
        $comment = $this->requireComment($post);
        $this->requireAuthor($comment->getUserId(), $post);

        $form = $this->initCommentForm($post, $comment);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->editComment($form);
            return;
        }

        $comment->setBody(trim((string) $form->getInput('body')));
        $comment->setUpdated(time());
        $this->udf_repo->saveComment($comment);

        $this->tpl->setOnScreenMessage('success', $this->txt('comment_updated'), true);
        $this->redirectToPost($post, 'udf-c' . $comment->getId());
    }

    protected function deleteComment(): void
    {
        $post = $this->requirePost();
        $comment = $this->requireComment($post);
        if (!$this->isAuthor($comment->getUserId()) && !$this->isModerator()) {
            $this->denyAndReturn($post);
        }

        $this->udf_repo->deleteComment($comment);
        $this->tpl->setOnScreenMessage('success', $this->txt('comment_deleted'), true);
        $this->redirectToPost($post, 'udf-comments');
    }

    private function initCommentForm(ilUdfPost $post, ilUdfComment $comment): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->txt('edit_comment'));
        $this->ctrl->setParameter($this, self::PARAM_POST, $post->getId());
        $this->ctrl->setParameter($this, self::PARAM_COMMENT, $comment->getId());
        $form->setFormAction($this->ctrl->getFormAction($this, 'updateComment'));
        $this->ctrl->clearParameterByClass(self::class, self::PARAM_COMMENT);
        $this->ctrl->clearParameterByClass(self::class, self::PARAM_POST);

        $body = new ilTextAreaInputGUI($this->txt('comment'), 'body');
        $body->setRequired(true);
        $body->setRows(6);
        $form->addItem($body);

        $form->addCommandButton('updateComment', $this->lng->txt('save'));
        $form->addCommandButton('showPost', $this->lng->txt('cancel'));

        return $form;
    }

    // ------------------------------------------------------------------
    // Permissions
    // ------------------------------------------------------------------

    /**
     * Logged-in users with read permission can write posts and comments.
     */
    private function canParticipate(): bool
    {
        return $this->udf_user->getId() !== ANONYMOUS_USER_ID
            && $this->access->checkAccess('read', '', $this->object->getRefId());
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
        if (!$wrapper->has($key)) {
            return 0;
        }
        return max(0, $wrapper->retrieve($key, $this->udf_refinery->kindlyTo()->int()));
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

    /**
     * Comment from the request; it must belong to the given post and not be deleted.
     */
    private function requireComment(ilUdfPost $post): ilUdfComment
    {
        $id = $this->getRequestedInt($this->udf_query, self::PARAM_COMMENT);
        $comment = $id > 0 ? $this->udf_repo->getComment($id) : null;
        if ($comment === null || $comment->getPostId() !== $post->getId() || $comment->isDeleted()) {
            $this->tpl->setOnScreenMessage('failure', $this->txt('comment_not_found'), true);
            $this->redirectToPost($post);
        }
        return $comment;
    }

    private function linkToPost(string $cmd, ilUdfPost $post, bool $form_action = false): string
    {
        $this->ctrl->setParameter($this, self::PARAM_POST, $post->getId());
        $link = $form_action ? $this->ctrl->getFormAction($this, $cmd) : $this->ctrl->getLinkTarget($this, $cmd);
        $this->ctrl->clearParameterByClass(self::class, self::PARAM_POST);
        return $link;
    }

    private function linkToComment(string $cmd, ilUdfPost $post, ilUdfComment $comment, bool $form_action = false): string
    {
        $this->ctrl->setParameter($this, self::PARAM_COMMENT, $comment->getId());
        $link = $this->linkToPost($cmd, $post, $form_action);
        $this->ctrl->clearParameterByClass(self::class, self::PARAM_COMMENT);
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

    private function renderLink(string $label, string $url): string
    {
        return '<a href="' . $this->esc($url) . '">' . $this->esc($label) . '</a>';
    }

    /**
     * Escapes the text, keeps line breaks and turns web addresses into links.
     */
    private function formatText(string $text): string
    {
        $html = $this->esc($text);
        $html = (string) preg_replace_callback(
            '~https?://[^\s<]+~u',
            static function (array $m): string {
                // punctuation at the end belongs to the sentence, not to the address
                $url = rtrim($m[0], '.,;:!?)');
                $rest = substr($m[0], strlen($url));
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $rest;
            },
            $html
        );
        return nl2br($html);
    }

    private function formatCommentCount(int $count): string
    {
        return $count === 1 ? $this->txt('comment_count_one') : sprintf($this->txt('comment_count'), $count);
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
        if (!isset($this->lecturer_cache[$user_id])) {
            $this->lecturer_cache[$user_id] = $user_id !== ANONYMOUS_USER_ID
                && $this->access->checkAccessOfUser($user_id, 'write', '', $this->object->getRefId());
        }
        return $this->lecturer_cache[$user_id];
    }

    /**
     * @return string escaped "Firstname Lastname"
     */
    private function getUserName(int $user_id): string
    {
        if (!isset($this->name_cache[$user_id])) {
            $name = ilObjUser::_lookupName($user_id);
            if (($name['login'] ?? '') === '') {
                $this->name_cache[$user_id] = $this->txt('deleted_user');
            } else {
                $this->name_cache[$user_id] = $this->esc(trim(
                    ($name['firstname'] ?? '') . ' ' . ($name['lastname'] ?? '')
                ));
            }
        }
        return $this->name_cache[$user_id];
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
.udf-heading { font-size: 1.1rem; margin: 0.5rem 0 0.75rem; }
.udf-card { border: 1px solid #d6d6d6; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 0.75rem; background: #fff; }
.udf-card:hover { border-color: #9a9a9a; }
.udf-card-full:hover { border-color: #d6d6d6; }
.udf-title { font-size: 1.1rem; font-weight: 600; margin: 0 0 0.25rem; }
.udf-card-full .udf-title { font-size: 1.4rem; }
.udf-title a { color: inherit; text-decoration: none; }
.udf-title a:hover { text-decoration: underline; }
.udf-meta { font-size: 0.85rem; color: #6f6f6f; }
.udf-author { font-weight: 600; color: #333; }
.udf-badge { display: inline-block; font-size: 0.75rem; font-weight: 600; padding: 0 0.4rem; border-radius: 4px; background: #e3ecf7; color: #2b5797; }
.udf-edited { font-style: italic; }
.udf-preview, .udf-body { margin: 0.5rem 0; line-height: 1.5; overflow-wrap: anywhere; }
.udf-actions { font-size: 0.85rem; margin-top: 0.25rem; }
.udf-actions a, .udf-actions button { font-weight: 600; }
.udf-actions-right { margin-left: 0.5rem; }
.udf-back { margin-bottom: 0.75rem; }
.udf-comments { margin-top: 1.5rem; }
.udf-empty { color: #6f6f6f; }
.udf-form { margin: 0.5rem 0 1rem; }
.udf-form textarea { width: 100%; margin-bottom: 0.5rem; }
.udf-comment { border-left: 2px solid #e0e0e0; padding: 0.25rem 0 0.25rem 0.75rem; margin: 0.5rem 0 0.5rem 0.25rem; }
.udf-comment:hover { border-left-color: #9a9a9a; }
.udf-comment > summary { cursor: pointer; list-style: none; }
.udf-comment > summary::-webkit-details-marker { display: none; }
.udf-comment:not([open]) > summary::after { content: " [+]"; }
.udf-comment .udf-body { margin: 0.25rem 0; }
.udf-deleted { color: #8a8a8a; font-style: italic; }
.udf-reply { display: inline-block; vertical-align: top; }
.udf-reply > summary { cursor: pointer; display: inline; font-weight: 600; color: #4c6586; }
.udf-reply[open] { display: block; }
.udf-pager { display: flex; gap: 0.5rem; margin-top: 1rem; }
</style>
CSS;
    }
}
