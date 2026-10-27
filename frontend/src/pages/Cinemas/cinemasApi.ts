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

type NearbyCinemasResponse = {
  cinemas?: Array<{
    id: string
    name: string
    address: string
    latitude: number
    longitude: number
    distance_meters: number
    website?: string | null
  }>
}

export async function fetchNearbyCinemas(
  latitude: number,
  longitude: number,
  signal?: AbortSignal,
): Promise<NearbyCinema[]> {
  let data: NearbyCinemasResponse
  try {
    const response = await api.get<NearbyCinemasResponse>('/nearby-cinemas', {
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

  return (data.cinemas ?? []).map((cinema) => ({
    id: cinema.id,
    name: cinema.name,
    address: cinema.address,
    latitude: cinema.latitude,
    longitude: cinema.longitude,
    distanceMeters: cinema.distance_meters,
    website: cinema.website ?? undefined,
  }))
}

export function formatDistance(meters: number): string {
  return meters < 1000 ? `${Math.round(meters)} m away` : `${(meters / 1000).toFixed(1)} km away`
}

