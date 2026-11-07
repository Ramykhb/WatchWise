export const toCalendarTime = (date: Date) => date.toISOString().replace(/[-:]|\.\d{3}/g, '')

/** `start` and `end` are UTC times like "20261107T183000Z", or days like "20261120" for an all-day event. */
export function googleCalendarUrl(text: string, start: string, end: string) {
  const params = new URLSearchParams({ action: 'TEMPLATE', text, dates: `${start}/${end}` })
  return `https://calendar.google.com/calendar/render?${params}`
}

export function outlookCalendarUrl(title: string, start: Date, end: Date) {
  const params = new URLSearchParams({
    path: '/calendar/action/compose',
    rru: 'addevent',
    subject: title,
    startdt: start.toISOString(),
    enddt: end.toISOString(),
    allday: 'false',
  })
  return `https://outlook.live.com/calendar/0/deeplink/compose?${params}`
}

const escapeText = (text: string) => text.replace(/\\/g, '\\\\').replace(/\r\n|\r|\n/g, '\\n').replace(/[,;]/g, '\\$&')

// iCalendar folds content lines at 75 UTF-8 bytes, without splitting a character.
function foldLine(line: string) {
  const encoder = new TextEncoder()
  let folded = ''
  let bytes = 0
  for (const character of line) {
    const size = encoder.encode(character).length
    if (bytes + size > 75) {
      folded += '\r\n '
      bytes = 1
    }
    folded += character
    bytes += size
  }
  return folded
}

export function watchEventIcs(title: string, start: Date, end: Date) {
  return [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//WatchWise//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    `UID:${crypto.randomUUID()}@watchwise`,
    `DTSTAMP:${toCalendarTime(new Date())}`,
    `DTSTART:${toCalendarTime(start)}`,
    `DTEND:${toCalendarTime(end)}`,
    `SUMMARY:${escapeText(title)}`,
    'END:VEVENT',
    'END:VCALENDAR',
    '',
  ].map(foldLine).join('\r\n')
}

export function downloadWatchEvent(title: string, start: Date, end: Date) {
  const url = URL.createObjectURL(new Blob([watchEventIcs(title, start, end)], { type: 'text/calendar;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = 'watchwise-event.ics'
  document.body.append(link)
  link.click()
  link.remove()
  // Allow the browser to start reading the file before releasing the object URL.
  window.setTimeout(() => URL.revokeObjectURL(url), 60_000)
}
