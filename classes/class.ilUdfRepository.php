<?php

declare(strict_types=1);

/**
 * Database access for the Udon Forum: object settings, posts, replies,
 * categories, votes, read status and statistics.
 *
 * Replies are stored in TABLE_COMMENT (former comments; only parent_id 0 and
 * not deleted rows are shown as replies).
 */
class ilUdfRepository
{
    public const TABLE_DATA = 'rep_robj_xudf_data';
    public const TABLE_POST = 'rep_robj_xudf_post';
    public const TABLE_COMMENT = 'rep_robj_xudf_cmt';
    public const TABLE_CATEGORY = 'rep_robj_xudf_cat';
    public const TABLE_VOTE = 'rep_robj_xudf_vote';
    public const TABLE_READ = 'rep_robj_xudf_read';

    public const TABLES = [
        self::TABLE_DATA,
        self::TABLE_POST,
        self::TABLE_COMMENT,
        self::TABLE_CATEGORY,
        self::TABLE_VOTE,
        self::TABLE_READ,
    ];

    public const ITEM_POST = 'p';
    public const ITEM_COMMENT = 'c';

    /** feed order: popular (votes and age, like Reddit "hot") */
    public const SORT_POPULAR = 'hot';
    /** feed order: newest first */
    public const SORT_NEW = 'new';
    /** feed order: highest score first */
    public const SORT_TOP = 'top';

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
     * @param int    $cat_id          only posts of this category (0 = all)
     * @param string $sort            one of the SORT_* constants
     * @param int    $since           only posts created after this time (0 = all)
     * @return ilUdfPost[]
     */
    public function getPostsForObject(
        int $obj_id,
        int $limit,
        int $offset,
        int $cat_id = 0,
        string $sort = self::SORT_NEW,
        int $since = 0
    ): array {
        $where = ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer');
        if ($cat_id > 0) {
            $where .= ' AND cat_id = ' . $this->db->quote($cat_id, 'integer');
        }
        if ($since > 0) {
            $where .= ' AND created > ' . $this->db->quote($since, 'integer');
        }

        switch ($sort) {
            case self::SORT_POPULAR:
                $order = ' ORDER BY hot DESC, id DESC';
                break;
            case self::SORT_TOP:
                $order = ' ORDER BY score DESC, created DESC, id DESC';
                break;
            default:
                $order = ' ORDER BY created DESC, id DESC';
        }

        $this->db->setLimit($limit, $offset);
        $set = $this->db->query('SELECT * FROM ' . self::TABLE_POST . $where . $order);
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
            'body_format' => ['text', $post->getBodyFormat()],
        ];

        if ($post->getId() === 0) {
            // the score is only changed by votes (see vote())
            $post->setId($this->db->nextId(self::TABLE_POST));
            $fields['id'] = ['integer', $post->getId()];
            $fields['score'] = ['integer', 0];
            $fields['hot'] = ['float', self::calculateHot(0, $post->getCreated())];
            $this->db->insert(self::TABLE_POST, $fields);
            return;
        }

        $this->db->update(self::TABLE_POST, $fields, ['id' => ['integer', $post->getId()]]);
    }

    /**
     * Deletes a post including its votes, read status and (old) comments.
     */
    public function deletePost(ilUdfPost $post): void
    {
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_VOTE .
            ' WHERE item_type = ' . $this->db->quote(self::ITEM_COMMENT, 'text') .
            ' AND item_id IN (SELECT id FROM ' . self::TABLE_COMMENT .
            ' WHERE post_id = ' . $this->db->quote($post->getId(), 'integer') . ')'
        );
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_VOTE .
            ' WHERE item_type = ' . $this->db->quote(self::ITEM_POST, 'text') .
            ' AND item_id = ' . $this->db->quote($post->getId(), 'integer')
        );
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_READ .
            ' WHERE post_id = ' . $this->db->quote($post->getId(), 'integer')
        );
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
     * Deletes all posts, categories, votes and settings of a forum object.
     */
    public function deleteAllForObject(int $obj_id): void
    {
        $posts_of_object = 'SELECT id FROM ' . self::TABLE_POST . ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer');
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_VOTE .
            ' WHERE item_type = ' . $this->db->quote(self::ITEM_COMMENT, 'text') .
            ' AND item_id IN (SELECT id FROM ' . self::TABLE_COMMENT . ' WHERE post_id IN (' . $posts_of_object . '))'
        );
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_VOTE .
            ' WHERE item_type = ' . $this->db->quote(self::ITEM_POST, 'text') .
            ' AND item_id IN (' . $posts_of_object . ')'
        );
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_READ . ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer')
        );
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
    // Replies
    // ------------------------------------------------------------------

    /**
     * Replies of a post, oldest first.
     *
     * @return ilUdfReply[]
     */
    public function getRepliesForPost(int $post_id): array
    {
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_COMMENT .
            ' WHERE post_id = ' . $this->db->quote($post_id, 'integer') .
            ' AND is_deleted = ' . $this->db->quote(0, 'integer') .
            ' ORDER BY created, id'
        );
        $list = [];
        while ($rec = $this->db->fetchAssoc($set)) {
            $list[] = $this->buildReply($rec);
        }
        return $list;
    }

    public function getReply(int $id): ?ilUdfReply
    {
        $set = $this->db->query(
            'SELECT * FROM ' . self::TABLE_COMMENT .
            ' WHERE id = ' . $this->db->quote($id, 'integer') .
            ' AND is_deleted = ' . $this->db->quote(0, 'integer')
        );
        $rec = $this->db->fetchAssoc($set);
        return $rec ? $this->buildReply($rec) : null;
    }

    public function saveReply(ilUdfReply $reply): void
    {
        $fields = [
            'post_id' => ['integer', $reply->getPostId()],
            'user_id' => ['integer', $reply->getUserId()],
            'body' => ['clob', $reply->getBody()],
            'body_format' => ['text', $reply->getBodyFormat()],
            'created' => ['integer', $reply->getCreated()],
            'updated' => ['integer', $reply->getUpdated()],
        ];

        if ($reply->getId() === 0) {
            $reply->setId($this->db->nextId(self::TABLE_COMMENT));
            $fields['id'] = ['integer', $reply->getId()];
            $fields['parent_id'] = ['integer', 0];
            $fields['depth'] = ['integer', 1];
            $fields['is_deleted'] = ['integer', 0];
            $fields['score'] = ['integer', 0];
            $this->db->insert(self::TABLE_COMMENT, $fields);
            return;
        }

        $this->db->update(self::TABLE_COMMENT, $fields, ['id' => ['integer', $reply->getId()]]);
    }

    public function deleteReply(ilUdfReply $reply): void
    {
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_COMMENT .
            ' WHERE id = ' . $this->db->quote($reply->getId(), 'integer')
        );
    }

    // ------------------------------------------------------------------
    // Statistics
    // ------------------------------------------------------------------

    /**
     * Texts of all posts and replies of a forum, for the statistics.
     *
     * @return array<int, array{posts: string[][], replies: string[][]}> user id => [[body, format], ...]
     */
    public function getTextsPerUser(int $obj_id): array
    {
        $result = [];

        $set = $this->db->query(
            'SELECT user_id, body, body_format FROM ' . self::TABLE_POST .
            ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer')
        );
        while ($rec = $this->db->fetchAssoc($set)) {
            $result[(int) $rec['user_id']]['posts'][] = [(string) ($rec['body'] ?? ''), (string) ($rec['body_format'] ?? 'md')];
        }

        $set = $this->db->query(
            'SELECT c.user_id, c.body, c.body_format FROM ' . self::TABLE_COMMENT . ' c' .
            ' JOIN ' . self::TABLE_POST . ' p ON p.id = c.post_id' .
            ' WHERE p.obj_id = ' . $this->db->quote($obj_id, 'integer') .
            ' AND c.is_deleted = ' . $this->db->quote(0, 'integer')
        );
        while ($rec = $this->db->fetchAssoc($set)) {
            $result[(int) $rec['user_id']]['replies'][] = [(string) ($rec['body'] ?? ''), (string) ($rec['body_format'] ?? 'md')];
        }

        return $result;
    }

    /**
     * Replies between people: who replied to whose post, and how often
     * (replies to one's own post do not count).
     *
     * @return array<int, array{0: int, 1: int, 2: int}> [replying user, author of the post, number]
     */
    public function getReplyInteractions(int $obj_id): array
    {
        $set = $this->db->query(
            'SELECT c.user_id replier, p.user_id author, COUNT(*) cnt FROM ' . self::TABLE_COMMENT . ' c' .
            ' JOIN ' . self::TABLE_POST . ' p ON p.id = c.post_id' .
            ' WHERE p.obj_id = ' . $this->db->quote($obj_id, 'integer') .
            ' AND c.is_deleted = ' . $this->db->quote(0, 'integer') .
            ' AND c.user_id <> p.user_id' .
            ' GROUP BY c.user_id, p.user_id'
        );
        $list = [];
        while ($rec = $this->db->fetchAssoc($set)) {
            $list[] = [(int) $rec['replier'], (int) $rec['author'], (int) $rec['cnt']];
        }
        return $list;
    }

    /**
     * Activity per category (topic) for the bubble chart: number of posts and
     * replies, and who was active (opened a post or replied) in it.
     *
     * @return array<int, array{posts: int, replies: int, users: array<int, true>}> category id (0 = none) => activity
     */
    public function getCategoryActivity(int $obj_id): array
    {
        $result = [];

        $set = $this->db->query(
            'SELECT cat_id, user_id, COUNT(*) cnt FROM ' . self::TABLE_POST .
            ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer') .
            ' GROUP BY cat_id, user_id'
        );
        while ($rec = $this->db->fetchAssoc($set)) {
            $cat = (int) $rec['cat_id'];
            $result[$cat]['posts'] = ($result[$cat]['posts'] ?? 0) + (int) $rec['cnt'];
            $result[$cat]['users'][(int) $rec['user_id']] = true;
        }

        $set = $this->db->query(
            'SELECT p.cat_id, c.user_id, COUNT(*) cnt FROM ' . self::TABLE_COMMENT . ' c' .
            ' JOIN ' . self::TABLE_POST . ' p ON p.id = c.post_id' .
            ' WHERE p.obj_id = ' . $this->db->quote($obj_id, 'integer') .
            ' AND c.is_deleted = ' . $this->db->quote(0, 'integer') .
            ' GROUP BY p.cat_id, c.user_id'
        );
        while ($rec = $this->db->fetchAssoc($set)) {
            $cat = (int) $rec['cat_id'];
            $result[$cat]['replies'] = ($result[$cat]['replies'] ?? 0) + (int) $rec['cnt'];
            $result[$cat]['users'][(int) $rec['user_id']] = true;
        }

        foreach ($result as $cat => $activity) {
            $result[$cat] = [
                'posts' => $activity['posts'] ?? 0,
                'replies' => $activity['replies'] ?? 0,
                'users' => $activity['users'] ?? [],
            ];
        }
        return $result;
    }

    // ------------------------------------------------------------------
    // Votes
    // ------------------------------------------------------------------

    /**
     * Sets the vote of a user (+1, -1 or 0 = remove) and updates the score.
     *
     * @return int the new score of the item
     */
    public function vote(string $item_type, int $item_id, int $user_id, int $vote): int
    {
        $key = [
            'item_type' => ['text', $item_type],
            'item_id' => ['integer', $item_id],
            'user_id' => ['integer', $user_id],
        ];
        if ($vote === 0) {
            $this->db->manipulate(
                'DELETE FROM ' . self::TABLE_VOTE .
                ' WHERE item_type = ' . $this->db->quote($item_type, 'text') .
                ' AND item_id = ' . $this->db->quote($item_id, 'integer') .
                ' AND user_id = ' . $this->db->quote($user_id, 'integer')
            );
        } else {
            $this->db->replace(self::TABLE_VOTE, $key, ['vote_val' => ['integer', $vote > 0 ? 1 : -1]]);
        }

        $set = $this->db->query(
            'SELECT SUM(vote_val) score FROM ' . self::TABLE_VOTE .
            ' WHERE item_type = ' . $this->db->quote($item_type, 'text') .
            ' AND item_id = ' . $this->db->quote($item_id, 'integer')
        );
        $rec = $this->db->fetchAssoc($set);
        $score = (int) ($rec['score'] ?? 0);

        if ($item_type === self::ITEM_POST) {
            $post = $this->getPost($item_id);
            $this->db->update(self::TABLE_POST, [
                'score' => ['integer', $score],
                'hot' => ['float', self::calculateHot($score, $post !== null ? $post->getCreated() : time())],
            ], ['id' => ['integer', $item_id]]);
        } else {
            $this->db->update(self::TABLE_COMMENT, [
                'score' => ['integer', $score],
            ], ['id' => ['integer', $item_id]]);
        }

        return $score;
    }

    /**
     * Votes of a user for the given items.
     *
     * @param int[] $item_ids
     * @return array<int, int> item id => +1 / -1
     */
    public function getUserVotes(string $item_type, array $item_ids, int $user_id): array
    {
        if ($item_ids === []) {
            return [];
        }
        $set = $this->db->query(
            'SELECT item_id, vote_val FROM ' . self::TABLE_VOTE .
            ' WHERE item_type = ' . $this->db->quote($item_type, 'text') .
            ' AND user_id = ' . $this->db->quote($user_id, 'integer') .
            ' AND ' . $this->db->in('item_id', $item_ids, false, 'integer')
        );
        $votes = [];
        while ($rec = $this->db->fetchAssoc($set)) {
            $votes[(int) $rec['item_id']] = (int) $rec['vote_val'];
        }
        return $votes;
    }

    /**
     * Ranking value for "popular" (same idea as Reddit "hot"): every ten
     * points count as much as being 12.5 hours newer.
     */
    public static function calculateHot(int $score, int $created): float
    {
        $order = log10(max(abs($score), 1));
        $sign = $score <=> 0;
        return round($sign * $order + ($created - 1700000000) / 45000, 7);
    }

    // ------------------------------------------------------------------
    // Read status
    // ------------------------------------------------------------------

    /**
     * Time from which on posts count as new for the user
     * (first visit or "mark all as read"); null if never visited.
     */
    public function getForumReadTime(int $user_id, int $obj_id): ?int
    {
        $times = $this->getReadTimes($user_id, $obj_id, [0]);
        return $times[0] ?? null;
    }

    /**
     * @param int[] $post_ids
     * @return array<int, int> post id => time the user last opened the post
     */
    public function getReadTimes(int $user_id, int $obj_id, array $post_ids): array
    {
        if ($post_ids === []) {
            return [];
        }
        $set = $this->db->query(
            'SELECT post_id, read_ts FROM ' . self::TABLE_READ .
            ' WHERE user_id = ' . $this->db->quote($user_id, 'integer') .
            ' AND obj_id = ' . $this->db->quote($obj_id, 'integer') .
            ' AND ' . $this->db->in('post_id', $post_ids, false, 'integer')
        );
        $times = [];
        while ($rec = $this->db->fetchAssoc($set)) {
            $times[(int) $rec['post_id']] = (int) $rec['read_ts'];
        }
        return $times;
    }

    /**
     * @param int $post_id 0 = whole forum ("mark all as read" / first visit)
     */
    public function setReadTime(int $user_id, int $obj_id, int $post_id, int $time): void
    {
        $this->db->replace(self::TABLE_READ, [
            'user_id' => ['integer', $user_id],
            'obj_id' => ['integer', $obj_id],
            'post_id' => ['integer', $post_id],
        ], [
            'read_ts' => ['integer', $time],
        ]);
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
        $post->setScore((int) ($rec['score'] ?? 0));
        $post->setBodyFormat((string) ($rec['body_format'] ?? ilUdfPost::FORMAT_MARKDOWN));
        return $post;
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

    /**
     * @param array<string, mixed> $rec
     */
    private function buildReply(array $rec): ilUdfReply
    {
        $reply = new ilUdfReply();
        $reply->setId((int) $rec['id']);
        $reply->setPostId((int) $rec['post_id']);
        $reply->setUserId((int) $rec['user_id']);
        $reply->setBody((string) ($rec['body'] ?? ''));
        $reply->setBodyFormat((string) ($rec['body_format'] ?? ilUdfPost::FORMAT_MARKDOWN));
        $reply->setCreated((int) $rec['created']);
        $reply->setUpdated((int) $rec['updated']);
        return $reply;
    }
}
