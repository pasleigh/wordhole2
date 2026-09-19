<?php
(@include_once("./config.php")) or die("Cannot find this file to include: config.php<BR>");

$db = new SQLite3($db_sqlite, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE) or die("cannot open the database");
$db->busyTimeout(5000);
$db->enableExceptions(true);

//For Date use TEXT as ISO8601 strings ("YYYY-MM-DD HH:MM:SS.SSS").
// Then use built in functions to manipulate date and times
$query = "CREATE TABLE IF NOT EXISTS 'w_index' (
                'id' INTEGER PRIMARY KEY NOT NULL,
                'round_num' INTEGER NOT NULL,
                'wordle_start_num' INTEGER NOT NULL,
                'wordle_start_date' TEXT NOT NULL,
                'par' INTEGER NOT NULL)";
$results = $db->query($query);

// People table
$query = "CREATE TABLE IF NOT EXISTS 'w_people' (
                'id' INTEGER PRIMARY KEY NOT NULL,
                'first_name' TEXT NOT NULL,
                'family_name' TEXT NOT NULL
                )";
$results = $db->query($query);

// Results data table
$query = "CREATE TABLE IF NOT EXISTS 'w_results' (
                'id' INTEGER PRIMARY KEY NOT NULL,
                'round_id' INTEGER NOT NULL,
                'person_id' INTEGER NOT NULL,
                'hole_num' INTEGER NOT NULL,
                'wordle_num' INTEGER NOT NULL,
                'score' INTEGER NOT NULL,
                'total' INTEGER NOT NULL
                )";
$results = $db->query($query);

// Answer table
$query = "CREATE TABLE IF NOT EXISTS 'w_answer' (
                'id' INTEGER PRIMARY KEY NOT NULL,
                'wordle_num' INTEGER NOT NULL,
                'wordle_answer' TEXT,
                'mean_score' REAL
                )";
$results = $db->query($query);

// Schema changes after the tables above are applied once, in order, and recorded in
// PRAGMA user_version so this file is safe to include on every request.
//   1 = groups: w_groups table and group_id on w_index, w_people and w_answer
$schema_version = (int)$db->querySingle("PRAGMA user_version");
if ($schema_version < 1) {
    // Migrating writes to the database file and creates a backup and SQLite journal beside it, so the
    // user running PHP (e.g. Apache's "daemon") needs write access to both the file and its folder
    if (!is_writable($db_sqlite) || !is_writable(dirname(realpath($db_sqlite)))) {
        $message = "The database $db_sqlite needs upgrading for groups, but the web server cannot write to it or its folder. "
            . "Give the web server write access to both, or run this once from the command line: php create_sqlite_tables.php";
    } else {
        try {
            migrate_to_groups(
                $db,
                $db_sqlite,
                isset($legacy_group_code) ? $legacy_group_code : 'wordhole1',
                isset($legacy_group_name) ? $legacy_group_name : 'Wordhole'
            );
        } catch (Throwable $e) {
            $message = "The database could not be upgraded for groups: " . $e->getMessage();
        }
    }
    if (isset($message)) {
        // Every page that includes this file expects JSON
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        die(json_encode(array('is_valid' => 0, 'message' => $message)) . "\n");
    }
}

function column_exists($db, $table, $column)
{
    $res = $db->query("PRAGMA table_info('$table')");
    while ($col = $res->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === $column) {
            return true;
        }
    }
    return false;
}

function migrate_to_groups($db, $db_file, $legacy_code, $legacy_name)
{
    // Take the write lock first so that if two requests arrive together only one migrates
    $db->exec("BEGIN IMMEDIATE");
    try {
        if ((int)$db->querySingle("PRAGMA user_version") >= 1) {
            $db->exec("COMMIT");
            return;
        }

        // Keep a copy of the database as it was before the migration
        $has_data = $db->querySingle("SELECT COUNT(*) FROM w_index") > 0
            || $db->querySingle("SELECT COUNT(*) FROM w_people") > 0
            || $db->querySingle("SELECT COUNT(*) FROM w_answer") > 0;
        if ($has_data) {
            $backup = $db_file . '.pre-groups-' . date('Ymd-His') . '.bak';
            if (!copy($db_file, $backup)) {
                throw new Exception("Could not back up $db_file to $backup before migrating");
            }
        }

        // Codes are unique regardless of case, so "Fam1" and "fam1" are the same group
        $db->exec("CREATE TABLE IF NOT EXISTS 'w_groups' (
                'id' INTEGER PRIMARY KEY NOT NULL,
                'code' TEXT NOT NULL UNIQUE COLLATE NOCASE,
                'name' TEXT NOT NULL,
                'created_at' TEXT NOT NULL
                )");

        // Existing data (if any) all belongs to the legacy group; a brand new database gets no group
        $legacy_group_id = 1;
        if ($has_data) {
            $stmt = $db->prepare("INSERT INTO w_groups (id, code, name, created_at) VALUES (:id, :code, :name, :created)");
            $stmt->bindValue(':id', $legacy_group_id, SQLITE3_INTEGER);
            $stmt->bindValue(':code', $legacy_code, SQLITE3_TEXT);
            $stmt->bindValue(':name', $legacy_name, SQLITE3_TEXT);
            $stmt->bindValue(':created', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->execute();
        }

        foreach (array('w_index', 'w_people', 'w_answer') as $table) {
            if (!column_exists($db, $table, 'group_id')) {
                $db->exec("ALTER TABLE $table ADD COLUMN group_id INTEGER NOT NULL DEFAULT $legacy_group_id");
            }
        }

        // Each person, round and wordle answer is unique within a group, and a hole is unique within a round
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_people_group_name ON w_people (group_id, first_name, family_name)");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_index_group_round ON w_index (group_id, round_num, wordle_start_num, wordle_start_date)");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_answer_group_wordle ON w_answer (group_id, wordle_num)");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_results_round_person_hole ON w_results (round_id, person_id, hole_num)");

        $db->exec("PRAGMA user_version = 1");
        $db->exec("COMMIT");
    } catch (Throwable $e) {
        $db->exec("ROLLBACK");
        throw $e;
    }
}
