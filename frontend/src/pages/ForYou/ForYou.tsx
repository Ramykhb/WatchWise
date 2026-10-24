import { useCallback, useEffect, useRef, useState } from "react";
import { Link } from "react-router";
import {
  Sun,
  CloudSun,
  CloudFog,
  CloudDrizzle,
  CloudRain,
  CloudSnow,
  CloudLightning,
  Cloud,
  type LucideIcon,
} from "lucide-react";
import AppNav from "../../components/AppNav/AppNav.tsx";
import MovieTile from "../../components/MovieTile/MovieTile.tsx";
import SplitHeadline from "../../components/SplitHeadline/SplitHeadline.tsx";
import bgBlob from "../../assets/foryou/bg-blob.svg";
import pinIcon from "../../assets/foryou/icon-pin.svg";
import sparkleIcon from "../../assets/foryou/icon-sparkle.svg";
import filmIcon from "../../assets/foryou/icon-film.svg";
import rainSmIcon from "../../assets/foryou/icon-rain-sm.svg";
import sparkleSmIcon from "../../assets/foryou/icon-sparkle-sm.svg";
import usersIcon from "../../assets/foryou/icon-users.svg";
import { useEntranceAnimation } from "../../hooks/useEntranceAnimation.ts";
import { api } from "../../lib/api.ts";
import {
  PROFILE,
  SHELVES,
  TASTE,
  WEATHER_MOOD,
  type Shelf,
} from "./forYouData.ts";
import styles from "./ForYou.module.css";

const SHELF_ICONS: Record<Shelf["icon"], string> = {
  film: filmIcon,
  rain: rainSmIcon,
  sparkle: sparkleSmIcon,
  users: usersIcon,
};

type WeatherResponse = {
  location: {
    latitude: number;
    longitude: number;
    is_default: boolean;
  };
  current: {
    temperature_2m: number;
    relative_humidity_2m: number;
    apparent_temperature: number;
    weather_code: number;
    precipitation: number;
    wind_speed_10m: number;
  };
};

type WeatherMood = {
  title: string;
  mood: string;
  description: string;
};

type WeatherMoodCache = {
  cachedAt: number;
  weather: WeatherResponse;
  mood: WeatherMood;
};

const WEATHER_CACHE_KEY = "watchwise:weather-mood:v1";
const WEATHER_CACHE_TTL = 60 * 60 * 1000;

function removeWeatherCache() {
  try {
    localStorage.removeItem(WEATHER_CACHE_KEY);
  } catch {
    // Storage can be unavailable in private browsing or when blocked by the browser.
  }
}

function readWeatherCache(): WeatherMoodCache | null {
  try {
    const raw = localStorage.getItem(WEATHER_CACHE_KEY);
    if (!raw) return null;

    const cache = JSON.parse(raw) as Partial<WeatherMoodCache>;
    const age = Date.now() - (cache.cachedAt ?? 0);
    const current = cache.weather?.current;
    const location = cache.weather?.location;
    const isValid =
      typeof cache.cachedAt === "number" &&
      typeof location?.latitude === "number" &&
      typeof location.longitude === "number" &&
      typeof location.is_default === "boolean" &&
      typeof current?.temperature_2m === "number" &&
      typeof current.apparent_temperature === "number" &&
      typeof current.relative_humidity_2m === "number" &&
      typeof current.weather_code === "number" &&
      typeof current.precipitation === "number" &&
      typeof current.wind_speed_10m === "number" &&
      typeof cache.mood?.title === "string" &&
      typeof cache.mood?.mood === "string" &&
      typeof cache.mood?.description === "string";

    if (!isValid || age < 0 || age >= WEATHER_CACHE_TTL) {
      removeWeatherCache();
      return null;
    }

    return cache as WeatherMoodCache;
  } catch {
    removeWeatherCache();
    return null;
  }
}

const weatherCondition = (code: number): string => {
  if (code === 0) return "Clear sky";
  if ([1, 2, 3].includes(code)) return "Partly cloudy";
  if ([45, 48].includes(code)) return "Foggy";
  if ([51, 53, 55, 56, 57].includes(code)) return "Drizzle";
  if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return "Rain";
  if ([71, 73, 75, 77, 85, 86].includes(code)) return "Snow";
  if ([95, 96, 99].includes(code)) return "Thunderstorm";

  return "Variable conditions";
};

const WEATHER_ICONS: Record<string, LucideIcon> = {
  "Clear sky": Sun,
  "Partly cloudy": CloudSun,
  Foggy: CloudFog,
  Drizzle: CloudDrizzle,
  Rain: CloudRain,
  Snow: CloudSnow,
  Thunderstorm: CloudLightning,
};

export default function ForYou() {
  const pageRef = useRef<HTMLDivElement>(null);
  const [initialWeatherCache] = useState(readWeatherCache);
  const [locationStatus, setLocationStatus] = useState<
    "requesting" | "granted" | "unavailable"
  >(() => initialWeatherCache?.weather.location.is_default ? "unavailable" : initialWeatherCache ? "granted" : "requesting");
  const [weather, setWeather] = useState<WeatherResponse | null>(
    () => initialWeatherCache?.weather ?? null,
  );
  const [weatherError, setWeatherError] = useState(false);
  const [weatherLoading, setWeatherLoading] = useState(initialWeatherCache === null);
  const [weatherMood, setWeatherMood] = useState<WeatherMood | null>(
    () => initialWeatherCache?.mood ?? null,
  );
  const [moodLoading, setMoodLoading] = useState(initialWeatherCache === null);
  const [cacheTimestamp, setCacheTimestamp] = useState<number | null>(
    () => initialWeatherCache?.cachedAt ?? null,
  );
  useEntranceAnimation(pageRef);

  // TODO: persist in the user's settings once the API exists.
  const [adaptToWeather, setAdaptToWeather] = useState(true);
  const shelves = SHELVES.filter((shelf) => adaptToWeather || !shelf.weather);

  const fetchWeather = useCallback(
    async (latitude?: number, longitude?: number) => {
      setWeatherLoading(true);
      setWeatherError(false);
      setMoodLoading(true);

      try {
        const { data } = await api.get<WeatherResponse>('/weather', {
          params: {
            ...(latitude !== undefined && longitude !== undefined
              ? { latitude, longitude }
              : {}),
          },
        });
        setWeather(data);
        setWeatherMood(null);
        setCacheTimestamp(null);
        removeWeatherCache();
        setWeatherLoading(false);

        try {
          const { data: moodData } = await api.post<WeatherMood>(
            '/weather-mood',
            {
              condition: weatherCondition(data.current.weather_code),
              temperature: data.current.temperature_2m,
              humidity: data.current.relative_humidity_2m,
              wind: data.current.wind_speed_10m,
              precipitation: data.current.precipitation,
            },
          );
          setWeatherMood(moodData);
          const cachedAt = Date.now();
          try {
            localStorage.setItem(
              WEATHER_CACHE_KEY,
              JSON.stringify({ cachedAt, weather: data, mood: moodData } satisfies WeatherMoodCache),
            );
            setCacheTimestamp(cachedAt);
          } catch {
            // Keep the live response usable if the browser blocks local storage.
          }
        } catch (moodError) {
          console.error("Unable to load weather mood:", moodError);
        } finally {
          setMoodLoading(false);
        }
      } catch (error) {
        console.error("Unable to load weather:", error);
        setWeatherError(true);
        setMoodLoading(false);
      } finally {
        setWeatherLoading(false);
      }
    },
    [],
  );

  const requestLocation = useCallback(() => {
    if (typeof navigator === "undefined" || !navigator.geolocation) {
      setLocationStatus("unavailable");
      void fetchWeather();
      return;
    }

    setLocationStatus("requesting");
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        setLocationStatus("granted");
        console.log("User location:", {
          latitude: coords.latitude,
          longitude: coords.longitude,
        });
        void fetchWeather(coords.latitude, coords.longitude);
      },
      () => {
        setLocationStatus("unavailable");
        void fetchWeather();
      },
    );
  }, [fetchWeather]);

  useEffect(() => {
    if (initialWeatherCache) return;

    const requestId = window.setTimeout(requestLocation, 0);

    return () => window.clearTimeout(requestId);
  }, [initialWeatherCache, requestLocation]);

  useEffect(() => {
    if (cacheTimestamp === null) return;

    const expireCacheAndRefresh = () => {
      removeWeatherCache();
      setWeather(null);
      setWeatherMood(null);
      setCacheTimestamp(null);
      setWeatherError(false);
      setWeatherLoading(true);
      setMoodLoading(true);
      requestLocation();
    };
    const timeUntilExpiry = cacheTimestamp + WEATHER_CACHE_TTL - Date.now();

    if (timeUntilExpiry <= 0) {
      expireCacheAndRefresh();
      return;
    }

    const timeout = window.setTimeout(expireCacheAndRefresh, timeUntilExpiry);
    return () => window.clearTimeout(timeout);
  }, [cacheTimestamp, requestLocation]);

  const place = weather?.location.is_default
    ? "Beirut · now"
    : "Your location · now";
  const current = weather?.current;
  const condition = current
    ? weatherCondition(current.weather_code)
    : "Loading weather…";
  const WeatherIcon = WEATHER_ICONS[condition] ?? Cloud;

  return (
    <div ref={pageRef} className={styles.page}>
      <div className={styles.background} aria-hidden="true">
        <img
          className={styles.blob}
          src={bgBlob}
          width={700}
          height={602.524}
          alt=""
        />
      </div>

      <AppNav />

      <main className={styles.main}>
        <section className={styles.hero} aria-labelledby="for-you-title">
          <div className={styles.intro} data-enter data-enter-delay="0.1">
            <SplitHeadline
              id="for-you-title"
              className={styles.title}
              accentClassName={styles.accent}
              lines={[
                { text: "Picked for" },
                { text: "you,", accentEnd: `${PROFILE.name}.` },
              ]}
            />
            <p className={styles.lead}>{PROFILE.summary}</p>
            <ul className={styles.taste} aria-label="Your taste">
              {TASTE.map((chip) => (
                <li key={chip.label}>
                  <Link
                    to={chip.to}
                    className={styles.tasteChip}
                    style={{ background: chip.color }}
                  >
                    {chip.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <aside
            className={styles.weather}
            aria-label="Weather"
            aria-busy={weatherLoading}
            data-enter
            data-enter-delay="0.3"
            data-enter-rise="80"
          >
            <div className={styles.weatherTop}>
              <div className={styles.temp}>
                <span className={styles.place}>
                  <img src={pinIcon} width={16} height={16} alt="" />
                  {place}
                </span>
                <span className={styles.degrees}>
                  {weatherLoading && !current ? (
                    <span className={styles.loadingValue}>Loading…</span>
                  ) : (
                    `${Math.round(current?.temperature_2m ?? 0)}°`
                  )}
                </span>
                <span className={styles.condition}>
                  {condition}
                  {current &&
                    ` · feels like ${Math.round(current.apparent_temperature)}°`}
                </span>
              </div>
              <span className={styles.weatherIcon} aria-hidden="true">
                <WeatherIcon size={64} strokeWidth={1.5} />
              </span>
            </div>
            <div className={styles.mood}>
              <span className={styles.moodLabel}>
                <img src={sparkleIcon} width={16} height={16} alt="" />
                WEATHER MOOD
              </span>
              <p className={styles.moodTitle}>
                {moodLoading && !weatherMood ? (
                  <span className={styles.loadingValue}>Loading…</span>
                ) : (
                  (weatherMood?.mood ?? WEATHER_MOOD.mood)
                )}
              </p>
              <p className={styles.moodText}>
                {weatherError && !weather
                  ? "Weather data is temporarily unavailable. Please try again shortly."
                  : moodLoading && !weatherMood
                    ? "Reading the sky…"
                    : (weatherMood?.description ?? WEATHER_MOOD.moodText)}
              </p>
            </div>
            {current && (
              <p className={styles.weatherDetails}>
                Humidity {current.relative_humidity_2m}% · Precipitation{" "}
                {current.precipitation} mm · Wind{" "}
                {Math.round(current.wind_speed_10m)} km/h
              </p>
            )}
            <label className={styles.switch}>
              <input
                type="checkbox"
                role="switch"
                checked={adaptToWeather}
                onChange={(event) => setAdaptToWeather(event.target.checked)}
              />
              <span className={styles.switchTrack} aria-hidden="true" />
              Adapt suggestions to weather
            </label>
            {locationStatus === "unavailable" && (
              <div className={styles.locationPrompt} role="status">
                <p className={styles.locationNote}>
                  Allow location services to personalize weather suggestions.
                </p>
              </div>
            )}
          </aside>
        </section>

        {shelves.map((shelf) => (
          <section
            key={shelf.id}
            className={styles.shelf}
            aria-labelledby={`shelf-${shelf.id}`}
          >
            <div className={styles.sectionHeader}>
              <h2 id={`shelf-${shelf.id}`} className={styles.sectionTitle}>
                {shelf.title}
              </h2>
              <Link to={shelf.more} className={styles.sectionAction}>
                More →
              </Link>
            </div>
            <span className={styles.reason}>
              <img
                src={SHELF_ICONS[shelf.icon]}
                width={14}
                height={14}
                alt=""
              />
              {shelf.reason}
            </span>
            <div className={styles.row}>
              {shelf.movies.map((movie) => (
                <MovieTile key={movie.slug} movie={movie} />
              ))}
            </div>
          </section>
        ))}
      </main>
    </div>
  );
}
