export type NightEdit = { title: string; date: string; time: string; location: string; meetingLink: string; note: string }

export function nightEditPayload(fields: NightEdit, originalDate: string, originalTime: string, now = Date.now()) {
  const title = fields.title.trim()
  if (!title || title.length > 80) throw new Error('Use a title of 1–80 characters.')
  const when = new Date(`${fields.date}T${fields.time}`)
  if (!fields.date || !fields.time || !Number.isFinite(when.getTime())) throw new Error('Choose a valid date and time.')
  const changedTime = fields.date !== originalDate || fields.time !== originalTime
  if (changedTime && when.getTime() <= now) throw new Error('Choose a time in the future.')
  const link = fields.meetingLink.trim()
  if (link) {
    try { if (!['https:', 'http:'].includes(new URL(link).protocol)) throw new Error() }
    catch { throw new Error('Use a full meeting URL starting with https:// or http://.') }
  }
  return {
    title, location: fields.location.trim() || null, meeting_link: link || null, note: fields.note.trim() || null,
    ...(changedTime ? { scheduled_at: when.toISOString() } : {}),
  }
}
