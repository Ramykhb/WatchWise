import type { Night } from './nightsData.ts'
import type { NightDetail } from './nightDetailData.ts'

/** The night time is local, matching the date and time shown on its page. */
export function movieNightEvent(night: Pick<Night, 'title' | 'date' | 'time'>, detail: Pick<NightDetail, 'host' | 'link'>, where: string) {
  const start = new Date(`${night.date}T${night.time}:00`)
  return {
    title: `${night.title} — WatchWise movie night`,
    start,
    end: new Date(start.getTime() + 3 * 60 * 60 * 1000),
    details: {
      description: [`Hosted by ${detail.host.name}`, detail.link && `Online: ${detail.link}`].filter(Boolean).join('\n'),
      location: where,
    },
  }
}
