import { useEffect, useState } from "react";
import { Link } from "react-router";
import MovieTile from "../../components/MovieTile/MovieTile.tsx";
import { api } from "../../lib/api.ts";
import { toTileMovie, type TmdbMovie } from "../Discover/discoverApi.ts";
import usersIcon from "../../assets/foryou/icon-users.svg";
import styles from "./ForYou.module.css";

type FollowingMovie = TmdbMovie & { following_count: number; reason: string };

export default function FollowingRecommendations({ userId, genres }: {
  userId: number; genres: Map<number, string>;
}) {
  const [movies, setMovies] = useState<FollowingMovie[]>([]);
  const [status, setStatus] = useState<"loading" | "ready" | "error">("loading");
  const [retry, setRetry] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    void api.get<{ movies: FollowingMovie[] }>("/following-recommendations", { signal: controller.signal })
      .then(({ data }) => {
        if (!controller.signal.aborted) {
          setMovies(data.movies);
          setStatus("ready");
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) setStatus("error");
      });
    return () => controller.abort();
  }, [userId, retry]);

  if (status === "ready" && movies.length === 0) return null;

  return (
    <section className={styles.shelf} aria-labelledby="shelf-friends">
      <div className={styles.sectionHeader}>
        <h2 id="shelf-friends" className={styles.sectionTitle}>Loved by people you follow</h2>
        <Link to="/profile" className={styles.sectionAction}>Your network →</Link>
      </div>
      <span className={styles.reason}>
        <img src={usersIcon} width={14} height={14} alt="" />
        Five-star picks from people you follow
      </span>
      {status === "loading" ? (
        <div className={styles.row} role="status" aria-label="Loading picks from people you follow" aria-busy="true">
          {Array.from({ length: 6 }, (_, index) => (
            <div className={styles.skeletonMovie} key={index} aria-hidden="true">
              <span className={`${styles.movieSkeletonShimmer} ${styles.skeletonPoster}`} />
              <span className={`${styles.movieSkeletonShimmer} ${styles.skeletonMovieTitle}`} />
              <span className={`${styles.movieSkeletonShimmer} ${styles.skeletonMovieMeta}`} />
            </div>
          ))}
        </div>
      ) : status === "error" ? (
        <div className={styles.recommendationState} role="status">
          <p>Picks from people you follow are temporarily unavailable. Please try again.</p>
          <button type="button" className={styles.retryLocation} onClick={() => {
            setStatus("loading");
            setRetry((value) => value + 1);
          }}>Try again</button>
        </div>
      ) : (
        <div className={styles.row}>
          {movies.map((movie) => (
            <MovieTile key={`${movie.media_type}-${movie.id}`} movie={{
              ...toTileMovie(movie, genres), recommendationReason: movie.reason,
            }} />
          ))}
        </div>
      )}
    </section>
  );
}
