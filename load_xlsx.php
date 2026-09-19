<?php
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");
(@include_once("./parse_workbook.php")) or die("Cannot read parse_workbook.php file<BR>");
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");

$reload_main = 0;
if (array_key_exists('reload', $_GET)) {
    $reload_main = 1;
}

// Load the results in the workbook stored with the site. The workbook says which group they are for (cell A2),
// and this browser must already be logged in to that group.
$filename = "./wordhole_records.xlsx";
try {
    $workbook = parse_workbook($filename);
    $existing_group = db_row($db, "SELECT id FROM w_groups WHERE code = :code", array(':code' => $workbook['group_code']));
    if (!$existing_group || !is_group_editor($db, $existing_group['id'])) {
        die("Log in to the group \"" . htmlspecialchars($workbook['group_code']) . "\" on the main page first, then try again.");
    }
    $group = import_workbook_data($db, $workbook['group_code'], $workbook['group_name'], $workbook['round_data']);
} catch (Throwable $e) {
    die("Could not load $filename: " . htmlspecialchars($e->getMessage()));
}
?>
<script>
    let reload_main = <?php echo $reload_main; ?>;

    if(reload_main > 0) {
        window.location.href = 'index.php?g=' + encodeURIComponent(<?php echo json_encode($group['code']); ?>)
    }
</script>

