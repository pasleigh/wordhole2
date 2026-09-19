<?php
$x = 0;
if (array_key_exists('e', $_GET) == false) {
    $_GET['e'] = null;
}
$edit = $_GET['e'];

$show_edit_block = false;
if ($edit === "edit") {
    $show_edit_block = true;
}

$edit_block = "";
if ($show_edit_block) {
    $edit_block .= <<<HTML
        <div class="row">
                    <div class="col-4">
                        <button type="button" class="btn btn-primary" id="submit_table_data_to_sqlite">Submit updates</button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-4">
                        <A href="./load_xlsx.php?reload" class="btn btn-info"> load the xlsx file into the database</A>
                    </div>
                </div>
    HTML;
}

$show_upload_block = true;
if (array_key_exists('upload', $_GET) == false) {
    $show_upload_block = false;
}

$upload_block = "";
if ($show_upload_block) {
    $upload_block .= <<<HTML
        <div class="row">
                    <div class="col-4">
                        <!--<button type="button" class="btn btn-primary" id="submit_table_data_to_sqlite">Submit updates</button>-->
                    </div>
        </div>
        <div class="row mt-2">
            <div id="drop-area" class="border rounded d-flex justify-content-center align-items-center"
                style="height: 200px; cursor: pointer;">
                <div class="text-center">
                    <i class="bi bi-cloud-arrow-up-fill text-primary" style="font-size: 48px;"></i>
                    <p class="mt-3">Drag and drop your Excel file here or click to select a file.</p>
                </div>
            </div>
            <input type="file" id="fileElem" multiple accept=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel,text/comma-separated-values, text/csv, application/csv" class="d-none">

        </div>
    HTML;
}

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wordhole Record</title>
    <link rel="icon" type="image/x-icon" href="./wordle_96.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-iYQeCzEYFbKjA/T2uDLTpkwGzCiq6soy8tYaI1GyVh/UjpbCx/TYkiZhlZB6+fzT" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<div class="container">
    <h2>Wordhole Record of Rounds</h2>
    <div class="row>">
        <div class="col-md-3">
            <div class="form-group">
                <label for="round_select">Select a round</label>
                <select id="round_select" class="form-select" aria-label="Select a round to show">
                    <option inactive>Select a round</option>
                </select>
            </div>
        </div>
        <div class="col-md-9"></div>
    </div>
    <div id="par_chart_container" style="height: 600px;"></div>
    <BR>
    <div id="column_chart_container" style="height: 300px;"></div>
    <div id="edit_block">
        <div class='card mb-3'>
            <div class='card-header'>
                <h5>Wordhole scores data</h5>
            </div>
            <div id='edit_body' class='card-body m-2'>
                <div id="body-title">Wordle</div>
                <div style="font-size: small">
                    <div id='jspreadsheet_wordle_data'></div>
                    <p>6.9999 = Failed to complete in 6. <BR>7.0001 = Did not send in result</p>
                </div>
                <?php echo($edit_block); ?>
                <?php echo($upload_block); ?>
            </div>
        </div>
    </div>


    <div id="scores"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-u1OknCvxWvY5kfmNBILK2hRnQC3Pr17a+RTT6rIHI7NnikvbZlHgTPOOmMi466C8"
        crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.6.1.min.js"
        integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>
<script src="./js/load_xlsx.js"></script>
<script src="./js/mycharts.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/series-label.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="./frameworks/jspreadsheets/jspreadsheet.js"></script>
<link rel="stylesheet" href="./frameworks/jspreadsheets/jspreadsheet.css" type="text/css"/>
<script src="./frameworks/jsuites/jsuites.js"></script>
<link rel="stylesheet" href="./frameworks/jsuites/jsuites.css" type="text/css"/>

<script>
    let dropArea = document.getElementById("drop-area");

    ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
        dropArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    ["dragenter", "dragover"].forEach((eventName) => {
        dropArea.addEventListener(eventName, highlight, false);
    });

    ["dragleave", "drop"].forEach((eventName) => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });

    dropArea.addEventListener("drop", handleDrop, false);

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dropArea.classList.add("highlight");
    }

    function unhighlight(e) {
        dropArea.classList.remove("highlight");
    }

    function handleDrop(e) {
        let dt = e.dataTransfer;
        let files = dt.files;
        handleFiles(files);
    }

    function handleFiles(files) {
        [...files].forEach(uploadFile);
    }

    function uploadFile(file) {
        console.log("Uploading", file.name);
        var form_data = new FormData();
        form_data.append('file', file);
        //alert(form_data);
        document.body.style.cursor = 'wait';
        document. getElementById("drop-area"). style. cursor = 'wait';
        $.ajax({
            url: 'upload_excel.php', // <-- point to server-side PHP script
            dataType: 'json',  // <-- what to expect back from the PHP script, if anything
            cache: false,
            contentType: false,
            processData: false,
            data: form_data,
            type: 'post',
            success: function(php_script_response){
                //alert(php_script_response); // <-- display response from the PHP script, if any
                console.log("Sever response ", JSON.stringify(php_script_response));
                document.body.style.cursor = 'default';
                document. getElementById("drop-area"). style. cursor = 'pointer';
                //alert("Success loading the Excel file.");
                window.location.href = 'index.php?upload'
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(xhr.responseText);
                console.log(thrownError);
                document.body.style.cursor = 'default';
                document. getElementById("drop-area"). style. cursor = 'pointer';
                alert("There was an error loading the Excel file.");
            }
        });
    }

    dropArea.addEventListener("click", () => {
        fileElem.click();
    });

    let fileElem = document.getElementById("fileElem");
    fileElem.addEventListener("change", function (e) {
        handleFiles(this.files);
    });
</script>
<script>
    $(document).ready(function () {
        load_wordle_data('par_chart_container','column_chart_container');
    });
</script>
</body>

</html>