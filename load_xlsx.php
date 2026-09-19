<?php
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");

$reload_main = 0;
if (array_key_exists('reload', $_GET)) {
    $reload_main = 1;
}

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

require_once './vendor/autoload.php';

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

$filename = "./wordhole_records.xlsx";
$PHP_EXCEL_filetype = IOFactory::identify($filename);
$file_info = "PHPSpreadsheet file type: $PHP_EXCEL_filetype<BR>";

$objPHPExcelModules = IOFactory::load($filename);
$num_modules = $objPHPExcelModules->getSheetCount();

$round_data = array();

foreach ($objPHPExcelModules->getWorksheetIterator() as $worksheet) {
    $worksheet_name = $worksheet->getTitle();
    $highestRow = $worksheet->getHighestRow();
    $highestCol = $worksheet->getHighestColumn();
    $highestColIndex = Coordinate::columnIndexFromString($highestCol);
    $nrColumns = ord($highestCol) - 64;

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

    $round_data[] = array(
        'start_date' => $start_date,
        'start_date_d-m-Y' => $start_date2,
        'start_wordle' => $start_wordle,
        'par' => $par,
        'results' => $results,
        'name' => $worksheet_name
    );

    // only do the first worksheet
    //break;
}

push_all_round_data_database($db, $round_data);

$message = "Success";
$is_valid = 1;


$return_data = array(
    'round_data' => $round_data,
    'message' => $message,
    'file_info' => $file_info,
    'is_valid' => $is_valid
);

//echo json_encode($return_data);
?>
<script>
    let reload_main = <?php echo $reload_main; ?>;

    if(reload_main > 0) {
        window.location.href = 'index.php?e=edit'
    }
</script>

