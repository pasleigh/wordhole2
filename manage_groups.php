<?php
// Run from the command line in this folder by the person who looks after the site:
//   php manage_groups.php list                       show the groups and whether editing is switched on
//   php manage_groups.php set-password <code>        set or reset the password that lets people change a group's results
//   php manage_groups.php set-admin-password         set or reset the super admin password that is needed to create groups
// This is a command line tool only; it cannot be used from a web page.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
chdir(__DIR__);
(@include_once("./auth.php")) or die("Cannot read auth.php file\n");

$command = isset($argv[1]) ? $argv[1] : '';

if ($command === 'list') {
    $groups = db_rows($db, "SELECT g.code, g.name, (g.edit_password_hash IS NOT NULL) AS has_password, COUNT(i.id) AS rounds
                            FROM w_groups g LEFT JOIN w_index i ON i.group_id = g.id GROUP BY g.id ORDER BY g.name COLLATE NOCASE");
    foreach ($groups as $group) {
        echo str_pad($group['code'], 14) . str_pad($group['name'], 32) . str_pad($group['rounds'] . " rounds", 12)
            . ($group['has_password'] ? "editing on" : "NO PASSWORD (view only)") . "\n";
    }
    echo "\nSuper admin password (needed to create groups): " . (has_admin_password($db) ? "set" : "NOT SET - nobody can create groups") . "\n";
    exit(0);
}

// Ask for a password twice without showing it; returns it, or null (after saying why) if it cannot be used
function ask_new_password($prompt)
{
    echo $prompt . ", at least " . MIN_PASSWORD_LENGTH . " characters: ";
    system('stty -echo 2>/dev/null');
    $password = rtrim((string)fgets(STDIN), "\r\n");
    echo "\nType it again: ";
    $again = rtrim((string)fgets(STDIN), "\r\n");
    system('stty echo 2>/dev/null');
    echo "\n";
    if ($password !== $again) {
        fwrite(STDERR, "The two passwords are not the same. Nothing was changed.\n");
        return null;
    }
    $problem = validate_new_password($password);
    if ($problem !== null) {
        fwrite(STDERR, $problem . " Nothing was changed.\n");
        return null;
    }
    return $password;
}

if ($command === 'set-admin-password') {
    $password = ask_new_password("New super admin password");
    if ($password === null) {
        exit(1);
    }
    set_admin_password($db, $password);
    echo "Super admin password set. It is asked for whenever a group is created.\n";
    exit(0);
}

if ($command === 'set-password' && isset($argv[2])) {
    $group = db_row($db, "SELECT id, code, name FROM w_groups WHERE code = :code", array(':code' => $argv[2]));
    if (!$group) {
        fwrite(STDERR, "There is no group with the code \"" . $argv[2] . "\". Try: php manage_groups.php list\n");
        exit(1);
    }
    $password = ask_new_password("New password for " . $group['name'] . " (" . $group['code'] . ")");
    if ($password === null) {
        exit(1);
    }
    set_group_password($db, $group['id'], $password);
    echo "Password set. Anyone already logged in to this group will need to log in again.\n";
    exit(0);
}

fwrite(STDERR, "Usage:\n  php manage_groups.php list\n  php manage_groups.php set-password <group code>\n  php manage_groups.php set-admin-password\n");
exit(1);
