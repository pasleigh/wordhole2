<?php
// The upload box is only shown when the page is opened with ?upload
$show_upload_block = true;
if (array_key_exists('upload', $_GET) == false) {
    $show_upload_block = false;
}

$upload_block = "";
if ($show_upload_block) {
    $upload_block .= <<<HTML
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Upload an Excel workbook</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5">
                        <label for="upload_password" class="form-label">Group password</label>
                        <input type="password" id="upload_password" class="form-control" autocomplete="off">
                        <div class="form-text">Needed to add results to a group. If the workbook is for a new group, this becomes its password (at least 8 characters).</div>
                    </div>
                    <div class="col-md-5">
                        <label for="upload_admin_password" class="form-label">Super admin password</label>
                        <input type="password" id="upload_admin_password" class="form-control" autocomplete="off">
                        <div class="form-text">Only needed when the workbook is for a group that does not exist yet.</div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div id="drop-area" class="border rounded d-flex justify-content-center align-items-center"
                        style="height: 200px; cursor: pointer;">
                        <div class="text-center">
                            <i class="bi bi-cloud-arrow-up-fill text-primary" style="font-size: 48px;"></i>
                            <p class="mt-3">Drag and drop your Excel file here or click to select a file.</p>
                            <p class="text-muted small">Cell A2 of each round sheet holds your group's code and A3 its name.</p>
                        </div>
                    </div>
                    <input type="file" id="fileElem" multiple accept=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel,text/comma-separated-values, text/csv, application/csv" class="d-none">
                </div>
            </div>
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
    <link rel="stylesheet" href="./css/wordhole.css">
</head>
<body>
<div class="container">
    <h2>Wordhole Record of Rounds</h2>
    <div class="row align-items-end">
        <div class="col-md-3">
            <div class="form-group">
                <label for="group_select">Select a group</label>
                <select id="group_select" class="form-select" aria-label="Select a group to show">
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="round_select">Select a round</label>
                <select id="round_select" class="form-select" aria-label="Select a round to show">
                    <option inactive>Select a round</option>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div id="auth_controls" class="d-flex flex-wrap gap-2 justify-content-md-end mt-2 mt-md-0"></div>
        </div>
    </div>
    <div id="round_message"></div>
    <div id="par_chart_container" style="height: 600px;"></div>
    <BR>
    <div id="column_chart_container" style="height: 300px;"></div>

    <div id="scorecard_card" class="card my-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 id="scorecard_title" class="mb-0">Scorecard</h5>
            <div id="scorecard_legend" class="wh-legend"></div>
        </div>
        <div class="card-body p-2">
            <div id="scorecard"></div>
            <p class="small text-muted mt-2 mb-0">X = failed to complete in 6 (counts as 6.9999). &nbsp; - = did not send in a result (counts as 7.0001).</p>
        </div>
    </div>

    <div id="daily_card" class="card mb-3" hidden>
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">Daily entry</h5>
            <div class="d-flex align-items-center gap-2">
                <label for="daily_hole" class="form-label mb-0 small text-muted">Day</label>
                <select id="daily_hole" class="form-select form-select-sm" style="width: auto;"></select>
            </div>
        </div>
        <div class="card-body">
            <div id="daily_people"></div>
            <div class="row mt-3 g-3 align-items-end">
                <div class="col-sm-4">
                    <label for="daily_word" class="form-label mb-1">Solution</label>
                    <input type="text" id="daily_word" class="form-control text-uppercase" maxlength="12" autocomplete="off">
                </div>
                <div class="col-sm-8">
                    <span class="text-muted">Mean score:</span> <strong id="daily_mean">-</strong>
                </div>
            </div>
        </div>
    </div>

    <?php echo($upload_block); ?>

</div>

<div id="wh_savebar" hidden>
    <span id="wh_savebar_text"></span>
    <button type="button" class="btn btn-outline-light btn-sm" id="wh_discard">Discard</button>
    <button type="button" class="btn btn-warning btn-sm" id="wh_save">Save changes</button>
</div>

<!-- Create a new group -->
<div class="modal fade" id="group_modal" tabindex="-1" aria-labelledby="group_title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="group_form">
            <div class="modal-header">
                <h5 class="modal-title" id="group_title">New group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="group_admin_password" class="form-label">Super admin password</label>
                <input type="password" id="group_admin_password" class="form-control" autocomplete="off" required>
                <div class="form-text mb-3">Only the site's administrator can create a group.</div>
                <label for="group_name" class="form-label">Group name</label>
                <input type="text" id="group_name" class="form-control mb-3" maxlength="100" autocomplete="off" required>
                <label for="group_code" class="form-label">Group code</label>
                <input type="text" id="group_code" class="form-control" maxlength="40" autocomplete="off" required>
                <div class="form-text mb-3">A short unique identifier, e.g. FAM01: letters, numbers, spaces, dots, dashes or underscores. It goes in the page address, and in cell A2 if you also upload workbooks.</div>
                <label for="group_password" class="form-label">Password for entering scores (at least 8 characters)</label>
                <input type="password" id="group_password" class="form-control mb-3" autocomplete="new-password" minlength="8" required>
                <label for="group_password_again" class="form-label">Password again</label>
                <input type="password" id="group_password_again" class="form-control" autocomplete="new-password" minlength="8" required>
                <div id="group_error" class="text-danger small mt-3" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create group</button>
            </div>
        </form>
    </div>
</div>

<!-- Log in to edit a group -->
<div class="modal fade" id="login_modal" tabindex="-1" aria-labelledby="login_title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="login_form">
            <div class="modal-header">
                <h5 class="modal-title" id="login_title">Log in to edit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Enter the password for <strong id="login_group_name"></strong>.</p>
                <input type="password" id="login_password" class="form-control" autocomplete="current-password" required>
                <div id="login_error" class="text-danger small mt-2" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Log in</button>
            </div>
        </form>
    </div>
</div>

<!-- Change the password of a group -->
<div class="modal fade" id="password_modal" tabindex="-1" aria-labelledby="password_title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="password_form">
            <div class="modal-header">
                <h5 class="modal-title" id="password_title">Change password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Everybody else who is logged in to this group will need the new password.</p>
                <label for="password_current" class="form-label">Current password</label>
                <input type="password" id="password_current" class="form-control mb-3" autocomplete="current-password" required>
                <label for="password_new" class="form-label">New password (at least 8 characters)</label>
                <input type="password" id="password_new" class="form-control mb-3" autocomplete="new-password" minlength="8" required>
                <label for="password_again" class="form-label">New password again</label>
                <input type="password" id="password_again" class="form-control" autocomplete="new-password" minlength="8" required>
                <div id="password_error" class="text-danger small mt-2" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Change password</button>
            </div>
        </form>
    </div>
</div>

<!-- Start a new round -->
<div class="modal fade" id="round_modal" tabindex="-1" aria-labelledby="round_title" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="round_form">
            <div class="modal-header">
                <h5 class="modal-title" id="round_title">New round</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <label for="round_num" class="form-label">Round number</label>
                        <input type="number" id="round_num" class="form-control" min="1" max="9999" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="round_start_date" class="form-label">First day</label>
                        <input type="date" id="round_start_date" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="round_start_wordle" class="form-label">First Wordle no.</label>
                        <input type="number" id="round_start_wordle" class="form-control" min="1" max="99999" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="round_par" class="form-label">Par</label>
                        <input type="number" id="round_par" class="form-control" min="1" max="6" required>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label">Who is playing?</label>
                        <div id="round_people" class="border rounded p-2" style="max-height: 14rem; overflow-y: auto;"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="round_new_players" class="form-label">New players (one per line, e.g. Ann Smith)</label>
                        <textarea id="round_new_players" class="form-control" rows="6"></textarea>
                    </div>
                </div>
                <div id="round_error" class="text-danger small mt-3" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create round</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-u1OknCvxWvY5kfmNBILK2hRnQC3Pr17a+RTT6rIHI7NnikvbZlHgTPOOmMi466C8"
        crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.6.1.min.js"
        integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>
<script src="./js/load_xlsx.js"></script>
<script src="./js/entry.js"></script>
<script src="./js/mycharts.js"></script>
<script src="./frameworks/highcharts_12_4_0/highcharts.js"></script>
<script src="./frameworks/highcharts_12_4_0/series-label.js"></script>
<script src="./frameworks/highcharts_12_4_0/exporting.js"></script>
<script src="./frameworks/highcharts_12_4_0/export-data.js"></script>
<script src="./frameworks/highcharts_12_4_0/accessibility.js"></script>

<script>
    let dropArea = document.getElementById("drop-area");

    // The drop box is only on the page when it is opened with ?upload
    if (dropArea) {

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
            form_data.append('password', document.getElementById('upload_password').value);
            form_data.append('admin_password', document.getElementById('upload_admin_password').value);
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
                    // Show the group the workbook was for
                    window.location.href = 'index.php?upload&g=' + encodeURIComponent(php_script_response.group_code)
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(xhr.responseText);
                    console.log(thrownError);
                    document.body.style.cursor = 'default';
                    document. getElementById("drop-area"). style. cursor = 'pointer';
                    let reason = (xhr.responseJSON && xhr.responseJSON.message) ? "\n\n" + xhr.responseJSON.message : "";
                    alert("There was an error loading the Excel file." + reason);
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
    }
</script>
<script>
    $(document).ready(function () {
        load_groups();
    });
</script>
</body>

</html>
