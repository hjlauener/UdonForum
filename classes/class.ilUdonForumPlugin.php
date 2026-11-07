<?php

declare(strict_types=1);

/**
 * Repository object plugin "Udon Forum" (type xudf) for ILIAS 11.
 */
class ilUdonForumPlugin extends ilRepositoryObjectPlugin
{
    public const ID = 'xudf';
    public const NAME = 'UdonForum';

    /** RBAC operation "Statistik einsehen" (label from the language file) */
    public const OP_STATISTICS = 'udf_statistics';
    /** RBAC operation "write" (whoever may edit a forum also gets the statistics once) */
    private const OP_ID_WRITE = 4;

    public function getPluginName(): string
    {
        return self::NAME;
    }

    /**
     * Icon of the object type. ILIAS always loads it under the same name
     * (icon_xudf.svg), so browsers keep showing an old version from their
     * cache. The modification time as version parameter makes them load the
     * current icon whenever it changes.
     */
    public static function _getIcon(string $a_type): string
    {
        $path = parent::_getIcon($a_type);
        $file = dirname(__DIR__) . '/templates/images/icon_' . $a_type . '.svg';
        if (!is_file($file) || str_contains($path, '?')) {
            return $path;
        }
        return $path . '?v=' . filemtime($file);
    }

    protected function uninstallCustom(): void
    {
        global $DIC;
        $db = $DIC->database();

        foreach (ilUdfRepository::TABLES as $table) {
            if ($db->tableExists($table)) {
                $db->dropTable($table, false);
            }
            if ($db->sequenceExists($table)) {
                $db->dropSequence($table);
            }
        }
    }

    /**
     * An Udon Forum can only be created directly in courses and groups.
     *
     * @return string[]
     */
    public function getParentTypes(): array
    {
        return ['crs', 'grp'];
    }

    public function allowCopy(): bool
    {
        return false;
    }

    protected function afterUpdate(): void
    {
        $this->ensureStatisticsPermission();
    }

    protected function afterActivation(): void
    {
        $this->ensureStatisticsPermission();
    }

    /**
     * Creates the permission "Statistik einsehen" (RBAC operation) and assigns
     * it to the object type. When the permission is new, all roles that may
     * edit an Udon Forum (write) get it too, in existing forums and in the role
     * templates – so that course admins can use the statistics right away.
     * Safe to call several times.
     */
    private function ensureStatisticsPermission(): void
    {
        $db = $this->db;

        // object type (created by ILIAS on activation)
        $set = $db->query(
            'SELECT obj_id FROM object_data WHERE type = ' . $db->quote('typ', 'text') .
            ' AND title = ' . $db->quote(self::ID, 'text')
        );
        $rec = $db->fetchAssoc($set);
        if ($rec === null) {
            return; // not activated yet: afterActivation() will do it
        }
        $type_id = (int) $rec['obj_id'];

        // operation
        $set = $db->query(
            'SELECT ops_id FROM rbac_operations WHERE operation = ' . $db->quote(self::OP_STATISTICS, 'text')
        );
        $rec = $db->fetchAssoc($set);
        if ($rec !== null) {
            $ops_id = (int) $rec['ops_id'];
        } else {
            $ops_id = $db->nextId('rbac_operations');
            $db->insert('rbac_operations', [
                'ops_id' => ['integer', $ops_id],
                'operation' => ['text', self::OP_STATISTICS],
                'description' => ['text', 'Udon Forum: view statistics'],
                'class' => ['text', 'object'],
                'op_order' => ['integer', 8500],
            ]);
        }

        // assignment to the object type
        $set = $db->query(
            'SELECT ops_id FROM rbac_ta WHERE typ_id = ' . $db->quote($type_id, 'integer') .
            ' AND ops_id = ' . $db->quote($ops_id, 'integer')
        );
        if ($db->fetchAssoc($set) !== null) {
            return; // already done before
        }
        $db->insert('rbac_ta', [
            'typ_id' => ['integer', $type_id],
            'ops_id' => ['integer', $ops_id],
        ]);

        $this->grantToRolesWithWrite($ops_id);
    }

    /**
     * Gives the new operation to every role that has "write" on an Udon Forum
     * (existing objects and role templates), like ILIAS does when it
     * introduces new permissions.
     */
    private function grantToRolesWithWrite(int $ops_id): void
    {
        $db = $this->db;

        // existing forums (rbac_pa stores the operations as serialized array)
        $set = $db->query(
            'SELECT rpa.rol_id, rpa.ref_id, rpa.ops_id FROM rbac_pa rpa' .
            ' JOIN object_reference ref ON ref.ref_id = rpa.ref_id' .
            ' JOIN object_data od ON od.obj_id = ref.obj_id AND od.type = ' . $db->quote(self::ID, 'text')
        );
        $rows = [];
        while ($rec = $db->fetchAssoc($set)) {
            $rows[] = $rec;
        }
        foreach ($rows as $rec) {
            $ops = unserialize((string) $rec['ops_id'], ['allowed_classes' => false]);
            if (!is_array($ops)) {
                continue;
            }
            $ops = array_map('intval', $ops);
            if (in_array(self::OP_ID_WRITE, $ops, true) && !in_array($ops_id, $ops, true)) {
                $ops[] = $ops_id;
                $db->manipulate(
                    'UPDATE rbac_pa SET ops_id = ' . $db->quote(serialize($ops), 'text') .
                    ' WHERE rol_id = ' . $db->quote((int) $rec['rol_id'], 'integer') .
                    ' AND ref_id = ' . $db->quote((int) $rec['ref_id'], 'integer')
                );
            }
        }

        // role templates (e.g. course admin) for new forums
        $set = $db->query(
            'SELECT rol_id, parent FROM rbac_templates WHERE type = ' . $db->quote(self::ID, 'text') .
            ' AND ops_id = ' . $db->quote(self::OP_ID_WRITE, 'integer')
        );
        $templates = [];
        while ($rec = $db->fetchAssoc($set)) {
            $templates[] = [(int) $rec['rol_id'], (int) $rec['parent']];
        }
        foreach ($templates as [$rol_id, $parent]) {
            $check = $db->query(
                'SELECT ops_id FROM rbac_templates WHERE rol_id = ' . $db->quote($rol_id, 'integer') .
                ' AND parent = ' . $db->quote($parent, 'integer') .
                ' AND type = ' . $db->quote(self::ID, 'text') .
                ' AND ops_id = ' . $db->quote($ops_id, 'integer')
            );
            if ($db->fetchAssoc($check) === null) {
                $db->insert('rbac_templates', [
                    'rol_id' => ['integer', $rol_id],
                    'type' => ['text', self::ID],
                    'ops_id' => ['integer', $ops_id],
                    'parent' => ['integer', $parent],
                ]);
            }
        }
    }
}
