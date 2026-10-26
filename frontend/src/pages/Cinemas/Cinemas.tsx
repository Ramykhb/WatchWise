import { useCallback, useEffect, useRef, useState } from 'react'
import { ExternalLink, Navigation, X } from 'lucide-react'
import mapboxgl from 'mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'
import AppNav from '../../components/AppNav/AppNav.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import pinIcon from '../../assets/cinemas/icon-pin.svg'
import filmIcon from '../../assets/cinemas/icon-film.svg'
import locateIcon from '../../assets/cinemas/icon-locate.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { fetchNearbyCinemas, formatDistance, type NearbyCinema } from './cinemasApi.ts'
import styles from './Cinemas.module.css'

const MAPBOX_ACCESS_TOKEN = import.meta.env.VITE_MAPBOX_ACCESS_TOKEN
const MAPBOX_STYLE_URL = import.meta.env.VITE_MAPBOX_STYLE_URL || 'mapbox://styles/mapbox/streets-v12'

function createCinemaMarker(cinema: NearbyCinema, colorClass: string, onSelect: () => void) {
  const marker = document.createElement('button')
  marker.type = 'button'
  marker.className = `${styles.cinemaPill} ${colorClass}`
  marker.setAttribute('aria-label', `Show details for ${cinema.name}`)
  marker.setAttribute('aria-pressed', 'false')
  marker.addEventListener('click', onSelect)

  const icon = document.createElement('img')
  icon.src = filmIcon
  icon.alt = ''
  icon.setAttribute('aria-hidden', 'true')
  marker.append(icon)

  const label = document.createElement('span')
  label.textContent = cinema.name
  marker.append(label)
  return marker
}

function safeWebsiteUrl(website: string): string | null {
  try {
    const url = new URL(website.startsWith('http://') || website.startsWith('https://') ? website : `https://${website}`)
    return ['http:', 'https:'].includes(url.protocol) ? url.toString() : null
  } catch {
    return null
  }
}

export default function Cinemas() {
  const pageRef = useRef<HTMLDivElement>(null)
  const mapContainerRef = useRef<HTMLDivElement>(null)
  const mapRef = useRef<mapboxgl.Map | null>(null)
  const cinemaMarkersRef = useRef<mapboxgl.Marker[]>([])
  const cinemaMarkerElementsRef = useRef<Map<string, HTMLButtonElement>>(new Map())
  const requestedLocation = useRef(false)
  useEntranceAnimation(pageRef)

  const [currentLocation, setCurrentLocation] = useState<[number, number] | null>(null)
  const [locating, setLocating] = useState(true)
  const [locationNote, setLocationNote] = useState('')
  const [nearbyCinemas, setNearbyCinemas] = useState<NearbyCinema[]>([])
  const [loadingCinemas, setLoadingCinemas] = useState(false)
  const [cinemaSearchNote, setCinemaSearchNote] = useState('')
  const [selectedCinemaId, setSelectedCinemaId] = useState<string | null>(null)

  const selectedCinema = nearbyCinemas.find((cinema) => cinema.id === selectedCinemaId) ?? null

  useEffect(() => {
    if (!currentLocation || !mapContainerRef.current || !MAPBOX_ACCESS_TOKEN) return

    const [latitude, longitude] = currentLocation
    mapboxgl.accessToken = MAPBOX_ACCESS_TOKEN
    const map = new mapboxgl.Map({
      container: mapContainerRef.current,
      style: MAPBOX_STYLE_URL,
      center: [longitude, latitude],
      zoom: 14,
      customAttribution: '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>',
    })
    mapRef.current = map
    const userDot = document.createElement('div')
    userDot.className = styles.userLocationDot
    userDot.setAttribute('aria-label', 'Your location')
    new mapboxgl.Marker({ element: userDot, anchor: 'center' }).setLngLat([longitude, latitude]).addTo(map)
    const updateLocationHalo = () => {
      const diameter = Math.max(44, Math.min(180, 120 * 2 ** ((map.getZoom() - 14) / 4)))
      userDot.style.setProperty('--location-halo-size', `${diameter}px`)
    }
    updateLocationHalo()
    map.on('zoom', updateLocationHalo)
    map.addControl(new mapboxgl.NavigationControl(), 'bottom-right')

    return () => {
      map.off('zoom', updateLocationHalo)
      cinemaMarkersRef.current.forEach((marker) => marker.remove())
      cinemaMarkersRef.current = []
      cinemaMarkerElementsRef.current.clear()
      mapRef.current = null
      map.remove()
    }
  }, [currentLocation])

  useEffect(() => {
    if (!currentLocation) return

    const controller = new AbortController()
    const [latitude, longitude] = currentLocation
    setNearbyCinemas([])
    setSelectedCinemaId(null)
    setLoadingCinemas(true)
    setCinemaSearchNote('')
    fetchNearbyCinemas(latitude, longitude, controller.signal)
      .then(setNearbyCinemas)
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === 'AbortError') return
        setNearbyCinemas([])
        setCinemaSearchNote(error instanceof Error ? error.message : 'Nearby cinema data is unavailable right now.')
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoadingCinemas(false)
      })

    return () => controller.abort()
  }, [currentLocation])

  useEffect(() => {
    const map = mapRef.current
    if (!map || !currentLocation) return

    console.log(`Adding ${nearbyCinemas.length} nearby cinema markers to Mapbox`)
    cinemaMarkersRef.current.forEach((marker) => marker.remove())
    cinemaMarkerElementsRef.current.clear()
    const pillColors = [styles.cinemaPillNavy, styles.cinemaPillCoral, styles.cinemaPillYellow]
    cinemaMarkersRef.current = nearbyCinemas.map((cinema, index) => {
      const element = createCinemaMarker(cinema, pillColors[index % pillColors.length], () => setSelectedCinemaId(cinema.id))
      cinemaMarkerElementsRef.current.set(cinema.id, element)
      // Mapbox owns the wrapper's transform; the inner button can scale freely.
      const wrapper = document.createElement('div')
      wrapper.className = styles.cinemaMarkerAnchor
      wrapper.append(element)
      return new mapboxgl.Marker({ element: wrapper, anchor: 'center' })
        .setLngLat([cinema.longitude, cinema.latitude])
        .addTo(map)
    })

    const updatePillScale = () => {
      // Keep pills small even at street level, and smaller still when zooming out.
      const scale = Math.max(0.36, Math.min(0.84, 0.36 * 1.18 ** (map.getZoom() - 10)))
      cinemaMarkerElementsRef.current.forEach((element) => {
        element.style.setProperty('--map-zoom-scale', String(scale))
      })
    }
    updatePillScale()
    map.on('zoom', updatePillScale)

    if (nearbyCinemas.length > 0) {
      const [latitude, longitude] = currentLocation
      const bounds = new mapboxgl.LngLatBounds([longitude, latitude], [longitude, latitude])
      nearbyCinemas.forEach((cinema) => bounds.extend([cinema.longitude, cinema.latitude]))
      map.fitBounds(bounds, { padding: 72, maxZoom: 14, duration: 500 })
    }

    return () => {
      map.off('zoom', updatePillScale)
      cinemaMarkersRef.current.forEach((marker) => marker.remove())
      cinemaMarkersRef.current = []
      cinemaMarkerElementsRef.current.clear()
    }
  }, [currentLocation, nearbyCinemas])

  useEffect(() => {
    cinemaMarkerElementsRef.current.forEach((element, id) => {
      const selected = id === selectedCinemaId
      element.classList.toggle(styles.cinemaPillSelected, selected)
      element.setAttribute('aria-pressed', String(selected))
    })
  }, [selectedCinemaId, nearbyCinemas])

  const selectCinemaFromList = (cinema: NearbyCinema) => {
    setSelectedCinemaId(cinema.id)
    const map = mapRef.current
    if (!map) return

    map.flyTo({
      center: [cinema.longitude, cinema.latitude],
      zoom: Math.max(map.getZoom(), 15),
      offset: [0, 80],
      duration: 1000,
    })
  }

  const useMyLocation = useCallback(() => {
    if (!navigator.geolocation) {
      setLocating(false)
      setLocationNote("This browser can't share your location.")
      return
    }
    setLocating(true)
    setLocationNote('')
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setLocating(false)
        setCurrentLocation([position.coords.latitude, position.coords.longitude])
      },
      () => {
        setLocating(false)
        setCurrentLocation(null)
        setLocationNote('Enable location access in your browser settings to use the nearby cinemas map, then try again.')
      },
      { timeout: 10000 },
    )
  }, [])

  useEffect(() => {
    if (requestedLocation.current) return
    requestedLocation.current = true
    useMyLocation()
  }, [useMyLocation])

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main className={styles.body}>
        {currentLocation ? (
          <>
        <section className={styles.panel} aria-labelledby="cinemas-title" data-enter data-enter-delay="0.05">
          <SplitHeadline id="cinemas-title" className={styles.title} lines={[{ text: 'Cinemas near you' }]} />
          <p className={styles.lead}>Discover cinemas around you. Select a cinema to see its details and get directions.</p>

          <div className={styles.location}>
            <img src={pinIcon} width={18} height={18} alt="" />
            <span className={styles.place}>Your current location</span>
            <button type="button" className={styles.locate} onClick={useMyLocation} disabled={locating}>
              <img src={locateIcon} width={16} height={16} alt="" />
              {locating ? 'Locating…' : 'Use my location'}
            </button>
          </div>
          <p className={styles.note} role="status">
            {loadingCinemas
              ? 'Searching for nearby cinemas…'
              : cinemaSearchNote || `${nearbyCinemas.length} nearby cinemas shown on the map.`}
          </p>
          {!loadingCinemas && !cinemaSearchNote && (
            <p className={styles.note}>
              Powered by <a href="https://www.geoapify.com/">Geoapify</a> · © OpenStreetMap contributors
            </p>
          )}
          {locationNote && (
            <p className={styles.note} role="status">
              {locationNote}
            </p>
          )}

          <ul className={styles.list} aria-label="Nearby cinemas" aria-busy={loadingCinemas} data-lenis-prevent>
            {nearbyCinemas.map((cinema) => (
              <li key={cinema.id}>
                <button
                  type="button"
                  className={`${styles.card} ${styles.cardHead}`}
                  aria-pressed={selectedCinemaId === cinema.id}
                  onClick={() => selectCinemaFromList(cinema)}
                >
                  <img className={styles.cardIcon} src={filmIcon} width={36} height={36} alt="" />
                  <span className={styles.cardName}>
                    <span className={styles.cardTitle}>{cinema.name}</span>
                    <span className={styles.cardMeta}>{cinema.address}</span>
                    <span className={styles.cardDistance}>{formatDistance(cinema.distanceMeters)}</span>
                  </span>
                </button>
              </li>
            ))}
            {nearbyCinemas.length === 0 && (
              <li className={styles.empty}>
                {loadingCinemas
                  ? 'Finding cinemas near you…'
                  : cinemaSearchNote
                    ? 'Cinema results could not be loaded. Use my location to try again.'
                    : 'No cinemas found near your location.'}
              </li>
            )}
          </ul>
        </section>

        <section className={styles.map} aria-label="Map" data-enter data-enter-delay="0.2" data-enter-rise="60">
          <div ref={mapContainerRef} className={styles.mapboxMap} />
          {selectedCinema && (
            <aside className={styles.cinemaDetails} aria-label="Selected cinema details">
              <button
                type="button"
                className={styles.closeCinemaDetails}
                aria-label="Close cinema details"
                onClick={() => setSelectedCinemaId(null)}
              >
                <X size={20} aria-hidden="true" />
              </button>
              <h2>{selectedCinema.name}</h2>
              <p className={styles.cinemaDetailsMeta}>
                {selectedCinema.address} · {formatDistance(selectedCinema.distanceMeters)}
              </p>
              <div className={styles.cinemaDetailsActions}>
                <a
                  className={styles.directionsButton}
                  href={`https://www.google.com/maps/dir/?api=1&destination=${selectedCinema.latitude},${selectedCinema.longitude}`}
                  target="_blank"
                  rel="noreferrer"
                >
                  <Navigation size={18} aria-hidden="true" />
                  Directions
                </a>
                {selectedCinema.website && safeWebsiteUrl(selectedCinema.website) && (
                  <a
                    className={styles.websiteButton}
                    href={safeWebsiteUrl(selectedCinema.website)!}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Website
                    <ExternalLink size={16} aria-hidden="true" />
                  </a>
                )}
              </div>
            </aside>
          )}
        </section>
          </>
        ) : (
          <div className={styles.locationRequired} role="status">
            <div className={styles.locationPrompt}>
              <img src={pinIcon} width={24} height={24} alt="" />
              {locating ? (
                <>
                  <h2>Finding your location</h2>
                  <p>Allow your browser to share your location to access nearby cinemas.</p>
                </>
              ) : (
                <>
                  <h2>Enable location to access this feature</h2>
                  <p>{locationNote || 'Allow location access in your browser to see cinemas around you.'}</p>
                  <button type="button" className={styles.retryLocation} onClick={useMyLocation}>
                    Recheck location permission
                  </button>
                </>
              )}
            </div>
          </div>
        )}
      </main>
    </div>
  )
}
