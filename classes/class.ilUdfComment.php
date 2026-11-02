<?php

declare(strict_types=1);

/**
 * A comment on a post. Comments can be nested up to MAX_DEPTH levels.
 */
class ilUdfComment
{
    public const MAX_DEPTH = 4;

    private int $id = 0;
    private int $post_id = 0;
    private int $parent_id = 0;
    private int $depth = 1;
    private int $user_id = 0;
    private string $body = '';
    private bool $deleted = false;
    private int $created = 0;
    private int $updated = 0;
    private int $score = 0;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getPostId(): int
    {
        return $this->post_id;
    }

    public function setPostId(int $post_id): void
    {
        $this->post_id = $post_id;
    }

    /**
     * 0 = direct comment on the post
     */
    public function getParentId(): int
    {
        return $this->parent_id;
    }

    public function setParentId(int $parent_id): void
    {
        $this->parent_id = $parent_id;
    }

    /**
     * 1 = direct comment on the post, MAX_DEPTH = deepest level
     */
    public function getDepth(): int
    {
        return $this->depth;
    }

    public function setDepth(int $depth): void
    {
        $this->depth = $depth;
    }

    public function canHaveReplies(): bool
    {
        return !$this->deleted && $this->depth < self::MAX_DEPTH;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    /**
     * Deleted comments that still have replies stay as placeholder
     * ("[deleted]") so that the discussion remains understandable.
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): void
    {
        $this->deleted = $deleted;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function setCreated(int $created): void
    {
        $this->created = $created;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function setUpdated(int $updated): void
    {
        $this->updated = $updated;
    }

    public function isEdited(): bool
    {
        return $this->updated > $this->created;
    }

    /**
     * Sum of all votes (+1 / -1)
     */
    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }
}
