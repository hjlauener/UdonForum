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
<#5>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableColumnExists('rep_robj_xudf_data', 'accept_mode')) {
    $ilDB->addTableColumn('rep_robj_xudf_data', 'accept_mode', ['type' => 'text', 'length' => 16, 'notnull' => true, 'default' => 'author']);
}
if (!$ilDB->tableColumnExists('rep_robj_xudf_data', 'cats_init')) {
    $ilDB->addTableColumn('rep_robj_xudf_data', 'cats_init', ['type' => 'integer', 'length' => 1, 'notnull' => true, 'default' => 0]);
}
if (!$ilDB->tableColumnExists('rep_robj_xudf_post', 'cat_id')) {
    $ilDB->addTableColumn('rep_robj_xudf_post', 'cat_id', ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0]);
}
if (!$ilDB->tableColumnExists('rep_robj_xudf_post', 'accepted_id')) {
    $ilDB->addTableColumn('rep_robj_xudf_post', 'accepted_id', ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0]);
}
?>
<#6>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableExists('rep_robj_xudf_cat')) {
    $ilDB->createTable('rep_robj_xudf_cat', [
        'id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'obj_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'title' => ['type' => 'text', 'length' => 255, 'notnull' => true, 'default' => ''],
        'sort_order' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xudf_cat', ['id']);
    $ilDB->addIndex('rep_robj_xudf_cat', ['obj_id'], 'i1');
    $ilDB->createSequence('rep_robj_xudf_cat');
}
?>
<#7>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableColumnExists('rep_robj_xudf_post', 'score')) {
    $ilDB->addTableColumn('rep_robj_xudf_post', 'score', ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0]);
}
if (!$ilDB->tableColumnExists('rep_robj_xudf_post', 'hot')) {
    $ilDB->addTableColumn('rep_robj_xudf_post', 'hot', ['type' => 'float', 'notnull' => true, 'default' => 0]);
}
if (!$ilDB->tableColumnExists('rep_robj_xudf_cmt', 'score')) {
    $ilDB->addTableColumn('rep_robj_xudf_cmt', 'score', ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0]);
}
// ranking value "popular" for existing posts (score 0 => only the creation time counts)
$ilDB->manipulate('UPDATE rep_robj_xudf_post SET hot = (created - 1700000000) / 45000');
?>
<#8>
<?php
/** @var ilDBInterface $ilDB */
if (!$ilDB->tableExists('rep_robj_xudf_vote')) {
    $ilDB->createTable('rep_robj_xudf_vote', [
        'item_type' => ['type' => 'text', 'length' => 1, 'notnull' => true, 'default' => 'p'],
        'item_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'user_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'vote_val' => ['type' => 'integer', 'length' => 1, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xudf_vote', ['item_type', 'item_id', 'user_id']);
}
if (!$ilDB->tableExists('rep_robj_xudf_read')) {
    $ilDB->createTable('rep_robj_xudf_read', [
        'user_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'obj_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'post_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'read_ts' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xudf_read', ['user_id', 'obj_id', 'post_id']);
}
?>
