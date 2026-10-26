import axios from 'axios'
import { api } from '../../lib/api.ts'

export type NearbyCinema = {
  id: string
  name: string
  address: string
  latitude: number
  longitude: number
  distanceMeters: number
  website?: string
}

type GeoapifyFeature = {
  id?: string
  geometry?: { coordinates?: [number, number] }
  properties?: {
    place_id?: string
    name?: string
    formatted?: string
    address_line1?: string
    address_line2?: string
    name_en?: string
    website?: string
    datasource?: { raw?: Record<string, unknown> }
    lat?: number
    lon?: number
    distance?: number
  }
}

type GeoapifyResponse = {
  features?: GeoapifyFeature[]
}

function distanceBetweenMeters(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const earthRadiusMeters = 6_371_000
  const toRadians = (degrees: number) => (degrees * Math.PI) / 180
  const latitudeDelta = toRadians(lat2 - lat1)
  const longitudeDelta = toRadians(lon2 - lon1)
  const haversine =
    Math.sin(latitudeDelta / 2) ** 2 +
    Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2)) * Math.sin(longitudeDelta / 2) ** 2
  return earthRadiusMeters * 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine))
}

export async function fetchNearbyCinemas(
  latitude: number,
  longitude: number,
  signal?: AbortSignal,
): Promise<NearbyCinema[]> {
  let data: GeoapifyResponse
  try {
    const response = await api.get<GeoapifyResponse>('/nearby-cinemas', {
      params: { latitude, longitude },
      signal,
    })
    data = response.data
  } catch (error) {
    if (signal?.aborted) throw error
    const message = axios.isAxiosError<{ message?: string }>(error)
      ? error.response?.data?.message
      : null
    throw new Error(message || 'The nearby cinema service could not be reached.')
  }

  console.log(`Geoapify returned ${data.features?.length ?? 0} cinema features:`, data.features)

  const cinemas = (data.features ?? []).flatMap((feature, index) => {
    const properties = feature.properties
    const resultLongitude = feature.geometry?.coordinates?.[0] ?? properties?.lon
    const resultLatitude = feature.geometry?.coordinates?.[1] ?? properties?.lat
    const rawTags = properties?.datasource?.raw
    const englishName = rawTags?.['name:en']
    const latinName = rawTags?.['name:latin']
    const rawWebsite = rawTags?.website ?? rawTags?.['contact:website']
    const name =
      (typeof englishName === 'string' && englishName) ||
      properties?.name_en ||
      (typeof latinName === 'string' && latinName) ||
      properties?.name
    if (!properties || !name || resultLatitude === undefined || resultLongitude === undefined) return []

    return [{
      id: properties.place_id ?? feature.id ?? `${name}-${index}`,
      name,
      address: properties.formatted ?? ([properties.address_line1, properties.address_line2].filter(Boolean).join(', ') || 'Address unavailable'),
      latitude: resultLatitude,
      longitude: resultLongitude,
      distanceMeters: properties.distance ?? distanceBetweenMeters(latitude, longitude, resultLatitude, resultLongitude),
      website: properties.website ?? (typeof rawWebsite === 'string' ? rawWebsite : undefined),
    }]
  })
  console.log(`Parsed ${cinemas.length} nearby Geoapify cinemas with map coordinates:`, cinemas)
  return cinemas.sort((first, second) => first.distanceMeters - second.distanceMeters)
}

export function formatDistance(meters: number): string {
  return meters < 1000 ? `${Math.round(meters)} m away` : `${(meters / 1000).toFixed(1)} km away`
}

