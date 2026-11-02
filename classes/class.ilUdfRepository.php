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

    public const TABLES = [self::TABLE_DATA, self::TABLE_POST, self::TABLE_COMMENT];

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
     * @return ilUdfPost[]
     */
    public function getPostsForObject(int $obj_id, int $limit, int $offset): array
    {
        $this->db->setLimit($limit, $offset);
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_POST .
            ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer') .
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
}
