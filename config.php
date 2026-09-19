<?php
$db_sqlite = 'wordhole01.sqlite';

// Results that existed before groups were introduced are assigned to this group when the
// database is migrated. Put the same code in cell A2 (and the name in A3) of the workbook
// that holds those results so that re-uploading it updates this group.
$legacy_group_code = 'PAS01';
$legacy_group_name = 'The Original Wordholers';
