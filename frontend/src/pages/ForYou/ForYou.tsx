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
import { useAuth } from "../../contexts/useAuth.ts";
import { api } from "../../lib/api.ts";
import type { WatchlistResponse } from "../../lib/watchlist.ts";
import { toTileMovie, type TmdbMovie } from "../Discover/discoverApi.ts";
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
  mood?: WeatherMood;
};

const WEATHER_CACHE_KEY = "watchwise:weather-mood:v1";
const WEATHER_CACHE_TTL = 60 * 60 * 1000;
const RECOMMENDATION_CACHE_KEY = "watchwise:weather-recommendations:v1";

type WeatherRecommendation = TmdbMovie & { reason: string };

type WeatherRecommendationCache = {
  cachedAt: number;
  latitude: number;
  longitude: number;
  movies: WeatherRecommendation[];
};

const MOVIE_GENRES = new Map<number, string>([
  [28, "Action"], [12, "Adventure"], [16, "Animation"], [35, "Comedy"],
  [80, "Crime"], [99, "Documentary"], [18, "Drama"], [10751, "Family"],
  [14, "Fantasy"], [36, "History"], [27, "Horror"], [10402, "Music"],
  [9648, "Mystery"], [10749, "Romance"], [878, "Sci-fi"], [53, "Thriller"],
  [10752, "War"], [37, "Western"], [10759, "Adventure"], [10765, "Sci-fi"],
  [10768, "War & Politics"], [10762, "Kids"], [10764, "Reality"], [10763, "News"],
  [10766, "Soap"], [10767, "Talk"],
]);

function removeWeatherCache() {
  try {
    localStorage.removeItem(WEATHER_CACHE_KEY);
  } catch {
    // Storage can be unavailable in private browsing or when blocked by the browser.
  }
}

function removeRecommendationCache() {
  try {
    localStorage.removeItem(RECOMMENDATION_CACHE_KEY);
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
      (cache.mood === undefined || (
        typeof cache.mood.title === "string" &&
        typeof cache.mood.mood === "string" &&
        typeof cache.mood.description === "string"
      ));

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

function readRecommendationCache(location: WeatherResponse["location"] | undefined): WeatherRecommendationCache | null {
  if (!location) return null;
  try {
    const raw = localStorage.getItem(RECOMMENDATION_CACHE_KEY);
    if (!raw) return null;
    const cache = JSON.parse(raw) as Partial<WeatherRecommendationCache>;
    const age = Date.now() - (cache.cachedAt ?? 0);
    const matchesLocation = cache.latitude === location.latitude && cache.longitude === location.longitude;
    const validMovies = Array.isArray(cache.movies) && cache.movies.every((movie) =>
      typeof movie?.id === "number" && typeof movie.title === "string" && typeof movie.reason === "string",
    );
    if (!matchesLocation || !validMovies || age < 0 || age >= WEATHER_CACHE_TTL) {
      localStorage.removeItem(RECOMMENDATION_CACHE_KEY);
      return null;
    }
    return cache as WeatherRecommendationCache;
  } catch {
    try { localStorage.removeItem(RECOMMENDATION_CACHE_KEY); } catch { /* Storage may be unavailable. */ }
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

const weatherPickTitle = (condition: string) => {
  switch (condition) {
    case "Clear sky": return "Sunny-day picks";
    case "Partly cloudy": return "Partly cloudy picks";
    case "Foggy": return "Foggy-day picks";
    case "Drizzle": return "Drizzly-day picks";
    case "Rain": return "Rainy-day picks";
    case "Snow": return "Snow-day picks";
    case "Thunderstorm": return "Stormy-day picks";
    default: return `${condition} picks`;
  }
};

export default function ForYou() {
  const pageRef = useRef<HTMLDivElement>(null);
  const { firstName, user } = useAuth();
  const userId = user?.id;
  const [watchedRetry, setWatchedRetry] = useState(0);
  const [watchedPicks, setWatchedPicks] = useState<{
    userId: number; source: TmdbMovie | null; movies: WeatherRecommendation[];
    status: "loading" | "ready" | "error";
  } | null>(null);

  useEffect(() => {
    if (userId === undefined) return;
    const controller = new AbortController();
    let requestTimeout: number | undefined;
    const load = async () => {
      try {
        const history = await api.get<WatchlistResponse>("/watchlist", { signal: controller.signal });
        if (controller.signal.aborted) return;
        if (!history.data.items.some((item) => item.status === "watched")) {
          setWatchedPicks(null);
          return;
        }
        setWatchedPicks({ userId, source: null, movies: [], status: "loading" });
        await new Promise<void>((resolve) => {
          requestTimeout = window.setTimeout(resolve, 1000);
        });
        if (controller.signal.aborted) return;
        const { data } = await api.get<{ source: TmdbMovie | null; movies: WeatherRecommendation[] }>(
          "/watched-recommendations", { signal: controller.signal },
        );
        if (!controller.signal.aborted) {
          setWatchedPicks({ userId, source: data.source, movies: data.movies, status: "ready" });
        }
      } catch (error: unknown) {
        if (!controller.signal.aborted) {
          setWatchedPicks({ userId, source: null, movies: [], status: "error" });
          console.error("Unable to load watched recommendations:", error);
        }
      }
    };
    void load();
    return () => {
      controller.abort();
      window.clearTimeout(requestTimeout);
    };
  }, [userId, watchedRetry]);
  const [initialWeatherCache] = useState(readWeatherCache);
  const [initialRecommendationCache] = useState(() =>
    readRecommendationCache(initialWeatherCache?.weather.location),
  );
  const [locationStatus, setLocationStatus] = useState<
    "checking" | "requesting" | "granted" | "unavailable"
  >("checking");
  const [weather, setWeather] = useState<WeatherResponse | null>(
    () => initialWeatherCache?.weather ?? null,
  );
  const [weatherError, setWeatherError] = useState(false);
  const [weatherLoading, setWeatherLoading] = useState(initialWeatherCache === null);
  const [weatherMood, setWeatherMood] = useState<WeatherMood | null>(
    () => initialWeatherCache?.mood ?? null,
  );
  const [moodLoading, setMoodLoading] = useState(initialWeatherCache === null || !initialWeatherCache.mood);
  const [cacheTimestamp, setCacheTimestamp] = useState<number | null>(
    () => initialWeatherCache?.cachedAt ?? null,
  );
  const [weatherRecommendations, setWeatherRecommendations] = useState<WeatherRecommendation[]>(
    () => initialRecommendationCache?.movies ?? [],
  );
  const [recommendationsLoading, setRecommendationsLoading] = useState(initialRecommendationCache === null);
  const [recommendationsError, setRecommendationsError] = useState(false);
  const moodRequestTimestamp = useRef<number | null>(null);
  useEntranceAnimation(pageRef);

  // TODO: persist in the user's settings once the API exists.
  const [adaptToWeather, setAdaptToWeather] = useState(true);
  const shelves = SHELVES;

  const fetchWeatherRecommendations = useCallback(async (latitude: number, longitude: number) => {
    setRecommendationsLoading(true);
    setRecommendationsError(false);
    try {
      const { data } = await api.get<{ movies: WeatherRecommendation[] }>("/movie-recommendations", {
        params: { latitude, longitude },
      });
      const movies = Array.isArray(data.movies) ? data.movies : [];
      setWeatherRecommendations(movies);
      try {
        localStorage.setItem(RECOMMENDATION_CACHE_KEY, JSON.stringify({
          cachedAt: Date.now(), latitude, longitude, movies,
        } satisfies WeatherRecommendationCache));
      } catch {
        // Keep recommendations usable if browser storage is unavailable.
      }
    } catch (error) {
      console.error("Unable to load weather recommendations:", error);
      setWeatherRecommendations([]);
      setRecommendationsError(true);
    } finally {
      setRecommendationsLoading(false);
    }
  }, []);

  const fetchWeatherMood = useCallback(async (data: WeatherResponse, cachedAt: number) => {
    if (moodRequestTimestamp.current === cachedAt) return;
    moodRequestTimestamp.current = cachedAt;
    setMoodLoading(true);
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
      try {
        localStorage.setItem(
          WEATHER_CACHE_KEY,
          JSON.stringify({ cachedAt, weather: data, mood: moodData } satisfies WeatherMoodCache),
        );
      } catch {
        // Keep the live mood usable if browser storage is unavailable.
      }
    } catch (moodError) {
      console.error("Unable to load weather mood:", moodError);
    } finally {
      setMoodLoading(false);
    }
  }, []);

  const fetchWeather = useCallback(
    async (latitude?: number, longitude?: number) => {
      setWeatherLoading(true);
      setWeatherError(false);
      setMoodLoading(true);
      setWeatherRecommendations([]);
      setRecommendationsLoading(true);
      setRecommendationsError(false);

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
        const cachedAt = Date.now();
        setCacheTimestamp(cachedAt);
        try {
          localStorage.setItem(
            WEATHER_CACHE_KEY,
            JSON.stringify({ cachedAt, weather: data } satisfies WeatherMoodCache),
          );
        } catch {
          // Keep the live weather usable if browser storage is unavailable.
        }
        setWeatherLoading(false);
        void fetchWeatherRecommendations(data.location.latitude, data.location.longitude);
        void fetchWeatherMood(data, cachedAt);
      } catch (error) {
        console.error("Unable to load weather:", error);
        setWeatherError(true);
        setMoodLoading(false);
        setRecommendationsLoading(false);
        setRecommendationsError(true);
      } finally {
        setWeatherLoading(false);
      }
    },
    [fetchWeatherMood, fetchWeatherRecommendations],
  );

  useEffect(() => {
    if (initialWeatherCache && !initialWeatherCache.mood) {
      void fetchWeatherMood(initialWeatherCache.weather, initialWeatherCache.cachedAt);
    }
  }, [fetchWeatherMood, initialWeatherCache]);

  const useDefaultLocation = useCallback(() => {
    removeWeatherCache();
    removeRecommendationCache();
    setWeather(null);
    setWeatherMood(null);
    setWeatherRecommendations([]);
    setCacheTimestamp(null);
    setWeatherError(false);
    setWeatherLoading(true);
    setMoodLoading(true);
    setRecommendationsLoading(true);
    setRecommendationsError(false);
    void fetchWeather();
  }, [fetchWeather]);

  const requestLocation = useCallback((forceDefault = false) => {
    if (typeof navigator === "undefined" || !navigator.geolocation) {
      setLocationStatus("unavailable");
      if (forceDefault || !initialWeatherCache?.weather.location.is_default) useDefaultLocation();
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
        if (forceDefault || !initialWeatherCache?.weather.location.is_default) useDefaultLocation();
      },
    );
  }, [initialWeatherCache, useDefaultLocation]);

  useEffect(() => {
    let active = true;
    let permission: PermissionStatus | null = null;

    const syncLocationPermission = () => {
      if (!active || !permission) return;
      if (permission.state === "denied") {
        setLocationStatus("unavailable");
        if (!initialWeatherCache?.weather.location.is_default) useDefaultLocation();
        return;
      }

      if (permission.state === "granted" && initialWeatherCache && !initialWeatherCache.weather.location.is_default) {
        setLocationStatus("granted");
        return;
      }
      requestLocation();
    };

    if (!navigator.permissions?.query) {
      requestLocation();
      return () => { active = false; };
    }

    void navigator.permissions.query({ name: "geolocation" }).then((status) => {
      if (!active) return;
      permission = status;
      status.addEventListener("change", syncLocationPermission);
      syncLocationPermission();
    }).catch(() => {
      if (active) requestLocation();
    });

    return () => {
      active = false;
      permission?.removeEventListener("change", syncLocationPermission);
    };
  }, [initialWeatherCache, requestLocation, useDefaultLocation]);

  useEffect(() => {
    if (!initialWeatherCache || initialRecommendationCache || locationStatus !== "granted") return;
    if (initialWeatherCache.weather.location.is_default) return;
    void fetchWeatherRecommendations(
      initialWeatherCache.weather.location.latitude,
      initialWeatherCache.weather.location.longitude,
    );
  }, [fetchWeatherRecommendations, initialRecommendationCache, initialWeatherCache, locationStatus]);

  useEffect(() => {
    if (cacheTimestamp === null) return;

    const expireCacheAndRefresh = () => {
      removeWeatherCache();
      removeRecommendationCache();
      setWeather(null);
      setWeatherMood(null);
      setWeatherRecommendations([]);
      setRecommendationsLoading(true);
      setRecommendationsError(false);
      setCacheTimestamp(null);
      setWeatherError(false);
      setWeatherLoading(true);
      setMoodLoading(true);
      requestLocation(true);
    };
    const timeUntilExpiry = cacheTimestamp + WEATHER_CACHE_TTL - Date.now();

    if (timeUntilExpiry <= 0) {
      expireCacheAndRefresh();
      return;
    }

    const timeout = window.setTimeout(expireCacheAndRefresh, timeUntilExpiry);
    return () => window.clearTimeout(timeout);
  }, [cacheTimestamp, requestLocation]);

  const canShowWeather = locationStatus === "granted" || (locationStatus === "unavailable" && weather?.location.is_default === true);
  const visibleWeather = canShowWeather ? weather : null;
  const visibleMood = visibleWeather ? weatherMood : null;
  const place = visibleWeather?.location.is_default
    ? "Beirut · now"
    : locationStatus === "checking" ? "Checking location…"
      : locationStatus === "unavailable" ? "Beirut · now" : "Your location · now";
  const current = visibleWeather?.current;
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
                { text: "you,", accentEnd: `${firstName}.` },
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
            aria-busy={locationStatus === "checking" || weatherLoading || moodLoading}
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
                  {(locationStatus === "checking" || weatherLoading) && !current ? (
                    <span className={`${styles.weatherSkeleton} ${styles.temperatureSkeleton}`} aria-hidden="true" />
                  ) : (
                    `${Math.round(current?.temperature_2m ?? 0)}°`
                  )}
                </span>
                <span className={styles.condition}>
                  {(locationStatus === "checking" || weatherLoading) && !current ? (
                    <span className={`${styles.weatherSkeleton} ${styles.conditionSkeleton}`} aria-hidden="true" />
                  ) : (
                    <>{condition}{current && ` · feels like ${Math.round(current.apparent_temperature)}°`}</>
                  )}
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
                {(locationStatus === "checking" || moodLoading) && !visibleMood ? (
                  <span className={`${styles.weatherSkeleton} ${styles.moodTitleSkeleton}`} aria-hidden="true" />
                ) : (
                  (visibleMood?.mood ?? WEATHER_MOOD.mood)
                )}
              </p>
              <p className={styles.moodText}>
                {weatherError && !visibleWeather
                  ? "Weather data is temporarily unavailable. Please try again shortly."
                  : (locationStatus === "checking" || moodLoading) && !visibleMood
                    ? <span className={styles.moodDescriptionSkeleton} aria-hidden="true"><i /><i /></span>
                    : (visibleMood?.description ?? WEATHER_MOOD.moodText)}
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
                  Location is off. Showing weather and movie picks for Beirut.
                </p>
                <button type="button" className={styles.retryLocation} onClick={() => requestLocation()}>
                  Check location again
                </button>
              </div>
            )}
          </aside>
        </section>

        {adaptToWeather && (
          <section className={styles.shelf} aria-labelledby="shelf-weather">
            <div className={styles.sectionHeader}>
              <h2 id="shelf-weather" className={styles.sectionTitle}>
                {current ? weatherPickTitle(condition) : "Weather picks"}
              </h2>
              <Link to="/discover" className={styles.sectionAction}>More →</Link>
            </div>
            <span className={styles.reason}>
              <img src={SHELF_ICONS.rain} width={14} height={14} alt="" />
              {current
                ? `TMDB movies selected for ${condition.toLowerCase()} weather · ${Math.round(current.temperature_2m)}° near ${visibleWeather?.location.is_default ? "Beirut" : "you"}`
                : "Matching real TMDB movies to your current weather"}
            </span>
            {locationStatus === "checking" || recommendationsLoading ? (
              <div className={styles.row} role="status" aria-label="Loading weather-matched movie recommendations" aria-busy="true">
                {Array.from({ length: 6 }, (_, index) => (
                  <div className={styles.skeletonMovie} key={index} aria-hidden="true">
                    <span className={`${styles.movieSkeletonShimmer} ${styles.skeletonPoster}`} />
                    <span className={`${styles.movieSkeletonShimmer} ${styles.skeletonMovieTitle}`} />
                    <span className={`${styles.movieSkeletonShimmer} ${styles.skeletonMovieMeta}`} />
                  </div>
                ))}
              </div>
            ) : recommendationsError ? (
              <p className={styles.recommendationState} role="status">Weather picks are temporarily unavailable.</p>
            ) : weatherRecommendations.length === 0 ? (
              <p className={styles.recommendationState} role="status">No matching movies were found for these conditions.</p>
            ) : (
              <div className={styles.row}>
                {weatherRecommendations.slice(0, 6).map((recommendation) => ({
                  ...toTileMovie(recommendation, MOVIE_GENRES),
                  recommendationReason: recommendation.reason,
                })).map((movie) => <MovieTile key={movie.slug} movie={movie} />)}
              </div>
            )}
          </section>
        )}

        {watchedPicks?.userId === userId && watchedPicks && (
          <section className={styles.shelf} aria-labelledby="shelf-because">
            <div className={styles.sectionHeader}>
              <h2 id="shelf-because" className={styles.sectionTitle}>
                Because you watched{watchedPicks.source ? ` ${watchedPicks.source.title}` : "…"}
              </h2>
              {watchedPicks.source && (
                <Link
                  to={`/movies/${watchedPicks.source.media_type === "tv" ? "tv-" : ""}${watchedPicks.source.id}`}
                  className={styles.sectionAction}
                >
                  More →
                </Link>
              )}
            </div>
            <span className={styles.reason}>
              <img src={filmIcon} width={14} height={14} alt="" />
              Similar story, tone and visuals
            </span>
            {watchedPicks.status === "loading" ? (
              <div className={styles.row} role="status" aria-label="Loading recommendations from your watched history" aria-busy="true">
                {Array.from({ length: 6 }, (_, index) => (
                  <div className={styles.skeletonMovie} key={index} aria-hidden="true">
                    <span className={`${styles.movieSkeletonShimmer} ${styles.skeletonPoster}`} />
                    <span className={`${styles.movieSkeletonShimmer} ${styles.skeletonMovieTitle}`} />
                    <span className={`${styles.movieSkeletonShimmer} ${styles.skeletonMovieMeta}`} />
                  </div>
                ))}
              </div>
            ) : watchedPicks.status === "error" ? (
              <div className={styles.recommendationState} role="status">
                <p>Recommendations are temporarily unavailable. Please try again shortly.</p>
                <button type="button" className={styles.retryLocation} onClick={() => setWatchedRetry((value) => value + 1)}>
                  Try again
                </button>
              </div>
            ) : watchedPicks.movies.length === 0 ? (
              <p className={styles.recommendationState} role="status">No matching titles were found for your watched history.</p>
            ) : (
              <div className={styles.row}>
                {watchedPicks.movies.map((recommendation) => (
                  <MovieTile key={`${recommendation.media_type}-${recommendation.id}`} movie={{
                    ...toTileMovie(recommendation, MOVIE_GENRES),
                    recommendationReason: recommendation.reason,
                  }} />
                ))}
              </div>
            )}
          </section>
        )}

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
