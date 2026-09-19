<?php
(@include_once("./config.php")) or die("Cannot find this file to include: config.php<BR>");

$db = new SQLite3($db_sqlite, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE) or die("cannot open the database");

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
