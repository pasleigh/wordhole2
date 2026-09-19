<?php
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");

// Each group has one password that lets you change its results (view-only needs no password).
// Logging in sets a cookie that is signed with a secret kept in the database and that lists the groups
// the browser has unlocked. It carries a fingerprint of each group's password hash, so changing a
// group's password logs everybody out of that group.
const AUTH_COOKIE = 'wordhole_auth';
const AUTH_LIFETIME = 43200;        // stay logged in for 12 hours
const AUTH_MAX_FAILURES = 10;       // wrong passwords allowed per group ...
const AUTH_FAILURE_WINDOW = 900;    // ... in this many seconds
const MIN_PASSWORD_LENGTH = 8;

function auth_secret($db)
{
    static $secret = null;
    if ($secret === null) {
        $row = db_row($db, "SELECT value FROM w_settings WHERE name = 'auth_secret'");
        if (!$row) {
            db_run($db, "INSERT OR IGNORE INTO w_settings (name, value) VALUES ('auth_secret', :secret)",
                array(':secret' => bin2hex(random_bytes(32))));
            $row = db_row($db, "SELECT value FROM w_settings WHERE name = 'auth_secret'");
        }
        $secret = $row['value'];
    }
    return $secret;
}

function auth_fingerprint($password_hash)
{
    return substr(hash('sha256', $password_hash), 0, 16);
}

// Only send the cookie to this application's own pages
function auth_cookie_path()
{
    return rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
}

// The groups named in a valid cookie: array(group_id => fingerprint). Not yet checked against the database.
function auth_read_cookie($db)
{
    if (empty($_COOKIE[AUTH_COOKIE]) || !is_string($_COOKIE[AUTH_COOKIE])) {
        return array();
    }
    $parts = explode('.', $_COOKIE[AUTH_COOKIE]);
    if (count($parts) !== 2) {
        return array();
    }
    list($payload, $signature) = $parts;
    if (!hash_equals(hash_hmac('sha256', $payload, auth_secret($db)), $signature)) {
        return array();
    }
    $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    if (!is_array($data) || !isset($data['e'], $data['g']) || !is_array($data['g']) || $data['e'] < time()) {
        return array();
    }
    return $data['g'];
}

function auth_write_cookie($db, $grants)
{
    $expires = time() + AUTH_LIFETIME;
    $payload = rtrim(strtr(base64_encode(json_encode(array('e' => $expires, 'g' => (object)$grants))), '+/', '-_'), '=');
    $value = $payload . '.' . hash_hmac('sha256', $payload, auth_secret($db));
    setcookie(AUTH_COOKIE, $value, array(
        'expires' => $expires,
        'path' => auth_cookie_path(),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ));
    $_COOKIE[AUTH_COOKIE] = $value; // so the rest of this request sees the change
}

// Can this browser change the results of this group?
function is_group_editor($db, $group_id)
{
    $grants = auth_read_cookie($db);
    $key = (string)$group_id;
    if (!isset($grants[$key])) {
        return false;
    }
    $group = db_row($db, "SELECT edit_password_hash FROM w_groups WHERE id = :id", array(':id' => (int)$group_id));
    if (!$group || !$group['edit_password_hash']) {
        return false;
    }
    return hash_equals(auth_fingerprint($group['edit_password_hash']), (string)$grants[$key]);
}

// Ids of all groups this browser can change
function editor_group_ids($db)
{
    $ids = array();
    foreach (array_keys(auth_read_cookie($db)) as $group_id) {
        if (is_group_editor($db, $group_id)) {
            $ids[] = (int)$group_id;
        }
    }
    return $ids;
}

// Stop a page that changes data unless the browser has unlocked the group
function require_group_editor($db, $group_id)
{
    if (!is_group_editor($db, $group_id)) {
        json_fail("Please log in to change the results of this group.", 401);
    }
}

function require_post()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_fail("This must be sent with POST.", 405);
    }
}

// Remember in the cookie that this group is unlocked
function grant_group($db, $group_id)
{
    $group = db_row($db, "SELECT edit_password_hash FROM w_groups WHERE id = :id", array(':id' => (int)$group_id));
    $grants = auth_read_cookie($db);
    $grants[(string)$group_id] = auth_fingerprint($group['edit_password_hash']);
    auth_write_cookie($db, $grants);
}

function auth_logout()
{
    setcookie(AUTH_COOKIE, '', array('expires' => time() - 3600, 'path' => auth_cookie_path(), 'httponly' => true, 'samesite' => 'Lax'));
    unset($_COOKIE[AUTH_COOKIE]);
}

// Returns an error message if the password is not acceptable, else null
function validate_new_password($password)
{
    if (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
        return "The password must be at least " . MIN_PASSWORD_LENGTH . " characters long.";
    }
    if (mb_strlen($password) > 200) {
        return "The password is too long.";
    }
    return null;
}

// Set (or replace) a group's password and return the stored hash
function set_group_password($db, $group_id, $password)
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    db_run($db, "UPDATE w_groups SET edit_password_hash = :hash WHERE id = :id", array(':hash' => $hash, ':id' => (int)$group_id));
    return $hash;
}

// Check a group's password and, if it is right, unlock the group for this browser.
// Returns array('ok'=>bool, 'status'=>http status, 'message'=>text)
function group_login($db, $group_id, $password)
{
    $group = db_row($db, "SELECT id, code, edit_password_hash FROM w_groups WHERE id = :id", array(':id' => (int)$group_id));
    if (!$group) {
        return array('ok' => false, 'status' => 404, 'message' => "There is no such group.");
    }
    if (!$group['edit_password_hash']) {
        return array('ok' => false, 'status' => 403, 'message' => "Editing is not switched on for this group yet. "
            . "The site owner can set its password by running: php manage_groups.php set-password " . $group['code']);
    }

    $recent = db_row($db, "SELECT COUNT(*) AS n FROM w_login_failures WHERE group_id = :id AND failed_at > :since",
        array(':id' => $group['id'], ':since' => time() - AUTH_FAILURE_WINDOW));
    if ($recent['n'] >= AUTH_MAX_FAILURES) {
        return array('ok' => false, 'status' => 429, 'message' => "Too many wrong passwords. Please wait a few minutes and try again.");
    }

    if (!password_verify($password, $group['edit_password_hash'])) {
        db_run($db, "INSERT INTO w_login_failures (group_id, ip, failed_at) VALUES (:id, :ip, :now)",
            array(':id' => $group['id'], ':ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', ':now' => time()));
        db_run($db, "DELETE FROM w_login_failures WHERE failed_at < :old", array(':old' => time() - 86400));
        usleep(500000); // make guessing slow
        return array('ok' => false, 'status' => 401, 'message' => "That password is not right.");
    }

    db_run($db, "DELETE FROM w_login_failures WHERE group_id = :id", array(':id' => $group['id']));
    grant_group($db, $group['id']);
    return array('ok' => true, 'status' => 200, 'message' => "Logged in.");
}
