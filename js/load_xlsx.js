var all_rounds_data;
var the_chart;
var current_par
var current_round_num
var current_round_id
var current_round_wordle_start_num
// current_round_id is the position of the round in all_rounds_data; the database id is separate
var current_round_db_id
var current_group_id
var current_group_name = ""
var all_groups = []
const GROUP_STORAGE_KEY = 'wordhole_group_code'

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
    var i = $(this).find(":selected").val();
    //var name = $(this).find(":selected").text();
    //alert("Changed " + i + " name");
    current_round_id = i

    let selected_round_data = all_rounds_data[i];

    current_par = selected_round_data.par
    current_round_num = selected_round_data.round_num
    current_round_wordle_start_num = selected_round_data.start_wordle
    current_round_db_id = selected_round_data.round_id

    let chart_container_id = 'par_chart_container';
    let column_chart_container_id = 'column_chart_container';
    draw_par_chart(selected_round_data, chart_container_id);
    draw_column_chart(selected_round_data, column_chart_container_id);
    write_winners_info(selected_round_data);
    updateTable(selected_round_data)
});

$('#submit_table_data_to_sqlite').on('click', function () {
    push_table_data_to_sqlite()
})
function push_table_data_to_sqlite(){
    let alldata = summary_table.getData()
    var form_data = new FormData();
    form_data.append('data', JSON.stringify(alldata));
    form_data.append('round_id', current_round_db_id);
    form_data.append('group_id', current_group_id);
    $.ajax({
        type: 'post',
        url: './push_table_data_to_sqlite.php',
        contentType: false,
        processData: false,
        data: form_data,
        dataType: "json",
        success: function (mydata) {

            // Update the all rounds data
            /*
            for(let i = 0; i < alldata.length; i++){
                for(let j = 0; j < 18 ; j++){
                    let score = alldata[0][3+j];
                    if(score < 0){
                        alldata[0][3+j] = null
                    }
                }
            }
            all_rounds_data[current_round_id] = alldata
            updateTable(all_rounds_data[current_round_id])

             */
            load_wordle_data('par_chart_container', 'column_chart_container', current_group_id)
            alert("submitted to database")
        },
        error: report_ajax_error
    })
}

var cell_changed = function (instance, cell, x, y, value){
    let par = all_rounds_data[current_round_id].par
    //let cellName = jspreadsheet.getColumnNameFromId([x, y]);
    //$('#result').html('New change on cell [' + x + ', ' + y + '] ' + cellName + ' to: ' + value + '');
    let row_data = summary_table.getRowData(y);
    let id = row_data[0]
    let first_name = row_data[1]
    let family_name = row_data[2]
    let scores = Array()
    let new_total = 0
    for(let i=0;i<18;i++){
        let score = row_data[3+i]
        scores.push(score)
        if(score !== ""){
            if(score !==null){
                if(score > 0) {
                    new_total += (score - par)
                }
            }
        }
    }
    let total = row_data[21];

    let alldata = summary_table.getData()
    //setValueFromCoords: get value from coords
    //setValueFromCoords([integer], [integer], [string], [bool]);
    // doing this causes this function to be called in a circular reference
    //summary_table.setValueFromCoords(21,y,new_total,true)

    // try setting the row
    // caused same circular ref
    row_data[21] = Math.round(new_total)
    //summary_table.setRowData(y, row_data)

    alldata = summary_table.getData()
    //summary_table.data = alldata
    table_def.data = alldata
    $('#jspreadsheet_wordle_data').empty();
    summary_table = jspreadsheet(document.getElementById('jspreadsheet_wordle_data'), table_def);
    //alldata[y]=row_data
    //$('#result').html(JSON.stringify(alldata))
    //$('#result').append('<BR> id: ' + id, ", home/Int: " + home_int + ', Ethnic desc: '+ethnic_dec+ ', Nationlity: '+nationality_desc);
    //update_ethnicity_award_data(year,id,home_int,ethnic_dec,nationality_desc);
}

var table_def = {
    data: null,
    columns: [
        {type: 'numeric', width: '25', title: 'ID'},
        {type: 'text', width: '80', title: 'First name', readOnly: true},
        {type: 'text', width: '80', title: 'Family name', readOnly: true},
        {type: 'numeric', width: '49', title: 'Hole 1'},
        {type: 'numeric', width: '49', title: 'Hole 2'},
        {type: 'numeric', width: '49', title: 'Hole 3'},
        {type: 'numeric', width: '49', title: 'Hole 4'},
        {type: 'numeric', width: '49', title: 'Hole 5'},
        {type: 'numeric', width: '49', title: 'Hole 6'},
        {type: 'numeric', width: '49', title: 'Hole 7'},
        {type: 'numeric', width: '49', title: 'Hole 8'},
        {type: 'numeric', width: '49', title: 'Hole 9'},
        {type: 'numeric', width: '49', title: 'Hole 10'},
        {type: 'numeric', width: '49', title: 'Hole 11'},
        {type: 'numeric', width: '49', title: 'Hole 12'},
        {type: 'numeric', width: '49', title: 'Hole 13'},
        {type: 'numeric', width: '49', title: 'Hole 14'},
        {type: 'numeric', width: '49', title: 'Hole 15'},
        {type: 'numeric', width: '49', title: 'Hole 16'},
        {type: 'numeric', width: '49', title: 'Hole 17'},
        {type: 'numeric', width: '49', title: 'Hole 18'},
        {type: 'numeric', width: '49', title: 'Total'},
        {type: 'hidden', title: 'person_id'}
    ],
    nestedHeaders: [
        [
            {title: '', colspan: '1'},
            {title: 'Who', colspan: '2'},
            {title: '783', colspan: '1'},
            {title: '884', colspan: '1'},
            {title: '885', colspan: '1'},
            {title: '886', colspan: '1'},
            {title: '887', colspan: '1'},
            {title: '888', colspan: '1'},
            {title: '889', colspan: '1'},
            {title: '890', colspan: '1'},
            {title: '891', colspan: '1'},
            {title: '892', colspan: '1'},
            {title: '893', colspan: '1'},
            {title: '894', colspan: '1'},
            {title: '895', colspan: '1'},
            {title: '896', colspan: '1'},
            {title: '897', colspan: '1'},
            {title: '898', colspan: '1'},
            {title: '899', colspan: '1'},
            {title: '900', colspan: '1'},
            {title: '', colspan: '1'},
        ],
        [
            {title: '', colspan: '1'},
            {title: '', colspan: '2'},
            {title: '21 Aug', colspan: '1'},
            {title: '22 Aug', colspan: '1'},
            {title: '23 Aug', colspan: '1'},
            {title: '24 Aug', colspan: '1'},
            {title: '25 Aug', colspan: '1'},
            {title: '26 Aug', colspan: '1'},
            {title: '27 Aug', colspan: '1'},
            {title: '28 Aug', colspan: '1'},
            {title: '29 Aug', colspan: '1'},
            {title: '30 Aug', colspan: '1'},
            {title: '31 Aug', colspan: '1'},
            {title: '1 Sep', colspan: '1'},
            {title: '2 Sep', colspan: '1'},
            {title: '3 Sep', colspan: '1'},
            {title: '4 Sep', colspan: '1'},
            {title: '5 Sep', colspan: '1'},
            {title: '6 Sep', colspan: '1'},
            {title: '7 Sep', colspan: '1'},
            {title: '', colspan: '1'},
        ]
    ],
    //colAlignments: ['center', 'left', 'left', 'left'] ,
    filters: false,
    search: false,
    //pagination: 30,
    contextMenu: false,
    onchange: cell_changed
}

// Fill the group dropdown, choose the group to start on and show its results.
// The group comes from the g=<code> in the page address, else the one used last time, else the first.
function load_groups(chart_container_id, column_chart_container_id) {
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
            select_group(group, chart_container_id, column_chart_container_id)
        },
        error: report_ajax_error
    })
}

// Make this group the one shown: remember it, put it in the page address and load its results
function select_group(group, chart_container_id, column_chart_container_id) {
    current_group_id = group.id
    current_group_name = group.name
    try {
        localStorage.setItem(GROUP_STORAGE_KEY, group.code)
    } catch (e) {
        // remembering the group is only a convenience
    }
    let url = new URL(window.location.href)
    url.searchParams.set('g', group.code)
    window.history.replaceState(null, '', url)
    load_wordle_data(chart_container_id, column_chart_container_id, group.id)
}

$('#group_select').on('change', function () {
    let group_id = $(this).val()
    for (let i = 0; i < all_groups.length; i++) {
        if (String(all_groups[i].id) === String(group_id)) {
            select_group(all_groups[i], 'par_chart_container', 'column_chart_container')
        }
    }
})

// Nothing to chart: clear the page and say why
function show_no_rounds(message) {
    all_rounds_data = []
    current_round_db_id = null
    $('#round_select').empty()
    $('#par_chart_container').empty()
    $('#column_chart_container').empty()
    $('#body-title').empty()
    $('#jspreadsheet_wordle_data').empty()
    $('#scores').html("<p class='text-muted mt-3'>" + escapeHtml(message) + "</p>")
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

function load_wordle_data(chart_container_id, column_chart_container_id, group_id) {
    var form_data = new FormData();
    form_data.append('group_id', group_id);
    $.ajax({
        type: 'post',
        //url: 'test_pwd.php',
        //url: 'externaldev?task=ajax',
        //url: 'index.php?option=com_dotcontent&view=external?task=ajax',
        //url: './load_xlsx.php',
        url: './load_sqlite.php',
        contentType: false,
        processData: false,
        data: form_data,
        dataType: "json",
        success: function (mydata) {
            //
            //console.log(JSON.stringify(mydata));
            if (mydata.is_valid == 1) {
                if (mydata.round_data.length === 0) {
                    show_no_rounds("No rounds have been uploaded for " + mydata.group.name + " yet.")
                    return
                }
                let html = ""
                for (let i = 0; i < mydata.round_data.length; i++) {
                    html += "<option value='" + i + "'>" + mydata.round_data[i].name + "</option>";
                }
                $('#round_select').html(html);

                all_rounds_data = mydata.round_data;

                //alert(mydata.message + "\n" + mydata.file_info);
                current_round_id = 0

                let selected_round_data = all_rounds_data[current_round_id];
                //let chart_container_id = "par_chart_container";
                //let column_chart_container_id = "column_chart_container";
                draw_par_chart(selected_round_data, chart_container_id);
                draw_column_chart(selected_round_data, column_chart_container_id);
                write_winners_info(selected_round_data);

                current_par = selected_round_data.par
                current_round_num = selected_round_data.round_num
                current_round_wordle_start_num = selected_round_data.start_wordle
                current_round_db_id = selected_round_data.round_id

                if (document.getElementById('jspreadsheet_wordle_data')) {
                    $('#body-title').html("<h5>" + escapeHtml(current_group_name) + " - Wordhole round number " + current_round_num + "</h5>")
                    let start_date = selected_round_data.start_date
                    let start_date_split = start_date.split("-")
                    let start_date_d = new Date(start_date_split[2], parseInt(start_date_split[1]) - 1, start_date_split[0], 0, 0, 0)
                    let date_str = moment(start_date_d).format('D MMM');
                    //date.setDate(date.getDate() + days);
                    let start_wordle = selected_round_data.start_wordle
                    for (let i = 0; i < 18; i++) {
                        let wordle_count_header = table_def.nestedHeaders[0]
                        wordle_count_header[2 + i].title = start_wordle + i

                        let wordle_date_header = table_def.nestedHeaders[1]
                        date_str = moment(start_date_d).add(i, 'days').format('D MMM')
                        wordle_date_header[2 + i].title = date_str

                    }
                    let data = [
                        ['1', 'Andy', 'Sleigh', '3', '4', '5', '3', '5', '3', null, null, null, null, null, null, null, null, null, null, null, null, 0],
                        ['2', 'Alan', 'Bentley', '3', '4', '5', '3', '5', '3', null, null, null, null, null, null, null, null, null, null, null, null, 0],
                        ['3', 'Mark', 'Wilson', '3', '4', '5', '3', '5', '3', null, null, null, null, null, null, null, null, null, null, null, null, 0],
                        ['4', 'Sue', 'Marchant', '3', '4', '5', '3', '5', '3', null, null, null, null, null, null, null, null, null, null, null, null, 0],
                    ];
                    //alert("jspreadsheet_wordle_data exists")
                    updateTable(selected_round_data)
                    //data = getRoundDataForTable(selected_round_data)
                    //table_def.data = data
                    //$('#jspreadsheet_wordle_data').empty();
                    //summary_table = jspreadsheet(document.getElementById('jspreadsheet_wordle_data'), table_def);
                }

            }
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

function find_winners(score_data) {
    // find the lowest score for each hole
    // and record the people wi that score
    let min_scorers = Array();

    let num_holes = score_data.results[0].scores.length;
    for (let i = 0; i < num_holes; i++) {

        // loop through people to find lowest score for this hole
        let min_score = 10;
        for (let j = 0; j < score_data.results.length; j++) {
            let score = score_data.results[j].scores[i];
            if (score === null) {
                score = 7;
            }
            if (score < min_score) {
                min_score = score;
            }
        }

        // loop through people to find the list of people with this score
        let people = Array();
        for (let j = 0; j < score_data.results.length; j++) {
            let score = score_data.results[j].scores[i];
            if (score === min_score) {
                // add this name to the array
                let first_name = score_data.results[j].first_name;
                let family_name = score_data.results[j].family_name;
                people.push({first_name: first_name, family_name: family_name});
            }
        }
        min_scorers.push({score: min_score, names: people});
    }

    // loop through the people to find how many won a hole
    let winner_stats = Array();
    for (let j = 0; j < score_data.results.length; j++) {
        let first_name = score_data.results[j].first_name;
        let family_name = score_data.results[j].family_name;

        // loop through holes for this person
        let win_count = 0;
        for (let i = 0; i < num_holes; i++) {
            let min_for_hole = min_scorers[i].score;
            let person_score_for_hole = score_data.results[j].scores[i];
            if (min_for_hole == person_score_for_hole) {
                win_count++;
            }
        }
        winner_stats.push({first_name: first_name, family_name: family_name, win_count: win_count})
    }

    return {winners: min_scorers, winner_stats: winner_stats};
}

function write_winners_info(latest_round_data) {
    let winners_data = find_winners(latest_round_data);
    let winners = winners_data.winners;
    let winners_count = winners_data.winner_stats;
    //console.log(winners);
    let html = "";
    let wordle_num
    for (i = 0; i < winners.length; i++) {
        wordle_num = parseInt(latest_round_data.start_wordle) + i
        let wordle_word_str = ""
        if(latest_round_data.wordle_words[wordle_num] != "")
        {
            wordle_word_str = ` : ${latest_round_data.wordle_words[wordle_num]}`
        }
        let html_row = "";
        html_row += "<div class='row'>";
        html_row += `<strong>Hole: ${i + 1}, (${wordle_num}${wordle_word_str})`;
        html_row += ` Best score: ${winners[i].score}.`;
        html_row += ` Mean score: ${parseFloat(latest_round_data.mean_scores[wordle_num]).toFixed(2)}.`;
        //html_row += ` : ${latest_round_data.wordle_words[wordle_num]}.`;
        html_row += `</strong>`;
        //html += "</div>";
        //html += "<div class='row'>";
        //html += "<div class='col-2'></div>";
        //html += "<div class='col-2'>";
        let phrase = "";
        if (winners[i].names.length == 1) {
            phrase = "person";
        } else {
            phrase = "people";
        }
        html_row += ` ${winners[i].names.length} ${phrase} got this best score`;
        //html += "</div>";
        html_row += "</div>";
        html_row += "<div class='row mb-3'>";
        for (j = 0; j < winners[i].names.length; j++) {
            //html += "<div class='row'>";
            //html += "<div class='col-2'></div>";
            //html += "<div class='col-2'>";
            html_row += `${winners[i].names[j].first_name}`;
            html_row += ` ${winners[i].names[j].family_name}, `;
            //html += "</div>";
            //html += "</div>";
        }
        html_row += "</div>";
        if (winners[i].names.length > 0) {
            html += html_row;
        }
    }
    html += "<div class='row mt-5'>";
    html += "<div class='col-6'><h5>How many holes did you get the best score?</h5></div>";
    html += "</div>";
    for (j = 0; j < winners_count.length; j++) {
        html += "<div class='row'>";
        html += "<div class='col-2'></div>";
        html += "<div class='col-4'>";
        html += `${winners_count[j].first_name}`;
        html += ` ${winners_count[j].family_name} `;
        html += ` :  ${winners_count[j].win_count} `;
        html += "</div>";
        html += "</div>";
    }
    $('#scores').html(html);

}

function updateTable(this_round_data) {
    table_def.data = getRoundDataForTable(this_round_data)
    $('#jspreadsheet_wordle_data').empty();
    summary_table = jspreadsheet(document.getElementById('jspreadsheet_wordle_data'), table_def);
}


function getRoundDataForTable(this_round_data) {
    let data = Array()

    /*
        let data = [
            ['1','Andy','Sleigh', '3', '4', '5', '3', '5', '3',null,null,null,null,null,null,null,null,null,null,null,null,0],
            ['2','Alan','Bentley', '3', '4', '5', '3', '5', '3',null,null,null,null,null,null,null,null,null,null,null,null,0],
            ['3','Mark','Wilson', '3', '4', '5', '3', '5', '3',null,null,null,null,null,null,null,null,null,null,null,null,0],
            ['4','Sue','Marchant', '3', '4', '5', '3', '5', '3',null,null,null,null,null,null,null,null,null,null,null,null,0],
        ];
    */
    let par = this_round_data.par;
    let results = this_round_data.results
    for (let i = 0; i < results.length; i++) {
        let this_person = Array()
        this_person.push(i + 1)
        this_person.push(results[i].first_name)
        this_person.push(results[i].family_name)
        let total = 0
        for (j = 0; j < results[i].scores.length; j++) {
            let score = results[i].scores[j]
            this_person.push(score)
            if (score !== null) {
                total += (score - par)
            }
        }
        this_person.push(Math.round(total))
        this_person.push(results[i].person_id)
        data.push(this_person)
    }
    let mean_data_row = Array()
    mean_data_row.push(' ')
    mean_data_row.push(' ')
    mean_data_row.push('Mean')
    let wordle_num
    for (let i = 0; i < 18; i++) {
        wordle_num = parseInt(this_round_data.start_wordle) + i
        let mean = parseFloat(this_round_data.mean_scores[wordle_num])
        let mean_str = ''
        if(mean > 0.01){
            mean_str =  mean.toFixed(2)
        }
        mean_data_row.push(mean_str)
    }
    data.push(mean_data_row)

    let wordle_word_row = Array()
    wordle_word_row.push(' ')
    wordle_word_row.push(' ')
    wordle_word_row.push('Solution')
    for (let i = 0; i < 18; i++) {
        wordle_num = parseInt(this_round_data.start_wordle) + i
        wordle_word_row.push(this_round_data.wordle_words[wordle_num])
    }
    data.push(wordle_word_row)

    return data
}