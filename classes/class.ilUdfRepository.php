<?php

declare(strict_types=1);

/**
 * Database access for the Udon Forum: object settings, posts and comments.
 */
class ilUdfRepository
{
    public const TABLE_DATA = 'rep_robj_xudf_data';
    public const TABLE_POST = 'rep_robj_xudf_post';
    public const TABLE_COMMENT = 'rep_robj_xudf_cmt';
    public const TABLE_CATEGORY = 'rep_robj_xudf_cat';

    public const TABLES = [self::TABLE_DATA, self::TABLE_POST, self::TABLE_COMMENT, self::TABLE_CATEGORY];

    private ilDBInterface $db;

    public function __construct(ilDBInterface $db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------
    // Posts
    // ------------------------------------------------------------------

    /**
     * Posts of a forum, newest first.
     *
     * @param int  $cat_id           only posts of this category (0 = all)
     * @param bool $only_unanswered  only posts without accepted answer
     * @return ilUdfPost[]
     */
    public function getPostsForObject(
        int $obj_id,
        int $limit,
        int $offset,
        int $cat_id = 0,
        bool $only_unanswered = false
    ): array {
        $where = ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer');
        if ($cat_id > 0) {
            $where .= ' AND cat_id = ' . $this->db->quote($cat_id, 'integer');
        }
        if ($only_unanswered) {
            $where .= ' AND accepted_id = ' . $this->db->quote(0, 'integer');
        }

        $this->db->setLimit($limit, $offset);
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_POST . $where .
            ' ORDER BY created DESC, id DESC'
        );
        $list = [];
        while ($rec = $this->db->fetchAssoc($set)) {
            $list[] = $this->buildPost($rec);
        }
        return $list;
    }

    public function getPost(int $id): ?ilUdfPost
    {
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_POST .
            ' WHERE id = ' . $this->db->quote($id, 'integer')
        );
        $rec = $this->db->fetchAssoc($set);
        return $rec ? $this->buildPost($rec) : null;
    }

    public function savePost(ilUdfPost $post): void
    {
        $fields = [
            'obj_id' => ['integer', $post->getObjId()],
            'user_id' => ['integer', $post->getUserId()],
            'title' => ['text', $post->getTitle()],
            'body' => ['clob', $post->getBody()],
            'created' => ['integer', $post->getCreated()],
            'updated' => ['integer', $post->getUpdated()],
            'cat_id' => ['integer', $post->getCatId()],
            'accepted_id' => ['integer', $post->getAcceptedId()],
        ];

        if ($post->getId() === 0) {
            $post->setId($this->db->nextId(self::TABLE_POST));
            $fields['id'] = ['integer', $post->getId()];
            $this->db->insert(self::TABLE_POST, $fields);
            return;
        }

        $this->db->update(self::TABLE_POST, $fields, ['id' => ['integer', $post->getId()]]);
    }

    /**
     * Deletes a post including all of its comments.
     */
    public function deletePost(ilUdfPost $post): void
    {
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_COMMENT .
            ' WHERE post_id = ' . $this->db->quote($post->getId(), 'integer')
        );
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_POST .
            ' WHERE id = ' . $this->db->quote($post->getId(), 'integer')
        );
    }

    /**
     * Deletes all posts, comments and settings of a forum object.
     */
    public function deleteAllForObject(int $obj_id): void
    {
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_COMMENT . ' WHERE post_id IN (' .
            'SELECT id FROM ' . self::TABLE_POST . ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer') . ')'
        );
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_POST . ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer')
        );
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_CATEGORY . ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer')
        );
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_DATA . ' WHERE id = ' . $this->db->quote($obj_id, 'integer')
        );
    }

    // ------------------------------------------------------------------
    // Comments
    // ------------------------------------------------------------------

    /**
     * All comments of a post, oldest first.
     *
     * @return ilUdfComment[]
     */
    public function getCommentsForPost(int $post_id): array
    {
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_COMMENT .
            ' WHERE post_id = ' . $this->db->quote($post_id, 'integer') .
            ' ORDER BY created, id'
        );
        $list = [];
        while ($rec = $this->db->fetchAssoc($set)) {
            $list[] = $this->buildComment($rec);
        }
        return $list;
    }

    public function getComment(int $id): ?ilUdfComment
    {
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_COMMENT .
            ' WHERE id = ' . $this->db->quote($id, 'integer')
        );
        $rec = $this->db->fetchAssoc($set);
        return $rec ? $this->buildComment($rec) : null;
    }

    /**
     * Number of visible (not deleted) comments per post.
     *
     * @param int[] $post_ids
     * @return array<int, int> post id => number of comments
     */
    public function countCommentsForPosts(array $post_ids): array
    {
        if ($post_ids === []) {
            return [];
        }
        $set = $this->db->query(
            'SELECT post_id, COUNT(*) cnt FROM ' . self::TABLE_COMMENT .
            ' WHERE ' . $this->db->in('post_id', $post_ids, false, 'integer') .
            ' AND is_deleted = ' . $this->db->quote(0, 'integer') .
            ' GROUP BY post_id'
        );
        $counts = [];
        while ($rec = $this->db->fetchAssoc($set)) {
            $counts[(int) $rec['post_id']] = (int) $rec['cnt'];
        }
        return $counts;
    }

    public function saveComment(ilUdfComment $comment): void
    {
        $fields = [
            'post_id' => ['integer', $comment->getPostId()],
            'parent_id' => ['integer', $comment->getParentId()],
            'depth' => ['integer', $comment->getDepth()],
            'user_id' => ['integer', $comment->getUserId()],
            'body' => ['clob', $comment->getBody()],
            'is_deleted' => ['integer', (int) $comment->isDeleted()],
            'created' => ['integer', $comment->getCreated()],
            'updated' => ['integer', $comment->getUpdated()],
        ];

        if ($comment->getId() === 0) {
            $comment->setId($this->db->nextId(self::TABLE_COMMENT));
            $fields['id'] = ['integer', $comment->getId()];
            $this->db->insert(self::TABLE_COMMENT, $fields);
            return;
        }

        $this->db->update(self::TABLE_COMMENT, $fields, ['id' => ['integer', $comment->getId()]]);
    }

    /**
     * Deletes a comment. If it still has replies, it is only marked as
     * deleted (its text is removed) so that the replies keep their context.
     * Placeholders above it that no longer have any replies are removed too.
     */
    public function deleteComment(ilUdfComment $comment): void
    {
        // a deleted comment can no longer be the accepted answer
        $this->db->manipulate(
            'UPDATE ' . self::TABLE_POST . ' SET accepted_id = ' . $this->db->quote(0, 'integer') .
            ' WHERE id = ' . $this->db->quote($comment->getPostId(), 'integer') .
            ' AND accepted_id = ' . $this->db->quote($comment->getId(), 'integer')
        );

        if ($this->hasReplies($comment->getId())) {
            $comment->setDeleted(true);
            $comment->setBody('');
            $this->saveComment($comment);
            return;
        }

        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_COMMENT .
            ' WHERE id = ' . $this->db->quote($comment->getId(), 'integer')
        );

        $parent = $comment->getParentId() > 0 ? $this->getComment($comment->getParentId()) : null;
        if ($parent !== null && $parent->isDeleted() && !$this->hasReplies($parent->getId())) {
            $this->deleteComment($parent);
        }
    }

    private function hasReplies(int $comment_id): bool
    {
        $set = $this->db->query(
            'SELECT COUNT(*) cnt FROM ' . self::TABLE_COMMENT .
            ' WHERE parent_id = ' . $this->db->quote($comment_id, 'integer')
        );
        $rec = $this->db->fetchAssoc($set);
        return (int) ($rec['cnt'] ?? 0) > 0;
    }

    // ------------------------------------------------------------------
    // Categories
    // ------------------------------------------------------------------

    /**
     * @return ilUdfCategory[] in display order
     */
    public function getCategories(int $obj_id): array
    {
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_CATEGORY .
            ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer') .
            ' ORDER BY sort_order, id'
        );
        $list = [];
        while ($rec = $this->db->fetchAssoc($set)) {
            $list[] = $this->buildCategory($rec);
        }
        return $list;
    }

    public function getCategory(int $id): ?ilUdfCategory
    {
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_CATEGORY .
            ' WHERE id = ' . $this->db->quote($id, 'integer')
        );
        $rec = $this->db->fetchAssoc($set);
        return $rec ? $this->buildCategory($rec) : null;
    }

    public function saveCategory(ilUdfCategory $cat): void
    {
        $fields = [
            'obj_id' => ['integer', $cat->getObjId()],
            'title' => ['text', $cat->getTitle()],
            'sort_order' => ['integer', $cat->getSortOrder()],
        ];

        if ($cat->getId() === 0) {
            if ($cat->getSortOrder() === 0) {
                $cat->setSortOrder($this->getMaxSortOrder($cat->getObjId()) + 10);
                $fields['sort_order'] = ['integer', $cat->getSortOrder()];
            }
            $cat->setId($this->db->nextId(self::TABLE_CATEGORY));
            $fields['id'] = ['integer', $cat->getId()];
            $this->db->insert(self::TABLE_CATEGORY, $fields);
            return;
        }

        $this->db->update(self::TABLE_CATEGORY, $fields, ['id' => ['integer', $cat->getId()]]);
    }

    /**
     * Deletes a category. Its posts are kept, but no longer have a category.
     */
    public function deleteCategory(ilUdfCategory $cat): void
    {
        $this->db->manipulate(
            'UPDATE ' . self::TABLE_POST . ' SET cat_id = ' . $this->db->quote(0, 'integer') .
            ' WHERE cat_id = ' . $this->db->quote($cat->getId(), 'integer')
        );
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_CATEGORY .
            ' WHERE id = ' . $this->db->quote($cat->getId(), 'integer')
        );
    }

    /**
     * Moves a category one position up (-1) or down (+1).
     */
    public function moveCategory(ilUdfCategory $cat, int $direction): void
    {
        $cats = $this->getCategories($cat->getObjId());
        $ids = array_map(static fn(ilUdfCategory $c): int => $c->getId(), $cats);
        $pos = array_search($cat->getId(), $ids, true);
        $new_pos = $pos === false ? false : $pos + $direction;
        if ($new_pos === false || !isset($cats[$new_pos])) {
            return;
        }

        [$cats[$pos], $cats[$new_pos]] = [$cats[$new_pos], $cats[$pos]];
        foreach ($cats as $i => $c) {
            $c->setSortOrder(($i + 1) * 10);
            $this->saveCategory($c);
        }
    }

    /**
     * @return array<int, int> category id => number of posts
     */
    public function countPostsPerCategory(int $obj_id): array
    {
        $set = $this->db->query(
            'SELECT cat_id, COUNT(*) cnt FROM ' . self::TABLE_POST .
            ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer') .
            ' GROUP BY cat_id'
        );
        $counts = [];
        while ($rec = $this->db->fetchAssoc($set)) {
            $counts[(int) $rec['cat_id']] = (int) $rec['cnt'];
        }
        return $counts;
    }

    public function markCategoriesInitialized(int $obj_id): void
    {
        $this->db->manipulate(
            'UPDATE ' . self::TABLE_DATA . ' SET cats_init = ' . $this->db->quote(1, 'integer') .
            ' WHERE id = ' . $this->db->quote($obj_id, 'integer')
        );
    }

    private function getMaxSortOrder(int $obj_id): int
    {
        $set = $this->db->query(
            'SELECT MAX(sort_order) max_order FROM ' . self::TABLE_CATEGORY .
            ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer')
        );
        $rec = $this->db->fetchAssoc($set);
        return (int) ($rec['max_order'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Mapping
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $rec
     */
    private function buildPost(array $rec): ilUdfPost
    {
        $post = new ilUdfPost();
        $post->setId((int) $rec['id']);
        $post->setObjId((int) $rec['obj_id']);
        $post->setUserId((int) $rec['user_id']);
        $post->setTitle((string) $rec['title']);
        $post->setBody((string) ($rec['body'] ?? ''));
        $post->setCreated((int) $rec['created']);
        $post->setUpdated((int) $rec['updated']);
        $post->setCatId((int) ($rec['cat_id'] ?? 0));
        $post->setAcceptedId((int) ($rec['accepted_id'] ?? 0));
        return $post;
    }

    /**
     * @param array<string, mixed> $rec
     */
    private function buildComment(array $rec): ilUdfComment
    {
        $comment = new ilUdfComment();
        $comment->setId((int) $rec['id']);
        $comment->setPostId((int) $rec['post_id']);
        $comment->setParentId((int) $rec['parent_id']);
        $comment->setDepth((int) $rec['depth']);
        $comment->setUserId((int) $rec['user_id']);
        $comment->setBody((string) ($rec['body'] ?? ''));
        $comment->setDeleted((bool) $rec['is_deleted']);
        $comment->setCreated((int) $rec['created']);
        $comment->setUpdated((int) $rec['updated']);
        return $comment;
    }

    /**
     * @param array<string, mixed> $rec
     */
    private function buildCategory(array $rec): ilUdfCategory
    {
        $cat = new ilUdfCategory();
        $cat->setId((int) $rec['id']);
        $cat->setObjId((int) $rec['obj_id']);
        $cat->setTitle((string) $rec['title']);
        $cat->setSortOrder((int) $rec['sort_order']);
        return $cat;
    }
}
