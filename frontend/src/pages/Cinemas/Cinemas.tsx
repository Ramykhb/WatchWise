import { useRef, useState, type CSSProperties } from 'react'
import { Link } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import pinIcon from '../../assets/cinemas/icon-pin.svg'
import locateIcon from '../../assets/cinemas/icon-locate.svg'
import starIcon from '../../assets/cinemas/icon-star.svg'
import mapSea from '../../assets/cinemas/map-sea.svg'
import mapPark from '../../assets/cinemas/map-park.svg'
import mapRoads from '../../assets/cinemas/map-roads.svg'
import youHalo from '../../assets/cinemas/you-halo.svg'
import youDot from '../../assets/cinemas/you.svg'
import filmIcon from '../../assets/cinemas/icon-film.svg'
import starPinIcon from '../../assets/cinemas/icon-star-pin.svg'
import navIcon from '../../assets/cinemas/icon-nav.svg'
import plusIcon from '../../assets/cinemas/icon-plus.svg'
import minusIcon from '../../assets/cinemas/icon-minus.svg'
import dotYou from '../../assets/cinemas/dot-you.svg'
import dotCinema from '../../assets/cinemas/dot-cinema.svg'
import dotLocation from '../../assets/cinemas/dot-location.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { gradientCss } from '../Home/homeData.ts'
import {
  CINEMAS,
  DEFAULT_LOCATION,
  FEATURES,
  LOCATIONS,
  SHOWING,
  YOU,
  type Feature,
  type MapPoint,
} from './cinemasData.ts'
import styles from './Cinemas.module.css'

type Mode = 'cinemas' | 'locations'

const ZOOM_MIN = 1
const ZOOM_MAX = 2
const ZOOM_STEP = 0.25

/** Pin position after zooming around "you"; pins stay the same size, only their spacing scales. */
const placeAt = ({ x, y }: MapPoint, zoom: number): CSSProperties => ({
  left: YOU.x + (x - YOU.x) * zoom,
  top: YOU.y + (y - YOU.y) * zoom,
})

const directionsUrl = (place: string) =>
  `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(place)}`

export default function Cinemas() {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  const [mode, setMode] = useState<Mode>('cinemas')
  const [filters, setFilters] = useState<Feature[]>(['tonight'])
  const [filmIndex, setFilmIndex] = useState(0)
  const [selectedId, setSelectedId] = useState(CINEMAS[0].id)
  // Chosen showtime per cinema (Figma: 8:40 at Grand ABC).
  const [times, setTimes] = useState<Record<string, string>>({ [CINEMAS[0].id]: CINEMAS[0].times[1] })
  const [zoom, setZoom] = useState(ZOOM_MIN)
  const [place, setPlace] = useState(DEFAULT_LOCATION)
  const [locating, setLocating] = useState(false)
  const [locationNote, setLocationNote] = useState('')

  const film = SHOWING[filmIndex]
  const cinemas = CINEMAS.filter((cinema) => filters.every((f) => cinema.features.includes(f)))
  const selectedCinema = CINEMAS.find((c) => c.id === selectedId)
  const selectedLocation = LOCATIONS.find((l) => l.id === selectedId)

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

  const useMyLocation = () => {
    if (!navigator.geolocation) {
      setLocationNote("This browser can't share your location.")
      return
    }
    setLocating(true)
    setLocationNote('')
    navigator.geolocation.getCurrentPosition(
      () => {
        // TODO: look up cinemas around these coordinates through the backend.
        setLocating(false)
        setPlace('Your current location')
      },
      () => {
        setLocating(false)
        setLocationNote('Location permission was blocked, so we are showing cinemas near Hamra.')
      },
      { timeout: 10000 },
    )
  }

  // Showtimes from the chosen one onwards ("Next: 8:40 PM · 10:20 PM").
  const upcoming = selectedCinema
    ? selectedCinema.times.slice(Math.max(0, selectedCinema.times.indexOf(times[selectedCinema.id] ?? selectedCinema.times[0])))
    : []

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
          <div className={styles.canvas}>
            <div className={styles.layers} style={{ scale: String(zoom) }} aria-hidden="true">
              <img src={mapSea} width={896} height={884} alt="" />
              <img src={mapPark} width={896} height={884} alt="" />
              <img src={mapRoads} width={896} height={884} alt="" />
            </div>
            <img className={styles.youHalo} src={youHalo} width={90} height={90} alt="" />
            <img className={styles.you} src={youDot} width={26} height={26} alt="You are here" />

            {mode === 'cinemas' &&
              cinemas.map((cinema) => (
                <button
                  key={cinema.id}
                  type="button"
                  className={selectedId === cinema.id ? styles.pinSelected : styles.pin}
                  style={placeAt(cinema.at, zoom)}
                  aria-pressed={selectedId === cinema.id}
                  onClick={() => setSelectedId(cinema.id)}
                >
                  <span className={styles.pinIcon}>
                    <img src={filmIcon} width={12} height={12} alt="" />
                  </span>
                  {cinema.pin}
                </button>
              ))}
            {LOCATIONS.filter((l) => mode === 'locations' || l.id === LOCATIONS[0].id).map((location) => (
              <button
                key={location.id}
                type="button"
                className={styles.pinLocation}
                style={placeAt(location.at, zoom)}
                aria-pressed={selectedId === location.id}
                aria-label={`${location.pin}: ${location.name}`}
                onClick={() => {
                  setMode('locations')
                  setSelectedId(location.id)
                }}
              >
                <span className={styles.pinIcon}>
                  <img src={starPinIcon} width={12} height={12} alt="" />
                </span>
                {location.pin}
              </button>
            ))}
          </div>

          {selectedCinema && mode === 'cinemas' && cinemas.includes(selectedCinema) && (
            <div className={styles.popup} role="status">
              <p className={styles.popupTitle}>{selectedCinema.name}</p>
              <p className={styles.popupText}>
                {selectedCinema.address} · {selectedCinema.distance} · {selectedCinema.drive}
              </p>
              <div className={styles.next}>
                <span className={styles.nextLabel}>Next:</span>
                {upcoming.map((time, index) => (
                  <span key={time} className={index === 0 ? styles.nextChipOn : styles.nextChip}>
                    {time} PM
                  </span>
                ))}
              </div>
              <div className={styles.popupActions}>
                <a
                  className={styles.directions}
                  href={directionsUrl(`${selectedCinema.name}, ${selectedCinema.address}`)}
                  target="_blank"
                  rel="noreferrer"
                >
                  <img src={navIcon} width={16} height={16} alt="" />
                  Directions
                </a>
                {/* TODO: use the cinema's own website from the showtimes API. */}
                <PillButton variant="outline" href={`https://www.google.com/search?q=${encodeURIComponent(selectedCinema.name)}`}>
                  Website
                </PillButton>
              </div>
            </div>
          )}
          {selectedLocation && mode === 'locations' && (
            <div className={styles.popup} role="status">
              <p className={styles.popupTitle}>{selectedLocation.name}</p>
              <p className={styles.popupText}>
                {selectedLocation.scene} in <strong>{selectedLocation.movie.title}</strong> · {selectedLocation.place}
              </p>
              <div className={styles.popupActions}>
                <a className={styles.directions} href={directionsUrl(selectedLocation.name)} target="_blank" rel="noreferrer">
                  <img src={navIcon} width={16} height={16} alt="" />
                  Directions
                </a>
                <PillButton variant="outline" to={`/movies/${selectedLocation.movie.slug}`}>
                  View film
                </PillButton>
              </div>
            </div>
          )}

          <div className={styles.zoom}>
            <button
              type="button"
              className={styles.zoomBtn}
              aria-label="Zoom in"
              disabled={zoom >= ZOOM_MAX}
              onClick={() => setZoom((z) => Math.min(ZOOM_MAX, z + ZOOM_STEP))}
            >
              <img src={plusIcon} width={20} height={20} alt="" />
            </button>
            <button
              type="button"
              className={styles.zoomBtn}
              aria-label="Zoom out"
              disabled={zoom <= ZOOM_MIN}
              onClick={() => setZoom((z) => Math.max(ZOOM_MIN, z - ZOOM_STEP))}
            >
              <img src={minusIcon} width={20} height={20} alt="" />
            </button>
          </div>

          <ul className={styles.legend} aria-label="Legend">
            <li>
              <img src={dotYou} width={10} height={10} alt="" />
              You
            </li>
            <li>
              <img src={dotCinema} width={10} height={10} alt="" />
              Cinema
            </li>
            <li>
              <img src={dotLocation} width={10} height={10} alt="" />
              Movie location
            </li>
          </ul>
        </section>
      </main>
    </div>
  )
}
