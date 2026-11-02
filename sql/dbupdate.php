<#1>
<?php
// Initial step (no tables).
?>
<#2>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableExists('rep_robj_xudf_data')) {
    $ilDB->createTable('rep_robj_xudf_data', [
        'id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'is_online' => ['type' => 'integer', 'length' => 1, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xudf_data', ['id']);
}
?>
<#3>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableExists('rep_robj_xudf_post')) {
    $ilDB->createTable('rep_robj_xudf_post', [
        'id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'obj_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'user_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'title' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'body' => ['type' => 'clob', 'notnull' => false],
        'created' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'updated' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xudf_post', ['id']);
    $ilDB->addIndex('rep_robj_xudf_post', ['obj_id'], 'i1');
    $ilDB->createSequence('rep_robj_xudf_post');
}
?>
<#4>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableExists('rep_robj_xudf_cmt')) {
    $ilDB->createTable('rep_robj_xudf_cmt', [
        'id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'post_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'parent_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'depth' => ['type' => 'integer', 'length' => 1, 'notnull' => true, 'default' => 1],
        'user_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'body' => ['type' => 'clob', 'notnull' => false],
        'is_deleted' => ['type' => 'integer', 'length' => 1, 'notnull' => true, 'default' => 0],
        'created' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'updated' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xudf_cmt', ['id']);
    $ilDB->addIndex('rep_robj_xudf_cmt', ['post_id'], 'i1');
    $ilDB->createSequence('rep_robj_xudf_cmt');
}
?>
