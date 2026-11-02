import { useEffect, useState } from "react";
import { Link } from "react-router";
import { api } from "../../lib/api.ts";
import styles from "./ForYou.module.css";

type Person = { id: number; name: string; watched_count: number };
type Profile = {
  watched_movies: number; watched_tv: number; five_star_ratings: number; following_count: number;
  genres: { id: number; name: string; percentage: number; watched_count: number; media_type: "movie" | "tv" | "all" }[];
  director: Person | null; actor: Person | null;
};
const GENRE_COLORS = ["var(--accent-coral)", "var(--accent-yellow)", "var(--accent-mint)"];

export default function TasteProfile({ userId, weatherEnabled }: { userId: number; weatherEnabled: boolean }) {
  const [profile, setProfile] = useState<Profile | null>(null);
  const [failed, setFailed] = useState(false);
  const [retry, setRetry] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    void api.get<Profile>("/taste-profile", { signal: controller.signal })
      .then(({ data }) => { if (!controller.signal.aborted) setProfile(data); })
      .catch(() => { if (!controller.signal.aborted) setFailed(true); });
    return () => controller.abort();
  }, [userId, retry]);

  if (failed) return (
    <div role="status">
      <p className={styles.lead}>Your taste profile is temporarily unavailable.</p>
      <button type="button" className={styles.retryLocation} onClick={() => {
        setFailed(false);
        setRetry((value) => value + 1);
      }}>Try again</button>
    </div>
  );
  if (!profile) return <p className={styles.lead} role="status">Loading your taste profile…</p>;

  const sources: string[] = [];
  if (profile.watched_movies) sources.push(`${profile.watched_movies} watched ${profile.watched_movies === 1 ? "film" : "films"}`);
  if (profile.watched_tv) sources.push(`${profile.watched_tv} watched TV ${profile.watched_tv === 1 ? "show" : "shows"}`);
  if (profile.five_star_ratings) sources.push(`${profile.five_star_ratings} five-star ${profile.five_star_ratings === 1 ? "rating" : "ratings"}`);
  if (profile.following_count) sources.push(`${profile.following_count} ${profile.following_count === 1 ? "person" : "people"} you follow`);
  const people = [profile.director, profile.actor].filter((person): person is Person => person !== null)
    .filter((person, index, list) => list.findIndex((item) => item.id === person.id) === index);

  return (
    <>
      <p className={styles.lead}>
        {sources.length
          ? `Built from your ${sources.join(", ")}${weatherEnabled ? " — and today’s weather" : ""}.`
          : "Mark films and TV shows as watched, rate your favourites, and follow people to build your taste profile."}
      </p>
      {(profile.genres.length > 0 || people.length > 0) && (
        <ul className={styles.taste} aria-label="Your taste">
          {profile.genres.map((genre, index) => (
            <li key={genre.id}>
              <Link
                to={`/discover?genre=${genre.id}&genre_name=${encodeURIComponent(genre.name)}&media=${genre.media_type}`}
                className={styles.tasteChip}
                style={{ background: GENRE_COLORS[index] }}
                title={`${genre.watched_count} watched titles · ${genre.percentage}% of genre occurrences in your watched history`}
              >{genre.name} · {genre.percentage}%</Link>
            </li>
          ))}
          {people.map((person) => (
            <li key={person.id}>
              <Link to={`/people/${person.id}`} className={styles.tasteChip}
                style={{ background: "var(--surface-violet-tint)" }}
                title={`In ${person.watched_count} of your watched titles`}>{person.name}</Link>
            </li>
          ))}
        </ul>
      )}
    </>
  );
}
