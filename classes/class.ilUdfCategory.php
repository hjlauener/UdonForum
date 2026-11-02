<?php

declare(strict_types=1);

/**
 * Category of an Udon Forum (e.g. "General", "Questions", "Exam").
 */
class ilUdfCategory
{
    private int $id = 0;
    private int $obj_id = 0;
    private string $title = '';
    private int $sort_order = 0;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getSortOrder(): int
    {
        return $this->sort_order;
    }

    public function setSortOrder(int $sort_order): void
    {
        $this->sort_order = $sort_order;
    }
}
