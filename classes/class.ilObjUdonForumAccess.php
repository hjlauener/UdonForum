<?php

declare(strict_types=1);

/**
 * Access class of the forum repository object.
 */
class ilObjUdonForumAccess extends ilObjectPluginAccess
{
    public function _checkAccess(
        string $cmd,
        string $permission,
        int $ref_id,
        int $obj_id,
        ?int $user_id = null
    ): bool {
        // RBAC is checked by ILIAS itself; additional conditions
        // (e.g. online/offline) can be added here later.
        return true;
    }
}
