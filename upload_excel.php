<?php
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");
(@include_once("./parse_workbook.php")) or die("Cannot read parse_workbook.php file<BR>");

// The workbook says which group it is for (cell A2), so there is no group to choose when uploading
try {
    $file = null;
    foreach ($_FILES as $this_file) {
        $file = $this_file;
        break; // only do one file
    }
    if ($file === null) {
        throw new WorkbookException("No file was uploaded.");
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new WorkbookException("The file could not be uploaded (error code " . $file['error'] . ").");
    }

    $filename = $file['tmp_name'];
    try {
        $workbook = parse_workbook($filename);
    } catch (WorkbookException $e) {
        throw $e;
    } catch (Throwable $e) {
        throw new WorkbookException("This does not look like a Wordhole Excel workbook: " . $e->getMessage());
    }

    $group = import_workbook_data($db, $workbook['group_code'], $workbook['group_name'], $workbook['round_data']);

    $return_data = array(
        'round_data' => $workbook['round_data'],
        'group_id' => $group['id'],
        'group_code' => $group['code'],
        'group_name' => $group['name'],
        'message' => "Success",
        'file_info' => $workbook['file_info'],
        'file_name' => $filename,
        'is_valid' => 1
    );
} catch (WorkbookException $e) {
    json_fail($e->getMessage(), 400);
} catch (Throwable $e) {
    json_fail("The results could not be saved: " . $e->getMessage(), 500);
}
echo json_encode($return_data);
