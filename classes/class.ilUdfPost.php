<?php

declare(strict_types=1);

/**
 * A post (thread start) in an Udon Forum.
 */
class ilUdfPost
{
    private int $id = 0;
    private int $obj_id = 0;
    private int $user_id = 0;
    private string $title = '';
    private string $body = '';
    private int $created = 0;
    private int $updated = 0;
    private int $score = 0;
    private int $cat_id = 0;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getObjId(): int
    {
        return $this->obj_id;
    }

    public function setObjId(int $obj_id): void
    {
        $this->obj_id = $obj_id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
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
     * 0 = no category
     */
    public function getCatId(): int
    {
        return $this->cat_id;
    }

    public function setCatId(int $cat_id): void
    {
        $this->cat_id = $cat_id;
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
