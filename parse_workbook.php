<?php
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

require_once __DIR__ . '/vendor/autoload.php';

// Thrown when the workbook is not something we can load; the message is safe to show the user
class WorkbookException extends Exception
{
}

function getDateStrFromCell($worksheet, $row, $col, $date_format = 'd-m-Y')
{
    $cellDataType = $worksheet->getCell([$col, $row])->getDataType();
    if ($cellDataType == 'n') {
        // Date format
        $mydate = $worksheet->getCell([$col, $row])->getValue();
        $mydate = Date::excelToDateTimeObject($mydate);
        $mydate_str = $mydate->format($date_format); // 02-09-1963
        //$intro_week_s2 = $intro_week_sd->format('j/M/Y'); // 2/sep/163
    } elseif ($cellDataType == 'f') {
        // Formula (hopefully a date)
        $mydate = $worksheet->getCell([$col, $row])->getOldCalculatedValue();
        $mydate = Date::excelToDateTimeObject($mydate);
        $mydate_str = $mydate->format($date_format); // 02-09-1963
    } else {
        // let just take the value
        $mydate_str = $worksheet->getCell([$col, $row])->getValue();
    }
    return $mydate_str;
}

// Read an Excel workbook of Wordhole rounds. Every "Round n" worksheet holds:
//   A2 = the group's unique code (the same on every round sheet)
//   A3 = the group's display name (optional)
// Returns array('group_code'=>, 'group_name'=>, 'round_data'=>, 'file_info'=>)
function parse_workbook($filename)
{
    $PHP_EXCEL_filetype = IOFactory::identify($filename);
    $file_info = "PHPSpreadsheet file type: $PHP_EXCEL_filetype<BR>";

    $objPHPExcelModules = IOFactory::load($filename);

    $round_data = array();
    $group_code = null;
    $group_name = '';

    foreach ($objPHPExcelModules->getWorksheetIterator() as $worksheet) {
        $worksheet_name = $worksheet->getTitle();
        if (strpos($worksheet_name, 'Round ') !== 0) {
            // Only "Round n" sheets hold results (e.g. the "Blank" template sheet does not)
            continue;
        }

        // Which group is this sheet for?
        $sheet_code = trim((string)$worksheet->getCell('A2')->getValue());
        if ($sheet_code === '') {
            throw new WorkbookException("Cell A2 of the sheet \"$worksheet_name\" is empty. It must hold the code that identifies your group.");
        }
        if (!preg_match('/^[\p{L}\p{N}_. -]{1,40}$/u', $sheet_code)) {
            throw new WorkbookException("The group code \"$sheet_code\" in cell A2 of the sheet \"$worksheet_name\" is not valid. Use up to 40 letters, numbers, spaces, dots, dashes or underscores.");
        }
        if ($group_code === null) {
            $group_code = $sheet_code;
        } elseif (strcasecmp($group_code, $sheet_code) !== 0) {
            throw new WorkbookException("Cell A2 differs between sheets: \"$group_code\" and \"$sheet_code\" (sheet \"$worksheet_name\"). One workbook can only hold one group.");
        }
        if ($group_name === '') {
            $group_name = mb_substr(trim((string)$worksheet->getCell('A3')->getValue()), 0, 100);
        }

        // Get the par from row 1, col 2
        $row = 1;
        $col = 2; // columns are now 1 based
        $myString = $worksheet->getCell([$col, $row])->getValue();
        $par = $myString;

        // Get the date of the first wordle
        $row = 1;
        $col = 3; // columns are now 1 based
        $myString = getDateStrFromCell($worksheet, $row, $col);//'d-m-Y == 24-09-1963
        $start_date = $myString;
        $start_date2 = getDateStrFromCell($worksheet, $row, $col);

        // Get the wordle number of the first one
        $row = 2;
        $col = 3; // columns are now 1 based
        $myString = $worksheet->getCell([$col, $row])->getValue();
        $start_wordle = $myString;

        $results = array();

        $num_holes = 18;
        $start_row = 4;
        $num_start_col = 3;

        // Find the mean row
        $mean_row = 0;
        for ($row = $start_row; $row < 100; $row++) {
            $col = 2;
            $mean_text = $worksheet->getCell([$col, $row])->getValue();
            if ($mean_text === 'Mean') {
                $mean_row = $row;
                break;
            }
        }
        // Get the names and scores
        for ($row = $start_row; $row < 100; $row++) {
            $col = 1;
            $first_name = $worksheet->getCell([$col, $row])->getValue();
            if ($first_name === null) {
                break;
            }
            if (trim($first_name) == "") {
                break;
            }
            $col++;
            $family_name = $worksheet->getCell([$col, $row])->getValue();
            $scores = array();
            for ($i = 1; $i <= 18; $i++) {
                $col = $num_start_col + ($i - 1) * 3;
                $myString = $worksheet->getCell([$col, $row])->getValue();
                if ($myString == "") {
                    $scores[] = null;
                } else {
                    $scores[] = floatval($myString);
                }
            }
            $results[] = array(
                'first_name' => $first_name,
                'family_name' => $family_name,
                'scores' => $scores
            );
        }
        // Get the means and wordle words
        $mean_scores = array();
        $wordle_words = array();
        $row = $mean_row;
        for ($i = 1; $i <= 18; $i++) {
            if ($mean_row === 0) {
                // No "Mean" row on this sheet
                $mean_scores[] = null;
                $wordle_words[] = null;
                continue;
            }
            $col = $num_start_col + ($i - 1) * 3;
            $myString = $worksheet->getCell([$col, $row])->getOldCalculatedValue();
            if ($myString == "") {
                $mean_scores[] = null;
            } else {
                $mean_scores[] = floatval($myString);
            }
            // Get teh wordle word
            $myString = $worksheet->getCell([$col, $row + 1])->getValue();
            $wordle_words[] = $myString;
        }

        $round_data[] = array(
            'start_date' => $start_date,
            'start_date_d-m-Y' => $start_date2,
            'start_wordle' => $start_wordle,
            'par' => $par,
            'results' => $results,
            'mean_scores' => $mean_scores,
            'wordle_words' => $wordle_words,
            'name' => $worksheet_name
        );
    }

    if ($group_code === null) {
        throw new WorkbookException("This workbook has no \"Round n\" sheets.");
    }

    return array(
        'group_code' => $group_code,
        'group_name' => $group_name,
        'round_data' => $round_data,
        'file_info' => $file_info
    );
}
