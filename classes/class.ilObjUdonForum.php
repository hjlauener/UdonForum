<?php

declare(strict_types=1);

/**
 * Application class of the Udon Forum repository object.
 *
 * The object itself only stores the visibility (online flag) and whether the
 * default categories were created. Posts are handled by ilUdfRepository.
 */
class ilObjUdonForum extends ilObjectPlugin
{
    protected ilDBInterface $udf_db;
    protected bool $online = false;
    protected bool $categories_initialized = false;

    public function __construct(int $a_ref_id = 0)
    {
        global $DIC;
        $this->udf_db = $DIC->database();
        parent::__construct($a_ref_id);
    }

    final protected function initType(): void
    {
        $this->setType(ilUdonForumPlugin::ID);
    }

    protected function doCreate(bool $clone_mode = false): void
    {
        $this->udf_db->insert(ilUdfRepository::TABLE_DATA, ['id' => ['integer', $this->getId()]] + $this->getDataFields());
    }

    protected function doRead(): void
    {
        $set = $this->udf_db->query(
            'SELECT * FROM ' . ilUdfRepository::TABLE_DATA .
            ' WHERE id = ' . $this->udf_db->quote($this->getId(), 'integer')
        );
        if ($rec = $this->udf_db->fetchAssoc($set)) {
            $this->online = (bool) $rec['is_online'];
            $this->categories_initialized = (bool) $rec['cats_init'];
        }
    }

    protected function doUpdate(): void
    {
        $this->udf_db->replace(
            ilUdfRepository::TABLE_DATA,
            ['id' => ['integer', $this->getId()]],
            $this->getDataFields()
        );
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    private function getDataFields(): array
    {
        return [
            'is_online' => ['integer', (int) $this->online],
            'cats_init' => ['integer', (int) $this->categories_initialized],
        ];
    }

    protected function doDelete(): void
    {
        (new ilUdfRepository($this->udf_db))->deleteAllForObject($this->getId());
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): void
    {
        $this->online = $online;
    }



    /**
     * The default categories are created once; afterwards the lecturer
     * manages them (and may also delete them).
     */
    public function areCategoriesInitialized(): bool
    {
        return $this->categories_initialized;
    }

    public function setCategoriesInitialized(bool $initialized): void
    {
        $this->categories_initialized = $initialized;
    }
}
