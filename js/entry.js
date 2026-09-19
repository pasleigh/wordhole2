// Logging in to edit a group, the scorecard, entering scores day by day and starting new rounds.
// The groups, rounds and charts are in load_xlsx.js, which calls wh_show_round, wh_set_access and wh_confirm_leave.
(function () {
    'use strict';

    const NUM_HOLES = 18
    const SCORE_FAILED = 6.9999      // typed as X: failed to complete in 6
    const SCORE_NOT_SENT = 7.0001    // typed as -: did not send in a result
    const WORDLE_DAY_ZERO = Date.UTC(2021, 5, 19)   // Wordle number 0 was on 19 June 2021
    const DAY_MS = 86400000
    const DAYS_BETWEEN_ROUNDS = 21   // a round is 18 days plus a break

    // The scores are held as the text typed in a cell: '' (nothing), '1' to '6', 'X' or '-'.
    // Anything else in the database (e.g. an old 0 or 7) is shown as it is until it is changed.
    const state = {
        canEdit: false,          // this browser has unlocked the group
        editing: false,          // score entry is switched on
        accessGroup: null,       // the group canEdit was worked out for
        round: null,             // the round shown (an item of all_rounds_data)
        pids: [],                // the people in the round, in table order
        original: {},            // person id -> 18 texts as stored
        draft: {},               // person id -> 18 texts as they are now
        originalWords: [],       // the 18 solutions as stored
        draftWords: [],
        dailyHole: 1,            // the day (1 to 18) shown in the daily entry
        cells: {},               // person id -> 18 <td> of the scorecard
        inputs: [],              // [row][hole] -> <input> of the scorecard, when entering scores
        wordCells: [],
        meanCells: [],
        totalCells: {},
        saving: false
    }

    // ---------------------------------------------------------------- scores and dates

    function valueToText(v) {
        if (v === null || v === undefined) {
            return ''
        }
        if (Math.abs(v - SCORE_FAILED) < 0.000001) {
            return 'X'
        }
        if (Math.abs(v - SCORE_NOT_SENT) < 0.000001) {
            return '-'
        }
        return String(v)
    }

    function textToValue(text) {
        if (text === '') {
            return null
        }
        if (text === 'X') {
            return SCORE_FAILED
        }
        if (text === '-') {
            return SCORE_NOT_SENT
        }
        return Number(text)
    }

    // The colour of a score: how it compares with par
    function scoreClass(text, par) {
        if (text === '') {
            return ''
        }
        if (text === 'X') {
            return 's-fail'
        }
        if (text === '-') {
            return 's-none'
        }
        const diff = Number(text) - par
        if (isNaN(diff)) {
            return ''
        }
        if (diff <= -2) {
            return 's-great'
        }
        if (diff <= -1) {
            return 's-good'
        }
        if (diff < 1) {
            return 's-par'
        }
        if (diff < 2) {
            return 's-over'
        }
        return 's-bad'
    }

    function displayText(text) {
        return text === '-' ? '–' : text
    }

    // "08-12-2023" -> a date (held as UTC midnight so daylight saving cannot move it)
    function parseDate(dmy) {
        const parts = String(dmy).split('-').map(Number)
        return new Date(Date.UTC(parts[2], parts[1] - 1, parts[0]))
    }

    function addDays(date, days) {
        return new Date(date.getTime() + days * DAY_MS)
    }

    function isoDate(date) {
        return date.toISOString().slice(0, 10)
    }

    function formatDay(date) {
        return date.toLocaleDateString('en-GB', {weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC'})
    }

    function formatShortDay(date) {
        return date.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', timeZone: 'UTC'})
    }

    function wordleNumberForDate(date) {
        return Math.round((date.getTime() - WORDLE_DAY_ZERO) / DAY_MS)
    }

    function todayUtc() {
        const now = new Date()
        return new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()))
    }

    function par() {
        return Number(state.round.par)
    }

    function startWordle() {
        return Number(state.round.start_wordle)
    }

    function isEntering() {
        return state.canEdit && state.editing && state.round !== null
    }

    // ---------------------------------------------------------------- the draft

    // Start again from what is stored for the round
    function resetDraft() {
        const round = state.round
        state.pids = []
        state.original = {}
        state.draft = {}
        state.originalWords = []
        state.draftWords = []
        if (!round) {
            return
        }
        round.results.forEach(function (person) {
            const texts = person.scores.map(valueToText)
            state.pids.push(person.person_id)
            state.original[person.person_id] = texts.slice()
            state.draft[person.person_id] = texts.slice()
        })
        for (let hole = 0; hole < NUM_HOLES; hole++) {
            const word = round.wordle_words[startWordle() + hole]
            state.originalWords.push(word ? String(word) : '')
        }
        state.draftWords = state.originalWords.slice()
    }

    function changes() {
        const scores = []
        const words = []
        state.pids.forEach(function (pid) {
            for (let hole = 0; hole < NUM_HOLES; hole++) {
                if (state.draft[pid][hole] !== state.original[pid][hole]) {
                    scores.push({person_id: Number(pid), hole_num: hole + 1, value: textToValue(state.draft[pid][hole])})
                }
            }
        })
        for (let hole = 0; hole < NUM_HOLES; hole++) {
            if (state.draftWords[hole] !== state.originalWords[hole]) {
                words.push({hole_num: hole + 1, word: state.draftWords[hole]})
            }
        }
        return {scores: scores, words: words}
    }

    function changeCount() {
        const c = changes()
        return c.scores.length + c.words.length
    }

    // Ask before throwing away unsaved changes
    window.wh_confirm_leave = function () {
        if (changeCount() === 0) {
            return true
        }
        return window.confirm('You have scores that are not saved yet. Leave them behind?')
    }

    window.addEventListener('beforeunload', function (e) {
        if (changeCount() > 0) {
            e.preventDefault()
            e.returnValue = ''
        }
    })

    // ---------------------------------------------------------------- called by load_xlsx.js

    // Whether this browser can change the results of the group being shown
    window.wh_set_access = function (canEdit) {
        if (!canEdit || state.accessGroup !== current_group_id) {
            state.editing = false
        }
        state.accessGroup = current_group_id
        state.canEdit = canEdit
    }

    // Show a round (or null when the group has no rounds yet)
    window.wh_show_round = function (round) {
        state.round = round
        resetDraft()
        state.dailyHole = round ? defaultDay() : 1
        renderAll()
    }

    // ---------------------------------------------------------------- toolbar

    function toolbarButton(html, classes, onClick) {
        return $('<button type="button">').addClass('btn btn-sm ' + classes).html(html).on('click', onClick)
    }

    function renderToolbar() {
        const box = $('#auth_controls').empty()
        if (current_group_id === null) {
            return
        }
        if (!state.canEdit) {
            if (group_has_password) {
                box.append(toolbarButton('<i class="bi bi-lock"></i> Log in to edit', 'btn-outline-secondary', function () {
                    openLogin()
                }))
            } else {
                box.append($('<span class="text-muted small">').text('Editing is not switched on for this group.'))
            }
            return
        }
        box.append(toolbarButton(state.editing ? 'Stop entering scores' : '<i class="bi bi-pencil"></i> Enter scores',
            state.editing ? 'btn-primary active' : 'btn-primary', toggleEditing))
        box.append(toolbarButton('<i class="bi bi-plus-lg"></i> New round', 'btn-outline-primary', openNewRound))
        box.append($('<a class="btn btn-sm btn-outline-secondary">').attr('href', 'index.php?upload&g=' + encodeURIComponent(current_group_code))
            .html('<i class="bi bi-cloud-arrow-up"></i> Upload workbook'))
        box.append(toolbarButton('Change password', 'btn-outline-secondary', openChangePassword))
        box.append(toolbarButton('<i class="bi bi-box-arrow-right"></i> Log out', 'btn-outline-secondary', logout))
    }

    function toggleEditing() {
        if (state.editing && !window.wh_confirm_leave()) {
            return
        }
        if (state.editing) {
            resetDraft()
        }
        state.editing = !state.editing
        renderAll()
    }

    function renderAll() {
        renderToolbar()
        renderScorecard()
        renderDaily()
        updateSaveBar()
    }

    // ---------------------------------------------------------------- scorecard

    function personName(person) {
        return (person.first_name + ' ' + person.family_name).trim()
    }

    function renderLegend() {
        const legend = $('#scorecard_legend').empty()
        if (!state.round) {
            return
        }
        const byClass = {}
        for (let n = 1; n <= 6; n++) {
            const c = scoreClass(String(n), par());
            (byClass[c] = byClass[c] || []).push(n)
        }
        ;['s-great', 's-good', 's-par', 's-over', 's-bad'].forEach(function (c) {
            if (byClass[c]) {
                const nums = byClass[c]
                legend.append($('<span>').addClass(c).text(nums.length > 1 ? nums[0] + '–' + nums[nums.length - 1] : nums[0]))
            }
        })
        legend.append($('<span class="s-fail">').text('X'))
        legend.append($('<span class="s-none">').text('–'))
    }

    function renderScorecard() {
        const round = state.round
        $('#scorecard_card').prop('hidden', round === null)
        if (!round) {
            $('#scorecard').empty()
            return
        }
        $('#scorecard_title').text(current_group_name + ' - ' + round.name + ' (par ' + round.par + ')')
        renderLegend()

        const entering = isEntering()
        const startDate = parseDate(round.start_date)
        let html = '<div class="wh-scroll"><table class="table table-sm wh-table mb-0"><thead><tr>'
        html += '<th class="wh-name-col" rowspan="2">Player</th>'
        for (let hole = 0; hole < NUM_HOLES; hole++) {
            html += '<th>' + (startWordle() + hole) + '</th>'
        }
        html += '<th class="wh-total-col" rowspan="2">Total</th></tr><tr>'
        for (let hole = 0; hole < NUM_HOLES; hole++) {
            html += '<th class="wh-date">' + formatShortDay(addDays(startDate, hole)) + '</th>'
        }
        html += '</tr></thead><tbody>'
        round.results.forEach(function (person, row) {
            html += '<tr><th class="wh-name-col" scope="row" title="' + escapeHtml(personName(person)) + '">' + escapeHtml(personName(person)) + '</th>'
            for (let hole = 0; hole < NUM_HOLES; hole++) {
                html += '<td class="wh-cell" data-pid="' + person.person_id + '" data-hole="' + hole + '">'
                if (entering) {
                    html += '<input type="text" class="wh-input" autocomplete="off" spellcheck="false"'
                        + ' data-row="' + row + '" data-hole="' + hole + '" data-pid="' + person.person_id + '"'
                        + ' aria-label="' + escapeHtml(personName(person)) + ', ' + formatDay(addDays(startDate, hole)) + '">'
                }
                html += '</td>'
            }
            html += '<td class="wh-total" data-pid="' + person.person_id + '"></td></tr>'
        })
        html += '</tbody><tfoot><tr><th class="wh-name-col" scope="row">Mean</th>'
        for (let hole = 0; hole < NUM_HOLES; hole++) {
            html += '<td class="wh-mean" data-hole="' + hole + '"></td>'
        }
        html += '<td></td></tr><tr><th class="wh-name-col" scope="row">Solution</th>'
        for (let hole = 0; hole < NUM_HOLES; hole++) {
            html += '<td class="wh-word" data-hole="' + hole + '">'
            if (entering) {
                html += '<input type="text" class="wh-input wh-word-input" maxlength="12" autocomplete="off" spellcheck="false"'
                    + ' data-hole="' + hole + '" aria-label="Solution, ' + formatDay(addDays(startDate, hole)) + '">'
            }
            html += '</td>'
        }
        html += '<td></td></tr></tfoot></table></div>'
        $('#scorecard').html(html)

        // Remember where everything is so typing only has to touch the cells that change
        const $card = $('#scorecard')
        state.cells = {}
        state.inputs = []
        state.totalCells = {}
        state.meanCells = []
        state.wordCells = []
        $card.find('td.wh-cell').each(function () {
            const pid = $(this).data('pid')
            const hole = $(this).data('hole');
            (state.cells[pid] = state.cells[pid] || [])[hole] = this
        })
        $card.find('.wh-input[data-pid]').each(function () {
            const row = $(this).data('row');
            (state.inputs[row] = state.inputs[row] || [])[$(this).data('hole')] = this
        })
        $card.find('td.wh-total').each(function () {
            state.totalCells[$(this).data('pid')] = this
        })
        $card.find('td.wh-mean').each(function () {
            state.meanCells[$(this).data('hole')] = this
        })
        $card.find('td.wh-word').each(function () {
            state.wordCells[$(this).data('hole')] = this
        })
        updateScorecard()
    }

    // The mean of what has been entered for one day, or null
    function computedMean(hole) {
        let sum = 0
        let count = 0
        state.pids.forEach(function (pid) {
            const value = textToValue(state.draft[pid][hole])
            if (value !== null && !isNaN(value)) {
                sum += value
                count++
            }
        })
        return count ? {mean: sum / count, count: count} : null
    }

    function holeChanged(hole) {
        return state.pids.some(function (pid) {
            return state.draft[pid][hole] !== state.original[pid][hole]
        })
    }

    // The mean shown for a day: as stored, unless scores for that day have been changed here
    function meanText(hole) {
        const stored = state.round.mean_scores[startWordle() + hole]
        const computed = computedMean(hole)
        if (!holeChanged(hole) && stored !== undefined && stored !== null && stored !== '' && !isNaN(Number(stored))) {
            return Number(stored).toFixed(2)
        }
        return computed ? computed.mean.toFixed(2) : ''
    }

    // Total against par for a person, e.g. "+3", or '' if they have no scores
    function totalText(pid) {
        let sum = 0
        let count = 0
        state.draft[pid].forEach(function (text) {
            const value = textToValue(text)
            if (value !== null && !isNaN(value)) {
                sum += value - par()
                count++
            }
        })
        if (!count) {
            return ''
        }
        const total = Math.round(sum)
        return total > 0 ? '+' + total : String(total)
    }

    // Bring every cell in line with the draft
    function updateScorecard() {
        if (!state.round) {
            return
        }
        const entering = isEntering()
        state.pids.forEach(function (pid, row) {
            for (let hole = 0; hole < NUM_HOLES; hole++) {
                const td = state.cells[pid][hole]
                const text = state.draft[pid][hole]
                td.className = 'wh-cell ' + scoreClass(text, par()) + (text !== state.original[pid][hole] ? ' wh-dirty' : '')
                if (entering) {
                    const input = state.inputs[row][hole]
                    if (input.value !== text) {
                        input.value = text
                    }
                } else if (td.textContent !== displayText(text)) {
                    td.textContent = displayText(text)
                }
            }
            state.totalCells[pid].textContent = totalText(pid)
        })
        for (let hole = 0; hole < NUM_HOLES; hole++) {
            state.meanCells[hole].textContent = meanText(hole)
            const word = state.draftWords[hole]
            state.wordCells[hole].className = 'wh-word' + (word !== state.originalWords[hole] ? ' wh-dirty' : '')
            if (entering) {
                const input = state.wordCells[hole].querySelector('input')
                if (input.value !== word) {
                    input.value = word
                }
            } else {
                state.wordCells[hole].textContent = word
            }
        }
    }

    // Move to another cell of the scorecard; after the last person, carry on with the next day
    function focusCell(row, hole) {
        const people = state.pids.length
        if (row >= people) {
            row = 0
            hole++
        } else if (row < 0) {
            row = people - 1
            hole--
        }
        if (hole < 0 || hole >= NUM_HOLES || !state.inputs[row] || !state.inputs[row][hole]) {
            return
        }
        state.inputs[row][hole].focus()
    }

    // Typing a score
    $('#scorecard').on('input', '.wh-input[data-pid]', function () {
        const pid = $(this).data('pid')
        const hole = $(this).data('hole')
        const typed = this.value.toUpperCase().replace(/\s/g, '')
        let text
        if (typed === '') {
            text = ''
        } else if (/^[1-6X-]$/.test(typed.slice(-1))) {
            text = typed.slice(-1)
        } else {
            this.value = state.draft[pid][hole]   // not a score: put back what was there
            return
        }
        this.value = text
        state.draft[pid][hole] = text
        draftChanged()
        if (text !== '') {
            focusCell($(this).data('row') + 1, hole)
        }
    })

    $('#scorecard').on('keydown', '.wh-input[data-pid]', function (e) {
        const row = $(this).data('row')
        const hole = $(this).data('hole')
        switch (e.key) {
            case 'ArrowDown':
            case 'Enter':
                e.preventDefault()
                focusCell(e.shiftKey ? row - 1 : row + 1, hole)
                break
            case 'ArrowUp':
                e.preventDefault()
                focusCell(row - 1, hole)
                break
            case 'ArrowLeft':
                e.preventDefault()
                focusCell(row, hole - 1)
                break
            case 'ArrowRight':
                e.preventDefault()
                focusCell(row, hole + 1)
                break
            case 'Backspace':
            case 'Delete':
                e.preventDefault()
                this.value = ''
                state.draft[$(this).data('pid')][hole] = ''
                draftChanged()
                break
        }
    })

    // Typing a solution
    $('#scorecard').on('input', '.wh-word-input', function () {
        const hole = $(this).data('hole')
        const word = this.value.toUpperCase().replace(/[^A-Z]/g, '')
        this.value = word
        state.draftWords[hole] = word
        draftChanged()
    })

    $('#scorecard').on('keydown', '.wh-word-input', function (e) {
        if (e.key === 'Enter' || e.key === 'ArrowRight' && this.selectionStart === this.value.length) {
            e.preventDefault()
            $('#scorecard .wh-word-input[data-hole="' + ($(this).data('hole') + 1) + '"]').trigger('focus')
        } else if (e.key === 'ArrowLeft' && this.selectionStart === 0) {
            e.preventDefault()
            $('#scorecard .wh-word-input[data-hole="' + ($(this).data('hole') - 1) + '"]').trigger('focus')
        }
    })

    // Pick the whole score when a cell is entered so that typing replaces it
    $('#scorecard').on('focusin', '.wh-input', function () {
        this.select()
    })

    // ---------------------------------------------------------------- daily entry

    const SCORE_CHOICES = ['1', '2', '3', '4', '5', '6', 'X', '-']

    // Today's day of the round if it is running, else the first day that is missing a score
    function defaultDay() {
        const index = Math.round((todayUtc().getTime() - parseDate(state.round.start_date).getTime()) / DAY_MS)
        if (index >= 0 && index < NUM_HOLES) {
            return index + 1
        }
        for (let hole = 0; hole < NUM_HOLES; hole++) {
            if (state.pids.some(function (pid) { return state.draft[pid][hole] === '' })) {
                return hole + 1
            }
        }
        return 1
    }

    function renderDaily() {
        const show = isEntering()
        $('#daily_card').prop('hidden', !show)
        if (!show) {
            return
        }
        const startDate = parseDate(state.round.start_date)
        const select = $('#daily_hole').empty()
        for (let hole = 0; hole < NUM_HOLES; hole++) {
            select.append($('<option>').val(hole + 1)
                .text('Day ' + (hole + 1) + ' · Wordle ' + (startWordle() + hole) + ' · ' + formatDay(addDays(startDate, hole))))
        }
        select.val(state.dailyHole)

        const people = $('#daily_people').empty()
        state.round.results.forEach(function (person) {
            const pid = person.person_id
            const row = $('<div class="wh-daily-row">').attr('data-pid', pid)
            row.append($('<div class="wh-daily-name">').text(personName(person)))
            const group = $('<div class="btn-group" role="group">').attr('aria-label', 'Score for ' + personName(person))
            SCORE_CHOICES.forEach(function (choice) {
                const id = 'daily_' + pid + '_' + (choice === '-' ? 'none' : choice)
                group.append($('<input type="radio" class="btn-check" autocomplete="off">')
                    .attr({id: id, name: 'daily_' + pid, value: choice, 'data-pid': pid}))
                group.append($('<label class="btn btn-outline-secondary">').attr('for', id)
                    .addClass(scoreClass(choice, par()) + '-btn').text(displayText(choice)))
            })
            row.append(group)
            row.append($('<button type="button" class="btn btn-sm btn-link text-muted wh-clear" title="Clear this score">').attr('data-pid', pid).text('clear'))
            people.append(row)
        })
        syncDaily()
    }

    // Show the draft for the chosen day in the daily entry
    function syncDaily() {
        if (!isEntering()) {
            return
        }
        const hole = state.dailyHole - 1
        state.pids.forEach(function (pid) {
            const text = state.draft[pid][hole]
            $('#daily_people input[name="daily_' + pid + '"]').each(function () {
                this.checked = (this.value === text)
            })
        })
        const word = state.draftWords[hole]
        const wordInput = $('#daily_word')
        if (wordInput.val() !== word) {
            wordInput.val(word)
        }
        const computed = computedMean(hole)
        $('#daily_mean').text(computed ? computed.mean.toFixed(2) + ' (' + computed.count + (computed.count === 1 ? ' score)' : ' scores)') : '-')
    }

    $('#daily_hole').on('change', function () {
        state.dailyHole = Number($(this).val())
        syncDaily()
    })

    $('#daily_people').on('change', 'input.btn-check', function () {
        state.draft[$(this).data('pid')][state.dailyHole - 1] = this.value
        draftChanged()
    })

    $('#daily_people').on('click', '.wh-clear', function () {
        state.draft[$(this).data('pid')][state.dailyHole - 1] = ''
        draftChanged()
    })

    $('#daily_word').on('input', function () {
        const word = this.value.toUpperCase().replace(/[^A-Z]/g, '')
        this.value = word
        state.draftWords[state.dailyHole - 1] = word
        draftChanged()
    })

    // ---------------------------------------------------------------- saving

    // Something in the draft changed: bring the scorecard, daily entry and save bar up to date
    function draftChanged() {
        updateScorecard()
        syncDaily()
        updateSaveBar()
    }

    function updateSaveBar() {
        const count = changeCount()
        $('#wh_savebar').prop('hidden', count === 0)
        $('body').toggleClass('wh-has-savebar', count > 0)
        $('#wh_savebar_text').text(count + (count === 1 ? ' change' : ' changes') + ' not saved yet')
        $('#wh_save, #wh_discard').prop('disabled', state.saving)
    }

    $('#wh_discard').on('click', function () {
        resetDraft()
        renderAll()
    })

    $('#wh_save').on('click', function () {
        if (state.saving || changeCount() === 0) {
            return
        }
        const form_data = new FormData()
        form_data.append('group_id', current_group_id)
        form_data.append('round_id', state.round.round_id)
        form_data.append('payload', JSON.stringify(changes()))
        state.saving = true
        updateSaveBar()
        $.ajax({
            type: 'post',
            url: './save_entries.php',
            contentType: false,
            processData: false,
            data: form_data,
            dataType: 'json',
            success: function (data) {
                state.saving = false
                // The server sends the round back with its totals and means worked out
                const day = state.dailyHole
                all_rounds_data[current_round_id] = data.round
                show_round(current_round_id)
                state.dailyHole = day
                renderDaily()
            },
            error: function (xhr) {
                state.saving = false
                updateSaveBar()
                const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'The changes could not be saved.'
                if (xhr.status === 401) {
                    // The login has run out: keep what has been typed and ask for the password again
                    state.canEdit = false
                    renderAll()
                    openLogin('Your login has expired. Log in again, then press Save changes. Nothing you typed has been lost.')
                } else {
                    alert(message + '\n\nYour changes have not been saved.')
                }
            }
        })
    })

    // ---------------------------------------------------------------- logging in and out

    function showModalError(selector, message) {
        $(selector).text(message).prop('hidden', !message)
    }

    function openLogin(message) {
        $('#login_group_name').text(current_group_name)
        $('#login_password').val('')
        showModalError('#login_error', message || '')
        bootstrap.Modal.getOrCreateInstance(document.getElementById('login_modal')).show()
    }

    $('#login_modal').on('shown.bs.modal', function () {
        $('#login_password').trigger('focus')
    })

    $('#login_form').on('submit', function (e) {
        e.preventDefault()
        const form_data = new FormData()
        form_data.append('group_id', current_group_id)
        form_data.append('password', $('#login_password').val())
        $.ajax({
            type: 'post',
            url: './login.php',
            contentType: false,
            processData: false,
            data: form_data,
            dataType: 'json',
            success: function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('login_modal')).hide()
                state.canEdit = true
                state.editing = true
                renderAll()
            },
            error: function (xhr) {
                $('#login_password').val('').trigger('focus')
                showModalError('#login_error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not log in.')
            }
        })
    })

    function logout() {
        if (!window.wh_confirm_leave()) {
            return
        }
        $.ajax({
            type: 'post',
            url: './logout.php',
            dataType: 'json',
            success: function () {
                state.canEdit = false
                state.editing = false
                resetDraft()
                renderAll()
            },
            error: report_ajax_error
        })
    }

    function openChangePassword() {
        $('#password_form')[0].reset()
        showModalError('#password_error', '')
        bootstrap.Modal.getOrCreateInstance(document.getElementById('password_modal')).show()
    }

    $('#password_modal').on('shown.bs.modal', function () {
        $('#password_current').trigger('focus')
    })

    $('#password_form').on('submit', function (e) {
        e.preventDefault()
        if ($('#password_new').val() !== $('#password_again').val()) {
            showModalError('#password_error', 'The two new passwords are not the same.')
            return
        }
        const form_data = new FormData()
        form_data.append('group_id', current_group_id)
        form_data.append('current_password', $('#password_current').val())
        form_data.append('new_password', $('#password_new').val())
        $.ajax({
            type: 'post',
            url: './change_password.php',
            contentType: false,
            processData: false,
            data: form_data,
            dataType: 'json',
            success: function (data) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('password_modal')).hide()
                alert(data.message)
            },
            error: function (xhr) {
                showModalError('#password_error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The password could not be changed.')
            }
        })
    })

    // ---------------------------------------------------------------- a new round

    let wordleTyped = false

    function openNewRound() {
        if (!window.wh_confirm_leave()) {
            return
        }
        // The newest round is first in the list
        const latest = all_rounds_data.length ? all_rounds_data[0] : null
        const startDate = latest ? addDays(parseDate(latest.start_date), DAYS_BETWEEN_ROUNDS) : todayUtc()
        wordleTyped = false
        $('#round_num').val(latest ? Number(latest.round_num) + 1 : 1)
        $('#round_start_date').val(isoDate(startDate))
        $('#round_start_wordle').val(wordleNumberForDate(startDate))
        $('#round_par').val(latest ? latest.par : 4)
        $('#round_new_players').val('')
        showModalError('#round_error', '')

        // Everybody who has played in the group; those in the latest round start ticked
        const playing = {}
        if (latest) {
            latest.results.forEach(function (person) {
                playing[person.person_id] = true
            })
        }
        const list = $('#round_people').empty()
        group_people.forEach(function (person) {
            const id = 'round_person_' + person.id
            const line = $('<div class="form-check">')
            line.append($('<input type="checkbox" class="form-check-input round-person">').attr('id', id).val(person.id)
                .prop('checked', latest ? !!playing[person.id] : true))
            line.append($('<label class="form-check-label">').attr('for', id).text(personName(person)))
            list.append(line)
        })
        if (group_people.length === 0) {
            list.append($('<span class="text-muted small">').text('Nobody yet: add the players on the right.'))
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('round_modal')).show()
    }

    // The first Wordle number follows from the date, unless it has been typed in
    $('#round_start_wordle').on('input', function () {
        wordleTyped = true
    })

    $('#round_start_date').on('change', function () {
        if (!wordleTyped && this.value) {
            $('#round_start_wordle').val(wordleNumberForDate(new Date(this.value + 'T00:00:00Z')))
        }
    })

    $('#round_form').on('submit', function (e) {
        e.preventDefault()
        const ids = $('#round_people .round-person:checked').map(function () {
            return Number(this.value)
        }).get()
        const form_data = new FormData()
        form_data.append('group_id', current_group_id)
        form_data.append('round_num', $('#round_num').val())
        form_data.append('start_date', $('#round_start_date').val())
        form_data.append('start_wordle', $('#round_start_wordle').val())
        form_data.append('par', $('#round_par').val())
        form_data.append('person_ids', JSON.stringify(ids))
        form_data.append('new_players', JSON.stringify($('#round_new_players').val().split('\n')))
        $.ajax({
            type: 'post',
            url: './add_round.php',
            contentType: false,
            processData: false,
            data: form_data,
            dataType: 'json',
            success: function (data) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('round_modal')).hide()
                // Reload the group so the new round and any new players are in the lists, and start entering scores
                state.editing = true
                load_wordle_data(current_group_id, data.round.round_id)
            },
            error: function (xhr) {
                if (xhr.status === 401) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('round_modal')).hide()
                    state.canEdit = false
                    renderAll()
                    openLogin('Your login has expired. Log in again, then create the round.')
                    return
                }
                showModalError('#round_error', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The round could not be created.')
            }
        })
    })
})()
