<?php

declare(strict_types=1);

/**
 * A reply to a post (one level, no nesting, no votes).
 */
class ilUdfReply
{
    private int $id = 0;
    private int $post_id = 0;
    private int $user_id = 0;
    private string $body = '';
    private string $body_format = ilUdfPost::FORMAT_MARKDOWN;
    private int $created = 0;
    private int $updated = 0;

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

    public function getBodyFormat(): string
    {
        return $this->body_format;
    }

    public function setBodyFormat(string $body_format): void
    {
        $this->body_format = $body_format === ilUdfPost::FORMAT_TEXT ? ilUdfPost::FORMAT_TEXT : ilUdfPost::FORMAT_MARKDOWN;
    }

    public function isMarkdown(): bool
    {
        return $this->body_format === ilUdfPost::FORMAT_MARKDOWN;
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
}
