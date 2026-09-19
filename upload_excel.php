<?php
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");
(@include_once("./parse_workbook.php")) or die("Cannot read parse_workbook.php file<BR>");
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");

// The workbook says which group it is for (cell A2), so there is no group to choose when uploading.
// Changing an existing group needs its password (unless this browser has already unlocked it): send it as "password".
// A workbook for a group that does not exist yet creates it, which only the super admin can do (send
// "admin_password"), and "password" is then the password chosen for the new group.
require_post();
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
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    $filename = $file['tmp_name'];
    try {
        $workbook = parse_workbook($filename);
    } catch (WorkbookException $e) {
        throw $e;
    } catch (Throwable $e) {
        throw new WorkbookException("This does not look like a Wordhole Excel workbook: " . $e->getMessage());
    }

    $existing_group = db_row($db, "SELECT id, code, edit_password_hash FROM w_groups WHERE code = :code",
        array(':code' => $workbook['group_code']));
    $new_group_password_hash = null;
    if ($existing_group) {
        if (!$existing_group['edit_password_hash']) {
            json_fail("Editing is not switched on for the group \"" . $existing_group['code'] . "\" yet. "
                . "The site owner can set its password by running: php manage_groups.php set-password " . $existing_group['code'], 403);
        }
        if (!is_group_editor($db, $existing_group['id'])) {
            $login = group_login($db, $existing_group['id'], $password);
            if (!$login['ok']) {
                json_fail($login['message'], $login['status']);
            }
        }
    } else {
        // A new group: only the super admin can create one, and the password given now becomes its password
        require_super_admin($db, isset($_POST['admin_password']) ? (string)$_POST['admin_password'] : '');
        $problem = validate_new_password($password);
        if ($problem !== null) {
            throw new WorkbookException("\"" . $workbook['group_code'] . "\" is a new group, so choose a password for it. " . $problem);
        }
        $new_group_password_hash = password_hash($password, PASSWORD_DEFAULT);
    }

    $group = import_workbook_data($db, $workbook['group_code'], $workbook['group_name'], $workbook['round_data'], $new_group_password_hash);
    if ($new_group_password_hash !== null) {
        grant_group($db, $group['id']);
    }

    $return_data = array(
        'group_id' => $group['id'],
        'group_code' => $group['code'],
        'group_name' => $group['name'],
        'rounds_loaded' => count($workbook['round_data']),
        'message' => "Success",
        'is_valid' => 1
    );
} catch (WorkbookException $e) {
    json_fail($e->getMessage(), 400);
} catch (Throwable $e) {
    json_fail("The results could not be saved: " . $e->getMessage(), 500);
}
echo json_encode($return_data);
