// Groups, rounds and the charts. The scorecard, score entry and logging in are in entry.js
var all_rounds_data = []
var current_round_id = 0     // position of the round in all_rounds_data (not its database id)
var current_group_id = null
var current_group_name = ""
var current_group_code = ""
var group_has_password = false
var all_groups = []
var group_people = []        // everybody who has played in the current group
const GROUP_STORAGE_KEY = 'wordhole_group_code'
const CHART_ID = 'par_chart_container'
const COLUMN_CHART_ID = 'column_chart_container'

function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, function (c) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]
    })
}

// "Wordhole" or "Wordhole - <group name>" for the chart titles
function wordhole_title(suffix) {
    let title = "Wordhole" + (suffix ? " " + suffix : "")
    if (current_group_name) {
        title += " - " + escapeHtml(current_group_name)
    }
    return title
}

$('#round_select').on('change', function () {
    let index = parseInt($(this).val())
    if (!wh_confirm_leave()) {
        $(this).val(current_round_id)
        return
    }
    show_round(index)
});

// Show one round: the charts and the scorecard
function show_round(index) {
    current_round_id = index
    $('#round_select').val(index)
    let selected_round_data = all_rounds_data[index]
    $('#round_message').empty()
    draw_par_chart(selected_round_data, CHART_ID);
    draw_column_chart(selected_round_data, COLUMN_CHART_ID);
    wh_show_round(selected_round_data)
}

// Fill the group dropdown, choose the group to start on and show its results.
// The group comes from the g=<code> in the page address, else the one used last time, else the first.
// keep_round_id: the database id of the round to show again afterwards (e.g. after logging in)
function load_groups(keep_round_id) {
    $.ajax({
        type: 'post',
        url: './load_groups.php',
        contentType: false,
        processData: false,
        dataType: "json",
        success: function (mydata) {
            all_groups = mydata.groups
            let select = $('#group_select').empty()
            for (let i = 0; i < all_groups.length; i++) {
                select.append($('<option>').val(all_groups[i].id).text(all_groups[i].name))
            }
            if (all_groups.length === 0) {
                show_no_rounds("No results have been uploaded yet.")
                return
            }

            let wanted_code = new URLSearchParams(window.location.search).get('g')
            if (wanted_code === null) {
                try {
                    wanted_code = localStorage.getItem(GROUP_STORAGE_KEY)
                } catch (e) {
                    wanted_code = null
                }
            }
            let group = all_groups[0]
            for (let i = 0; i < all_groups.length; i++) {
                if (wanted_code !== null && all_groups[i].code.toLowerCase() === wanted_code.toLowerCase()) {
                    group = all_groups[i]
                }
            }
            select.val(group.id)
            select_group(group, keep_round_id)
        },
        error: report_ajax_error
    })
}

// Make this group the one shown: remember it, put it in the page address and load its results
function select_group(group, keep_round_id) {
    current_group_id = group.id
    current_group_name = group.name
    current_group_code = group.code
    group_has_password = group.has_password
    try {
        localStorage.setItem(GROUP_STORAGE_KEY, group.code)
    } catch (e) {
        // remembering the group is only a convenience
    }
    let url = new URL(window.location.href)
    url.searchParams.set('g', group.code)
    window.history.replaceState(null, '', url)
    load_wordle_data(group.id, keep_round_id)
}

$('#group_select').on('change', function () {
    let group_id = $(this).val()
    if (!wh_confirm_leave()) {
        $(this).val(current_group_id)
        return
    }
    for (let i = 0; i < all_groups.length; i++) {
        if (String(all_groups[i].id) === String(group_id)) {
            select_group(all_groups[i])
        }
    }
})

// Nothing to chart: clear the page and say why
function show_no_rounds(message) {
    all_rounds_data = []
    $('#round_select').empty()
    $('#par_chart_container').empty()
    $('#column_chart_container').empty()
    $('#round_message').html("<p class='text-muted mt-3'>" + escapeHtml(message) + "</p>")
    wh_show_round(null)
}

function report_ajax_error(jqXHR, textStatus, errorThrown) {
    let reason = (jqXHR.responseJSON && jqXHR.responseJSON.message) ? "\n\n" + jqXHR.responseJSON.message : ""
    alert('An error occurred... Look at the console (F12 or Ctrl+Shift+I, Console tab) for more information!' + reason)
    console.log('jqXHR.responseText');
    console.log(jqXHR.responseText);
    console.log('jqXHR:');
    console.log(jqXHR);
    console.log('textStatus:');
    console.log(textStatus);
    console.log('errorThrown:');
    console.log(errorThrown);
}

// Load every round of a group and show one of them.
// select_round_id: the database id of the round to show, else the newest
function load_wordle_data(group_id, select_round_id) {
    var form_data = new FormData();
    form_data.append('group_id', group_id);
    $.ajax({
        type: 'post',
        url: './load_sqlite.php',
        contentType: false,
        processData: false,
        data: form_data,
        dataType: "json",
        success: function (mydata) {
            if (mydata.is_valid != 1) {
                return
            }
            group_people = mydata.people
            wh_set_access(mydata.can_edit)
            all_rounds_data = mydata.round_data
            if (all_rounds_data.length === 0) {
                show_no_rounds("No rounds have been added for " + mydata.group.name + " yet.")
                return
            }
            let html = ""
            let index = 0
            for (let i = 0; i < all_rounds_data.length; i++) {
                html += "<option value='" + i + "'>" + all_rounds_data[i].name + "</option>";
                if (select_round_id !== undefined && String(all_rounds_data[i].round_id) === String(select_round_id)) {
                    index = i
                }
            }
            $('#round_select').html(html);
            show_round(index)
        },
        error: report_ajax_error
    })
}

function draw_par_chart(score_data, container_id) {
    let myChart = myline_chart;

    let par = parseInt(score_data.par);
    let start_wordle_num = parseInt(score_data.start_wordle);

    myChart.title.text = wordhole_title();
    let subtitle_text = score_data.name + ". First hole (" + score_data.start_wordle + ") " + score_data.start_date;
    myChart.subtitle.text = subtitle_text;

    myChart.series = [];

    var d = new Date();
    var my_data_string =  d.getFullYear() + '-' + ('0' + (d.getMonth()+1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    myChart.exporting.filename = "wordhole_chart_" + score_data.round_num + "_" + my_data_string;

    for (let i = 0; i < score_data.results.length; i++) {
        if (!score_data.results[i].scores.some(function (s) { return s !== null; })) {
            // Playing this round but nothing entered yet
            continue;
        }
        let name = score_data.results[i].first_name + " " + score_data.results[i].family_name;
        let score_array = Array();
        let running_total = 0;
        score_array.push({x: start_wordle_num-1, y: 0, v: 0, h: 0, c: ''});

        for (let j = 0; j < score_data.results[i].scores.length; j++) {
            let x = start_wordle_num + j;
            let y;
            let v;
            let comment = "";
            if (score_data.results[i].scores[j]) {
                v = Math.round(score_data.results[i].scores[j]);
                y = v - par;
                running_total += y;
                if (score_data.results[i].scores[j] > 7) {
                    comment = "<BR>Did not submit 🙁";// U+2641
                }
                if (v == 7 && score_data.results[i].scores[j] < 7) {
                    // A real 7 score
                    comment = "😵";//U+1F974️";
                }
                if (v < 3) {
                    comment = "🤩";//U+1F929"; // Big smile
                }
            } else {
                y = null;
                running_total = null;
                v = null;
            }
            score_array.push({x: x, y: running_total, v: v, h: j + 1, c: comment});
        }
        myChart.series.push(
            {
                name: name,
                data: score_array
            }
        );

    }

    /*
    if(the_chart) {
        while( the_chart.series.length > 0 ) {
            for(let i = 0 ; i < the_chart.series.length; i++){
                the_chart.series[i].remove( false );
            }
        }
    }
    */
    $('#' + container_id).show();
    $('#' + container_id).highcharts(myChart);

}
function draw_column_chart(score_data, container_id) {
    let myChart = mycolumn_chart;

    let par = parseInt(score_data.par);
    let start_wordle_num = parseInt(score_data.start_wordle);

    myChart.title.text = wordhole_title("Mean Scores");
    let subtitle_text = score_data.name + ". First hole (" + score_data.start_wordle + ") " + score_data.start_date;
    myChart.subtitle.text = subtitle_text;

    myChart.series = []
    // Get the data for this round
    let mean_data = Array()
    let wordle_num
    let this_round_data = score_data
    for (let i = 0; i < 18; i++) {
        wordle_num = parseInt(this_round_data.start_wordle) + i
        let mean = parseFloat(this_round_data.mean_scores[wordle_num])
        let mean_str = ''
        if(mean > 0.01){
            mean_str =  mean.toFixed(2)
        }
        mean_data.push({y:mean, wordle_num: wordle_num })
    }
    myChart.series.push(
        {
            name: 'Wordle',
            data: mean_data
        }
    )

    let wordle_words = Array()
    for (let i = 0; i < 18; i++) {
        wordle_num = parseInt(this_round_data.start_wordle) + i
        wordle_words.push(this_round_data.wordle_words[wordle_num])
    }
    myChart.xAxis.categories = wordle_words


    var d = new Date();
    var my_data_string =  d.getFullYear() + '-' + ('0' + (d.getMonth()+1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    myChart.exporting.filename = "wordhole_mean_scores_chart_" + score_data.round_num + "_" + my_data_string;
    $('#' + container_id).show();
    $('#' + container_id).highcharts(myChart);

}
