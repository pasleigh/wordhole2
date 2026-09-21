// Sending the chart to a chat as a picture. A website cannot post into a WhatsApp or Messenger group by
// itself, so this hands the picture to the device instead:
//   1. phones and tablets: the share sheet, where you choose WhatsApp, Messenger, ... and the group
//   2. otherwise: copy the picture to the clipboard, to paste into the chat
//   3. otherwise: save it as a PNG file to attach
// Sharing and copying need a secure connection (https, or localhost); without one the picture is saved.
// load_xlsx.js calls wh_prepare_share each time the chart is drawn, and wh_show_share to show or hide the button.
(function () {
    'use strict'

    const WIDTH = 1200        // the picture is drawn at a fixed landscape size that reads well on a phone ...
    const HEIGHT = 720
    const SCALE = 2           // ... and at twice the resolution so it stays sharp when zoomed

    const state = {
        picture: null,        // promise of the PNG for the chart on show, made ahead so the share sheet opens at once
        fileName: 'wordhole-chart.png',
        title: 'Wordhole',
        messageTimer: null
    }

    function dummyFile() {
        return new File(['x'], 'chart.png', {type: 'image/png'})
    }

    function canShareFile(file) {
        return !!(navigator.share && navigator.canShare && navigator.canShare({files: [file]}))
    }

    function canCopyImage() {
        return !!(window.isSecureContext && navigator.clipboard && navigator.clipboard.write && window.ClipboardItem)
    }

    // Name the button after what it will do on this device
    function labelButton() {
        let icon = 'bi-download'
        let label = 'Save chart'
        let hint = 'Saves the chart as a picture you can attach to a chat'
        if (canShareFile(dummyFile())) {
            icon = 'bi-share'
            label = 'Share chart'
            hint = 'Choose WhatsApp, Messenger or another app to send the chart picture'
        } else if (canCopyImage()) {
            icon = 'bi-clipboard'
            label = 'Copy chart'
            hint = 'Copies the chart as a picture to paste into WhatsApp or Messenger'
        }
        $('#share_chart').html('<i class="bi ' + icon + '"></i> ' + label).attr('title', hint)
    }

    // The chart as a PNG, drawn at a fixed size on a white background
    function chartToPng(chart) {
        return new Promise(function (resolve, reject) {
            const svg = chart.getSVG({
                chart: {width: WIDTH, height: HEIGHT, backgroundColor: '#ffffff'},
                credits: {enabled: false},
                exporting: {enabled: false}
            })
            const image = new Image()
            image.onload = function () {
                const canvas = document.createElement('canvas')
                canvas.width = WIDTH * SCALE
                canvas.height = HEIGHT * SCALE
                const context = canvas.getContext('2d')
                context.fillStyle = '#ffffff'
                context.fillRect(0, 0, canvas.width, canvas.height)
                context.drawImage(image, 0, 0, canvas.width, canvas.height)
                canvas.toBlob(function (blob) {
                    if (blob) {
                        resolve(blob)
                    } else {
                        reject(new Error('The picture could not be made.'))
                    }
                }, 'image/png')
            }
            image.onerror = function () {
                reject(new Error('The chart could not be turned into a picture.'))
            }
            image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg)
        })
    }

    // Called when a chart has been drawn: get its picture ready
    window.wh_prepare_share = function (chart, fileName, title) {
        state.fileName = fileName
        state.title = title
        state.picture = null
        // let the chart finish drawing first
        setTimeout(function () {
            state.picture = chartToPng(chart)
            state.picture.catch(function () {
                // reported if the button is pressed
            })
        }, 300)
    }

    window.wh_show_share = function (show) {
        $('#share_row').prop('hidden', !show)
        if (show) {
            labelButton()
        } else {
            showMessage('')
        }
    }

    function showMessage(text) {
        clearTimeout(state.messageTimer)
        $('#share_message').text(text)
        if (text) {
            state.messageTimer = setTimeout(function () {
                $('#share_message').text('')
            }, 12000)
        }
    }

    function savePicture(blob) {
        const link = document.createElement('a')
        link.href = URL.createObjectURL(blob)
        link.download = state.fileName
        document.body.appendChild(link)
        link.click()
        link.remove()
        setTimeout(function () {
            URL.revokeObjectURL(link.href)
        }, 10000)
    }

    $('#share_chart').on('click', async function () {
        const button = $(this).prop('disabled', true)
        try {
            if (!state.picture) {
                showMessage('The chart is not ready yet. Try again in a moment.')
                return
            }
            const blob = await state.picture
            const file = new File([blob], state.fileName, {type: 'image/png'})

            if (canShareFile(file)) {
                try {
                    await navigator.share({files: [file], title: state.title, text: state.title})
                    showMessage('')
                } catch (error) {
                    // closing the share sheet without sending is not an error
                    if (error.name !== 'AbortError') {
                        throw error
                    }
                }
                return
            }

            if (canCopyImage()) {
                try {
                    await navigator.clipboard.write([new window.ClipboardItem({'image/png': blob})])
                    showMessage('Chart copied. Paste it into your WhatsApp or Messenger chat.')
                    return
                } catch (error) {
                    // not allowed to copy here: save it instead
                }
            }

            savePicture(blob)
            showMessage(window.isSecureContext
                ? 'Chart saved as ' + state.fileName + '. Attach it to your chat.'
                : 'Sharing needs a secure (https) connection, so the chart was saved as ' + state.fileName + ' instead. Attach it to your chat.')
        } catch (error) {
            showMessage('Sorry, the chart could not be shared: ' + error.message)
        } finally {
            button.prop('disabled', false)
        }
    })
})()
