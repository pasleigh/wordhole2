<?php
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");
(@include_once("./parse_workbook.php")) or die("Cannot read parse_workbook.php file<BR>");

$reload_main = 0;
if (array_key_exists('reload', $_GET)) {
    $reload_main = 1;
}

// Load the results in the workbook stored with the site. The workbook says which group they are for (cell A2).
$filename = "./wordhole_records.xlsx";
try {
    $workbook = parse_workbook($filename);
    $group = import_workbook_data($db, $workbook['group_code'], $workbook['group_name'], $workbook['round_data']);
} catch (Throwable $e) {
    die("Could not load $filename: " . htmlspecialchars($e->getMessage()));
}

$message = "Success";
$is_valid = 1;

$return_data = array(
    'round_data' => $workbook['round_data'],
    'group_id' => $group['id'],
    'message' => $message,
    'file_info' => $workbook['file_info'],
    'is_valid' => $is_valid
);

//echo json_encode($return_data);
?>
<script>
    let reload_main = <?php echo $reload_main; ?>;

    if(reload_main > 0) {
        window.location.href = 'index.php?e=edit&g=' + encodeURIComponent(<?php echo json_encode($group['code']); ?>)
    }
</script>

