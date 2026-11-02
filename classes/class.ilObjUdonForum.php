<?php

declare(strict_types=1);

/**
 * Application class of the Udon Forum repository object.
 *
 * The object itself only stores the visibility (online flag). Posts and
 * comments are handled by ilUdfRepository.
 */
class ilObjUdonForum extends ilObjectPlugin
{
    protected ilDBInterface $udf_db;
    protected bool $online = false;

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
        $this->udf_db->insert(ilUdfRepository::TABLE_DATA, [
            'id' => ['integer', $this->getId()],
            'is_online' => ['integer', (int) $this->online],
        ]);
    }

    protected function doRead(): void
    {
        $set = $this->udf_db->query(
            'SELECT is_online FROM ' . ilUdfRepository::TABLE_DATA .
            ' WHERE id = ' . $this->udf_db->quote($this->getId(), 'integer')
        );
        if ($rec = $this->udf_db->fetchAssoc($set)) {
            $this->online = (bool) $rec['is_online'];
        }
    }

    protected function doUpdate(): void
    {
        $this->udf_db->replace(
            ilUdfRepository::TABLE_DATA,
            ['id' => ['integer', $this->getId()]],
            ['is_online' => ['integer', (int) $this->online]]
        );
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
}
