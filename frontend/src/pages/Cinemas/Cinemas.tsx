import { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'
import { CircleMarker, MapContainer, TileLayer, useMap } from 'react-leaflet'
import type { LatLngExpression } from 'leaflet'
import 'leaflet/dist/leaflet.css'
import AppNav from '../../components/AppNav/AppNav.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import pinIcon from '../../assets/cinemas/icon-pin.svg'
import locateIcon from '../../assets/cinemas/icon-locate.svg'
import starIcon from '../../assets/cinemas/icon-star.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { gradientCss } from '../Home/homeData.ts'
import {
  CINEMAS,
  DEFAULT_LOCATION,
  FEATURES,
  LOCATIONS,
  SHOWING,
  type Feature,
} from './cinemasData.ts'
import styles from './Cinemas.module.css'

type Mode = 'cinemas' | 'locations'

const DEFAULT_MAP_CENTER: LatLngExpression = [33.8938, 35.5018]
const CARTO_API_KEY = import.meta.env.VITE_CARTO_API_KEY

function RecenterMap({ center }: { center: LatLngExpression }) {
  const map = useMap()
  useEffect(() => {
    map.flyTo(center, map.getZoom(), { duration: 0.8 })
  }, [center, map])
  return null
}

export default function Cinemas() {
  const pageRef = useRef<HTMLDivElement>(null)
  const requestedLocation = useRef(false)
  useEntranceAnimation(pageRef)

  const [mode, setMode] = useState<Mode>('cinemas')
  const [filters, setFilters] = useState<Feature[]>(['tonight'])
  const [filmIndex, setFilmIndex] = useState(0)
  const [selectedId, setSelectedId] = useState(CINEMAS[0].id)
  // Chosen showtime per cinema (Figma: 8:40 at Grand ABC).
  const [times, setTimes] = useState<Record<string, string>>({ [CINEMAS[0].id]: CINEMAS[0].times[1] })
  const [place, setPlace] = useState(DEFAULT_LOCATION)
  const [mapCenter, setMapCenter] = useState<LatLngExpression>(DEFAULT_MAP_CENTER)
  const [currentLocation, setCurrentLocation] = useState<LatLngExpression | null>(null)
  const [locating, setLocating] = useState(true)
  const [locationNote, setLocationNote] = useState('')

  const film = SHOWING[filmIndex]
  const cinemas = CINEMAS.filter((cinema) => filters.every((f) => cinema.features.includes(f)))

  const toggleFilter = (feature: Feature) =>
    setFilters((current) => (current.includes(feature) ? current.filter((f) => f !== feature) : [...current, feature]))

  const pickTime = (cinemaId: string, time: string) => {
    setSelectedId(cinemaId)
    setTimes((current) => ({ ...current, [cinemaId]: time }))
  }

  const switchMode = (next: Mode) => {
    setMode(next)
    setSelectedId(next === 'cinemas' ? CINEMAS[0].id : LOCATIONS[0].id)
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
        setPlace('Your current location')
        const coordinates: LatLngExpression = [position.coords.latitude, position.coords.longitude]
        setCurrentLocation(coordinates)
        setMapCenter(coordinates)
      },
      () => {
        setLocating(false)
        setCurrentLocation(null)
        setLocationNote('Location permission was blocked, so we are showing cinemas near Hamra.')
        setMapCenter(DEFAULT_MAP_CENTER)
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
        <section className={styles.panel} aria-labelledby="cinemas-title" data-enter data-enter-delay="0.05">
          <SplitHeadline id="cinemas-title" className={styles.title} lines={[{ text: 'Cinemas near you' }]} />
          <p className={styles.lead}>Showtimes and movie locations around you, powered by maps &amp; geolocation.</p>

          <div className={styles.location}>
            <img src={pinIcon} width={18} height={18} alt="" />
            <span className={styles.place}>{place}</span>
            <button type="button" className={styles.locate} onClick={useMyLocation} disabled={locating}>
              <img src={locateIcon} width={16} height={16} alt="" />
              {locating ? 'Locating…' : 'Use my location'}
            </button>
          </div>
          {locationNote && (
            <p className={styles.note} role="status">
              {locationNote}
            </p>
          )}

          <div className={styles.segmented} role="tablist" aria-label="Show on the map">
            {(['cinemas', 'locations'] as const).map((id) => (
              <button
                key={id}
                type="button"
                role="tab"
                aria-selected={mode === id}
                className={styles.segment}
                onClick={() => switchMode(id)}
              >
                {id === 'cinemas' ? 'Cinemas' : 'Movie locations'}
              </button>
            ))}
          </div>

          {mode === 'cinemas' ? (
            <>
              <div className={styles.filters} role="group" aria-label="Filter cinemas">
                {FEATURES.map((feature) => (
                  <button
                    key={feature.id}
                    type="button"
                    aria-pressed={filters.includes(feature.id)}
                    className={styles.chip}
                    onClick={() => toggleFilter(feature.id)}
                  >
                    {feature.label}
                  </button>
                ))}
              </div>

              <div className={styles.film}>
                <span className={styles.filmThumb} style={{ background: gradientCss(film.gradient) }} aria-hidden="true" />
                <Link to={`/movies/${film.slug}`} className={styles.filmText}>
                  <span className={styles.filmLabel}>SHOWING</span>
                  <span className={styles.filmTitle}>{film.title}</span>
                </Link>
                <button
                  type="button"
                  className={styles.change}
                  onClick={() => setFilmIndex((index) => (index + 1) % SHOWING.length)}
                >
                  Change<span className="sr-only"> film</span>
                </button>
              </div>

              <ul className={styles.list} data-lenis-prevent>
                {cinemas.map((cinema) => (
                  <li key={cinema.id}>
                    <article className={styles.card} aria-current={selectedId === cinema.id ? 'true' : undefined}>
                      <button type="button" className={styles.cardHead} onClick={() => setSelectedId(cinema.id)}>
                        <span className={styles.cardName}>
                          <span className={styles.cardTitle}>{cinema.name}</span>
                          <span className={styles.cardMeta}>
                            {cinema.address} · {cinema.distance}
                          </span>
                        </span>
                        <span className={styles.rating}>
                          <img src={starIcon} width={14} height={14} alt="" />
                          {cinema.rating}
                        </span>
                      </button>
                      <div className={styles.times} role="group" aria-label={`${cinema.name} showtimes`}>
                        {cinema.times.map((time) => (
                          <button
                            key={time}
                            type="button"
                            aria-pressed={times[cinema.id] === time}
                            className={styles.time}
                            onClick={() => pickTime(cinema.id, time)}
                          >
                            {time}
                          </button>
                        ))}
                      </div>
                    </article>
                  </li>
                ))}
                {cinemas.length === 0 && <li className={styles.empty}>No cinemas nearby match these filters.</li>}
              </ul>
            </>
          ) : (
            <ul className={styles.list} data-lenis-prevent>
              {LOCATIONS.map((location) => (
                <li key={location.id}>
                  <button
                    type="button"
                    className={`${styles.card} ${styles.cardHead}`}
                    aria-current={selectedId === location.id ? 'true' : undefined}
                    onClick={() => setSelectedId(location.id)}
                  >
                    <span className={styles.cardName}>
                      <span className={styles.cardTitle}>{location.name}</span>
                      <span className={styles.cardMeta}>{location.place}</span>
                      <span className={styles.cardScene}>
                        {location.movie.title} · {location.scene}
                      </span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className={styles.map} aria-label="Map" data-enter data-enter-delay="0.2" data-enter-rise="60">
          <MapContainer className={styles.leafletMap} center={mapCenter} zoom={17} scrollWheelZoom>
            <RecenterMap center={mapCenter} />
            {currentLocation && (
              <CircleMarker
                center={currentLocation}
                radius={8}
                pathOptions={{ color: '#ffffff', weight: 3, fillColor: '#1684ff', fillOpacity: 1 }}
              />
            )}
            <TileLayer
              attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
              url={`https://{s}.basemaps.cartocdn.com/rastertiles/light_all/{z}/{x}/{y}{r}.png?key=${CARTO_API_KEY}`}
            />
          </MapContainer>
        </section>
      </main>
    </div>
  )
}
