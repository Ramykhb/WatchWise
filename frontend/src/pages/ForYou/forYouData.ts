import type { Movie } from "../../components/MovieTile/MovieTile.tsx";
import { MOVIES } from "../Home/homeData.ts";

// Mock content from the Figma "For You — Recommendations" frame. Replace with the recommendation
// API (watch history, ratings, follows) and a weather API once the backend is connected.

export type TasteChip = { label: string; color: string; to: string };

export type Shelf = {
  id: string;
  title: string;
  reason: string;
  icon: "film" | "rain" | "sparkle" | "users";
  more: string;
  /** Shown only while "Adapt suggestions to weather" is on. */
  weather?: boolean;
  movies: Movie[];
};

const withMeta = (movie: Movie, meta: string): Movie => ({ ...movie, meta });

export const PROFILE = {
  name: "Maya",
  summary:
    "Built from your 142 watched films, your 5★ ratings, favourite genres and directors, the people you follow — and tonight's weather.",
};

export const TASTE: TasteChip[] = [
  {
    label: "Sci-fi · 38%",
    color: "var(--accent-coral)",
    to: "/discover?q=sci-fi",
  },
  {
    label: "Drama · 27%",
    color: "var(--accent-yellow)",
    to: "/discover?q=drama",
  },
  {
    label: "Thriller · 18%",
    color: "var(--accent-mint)",
    to: "/discover?q=thriller",
  },
  {
    label: "Lena Okafor",
    color: "var(--surface-violet-tint)",
    to: "/people/lena-okafor",
  },
  {
    label: "Amara Mensah",
    color: "var(--surface-violet-tint)",
    to: "/people/amara-mensah",
  },
];

export const WEATHER_MOOD = {
  mood: "Cozy thrillers & comfort dramas",
  moodText:
    "Rainy evenings → slower, atmospheric picks. Sunny weekends → feel-good & adventure.",
};

export const SHELVES: Shelf[] = [
  {
    id: "because",
    title: "Because you watched Neon Tide",
    reason: "Similar story, tone and visuals",
    icon: "film",
    more: "/movies/neon-tide",
    movies: [
      withMeta(MOVIES.stardust, "97% match · Fantasy"),
      withMeta(MOVIES.deepBlue, "94% match · Thriller"),
      withMeta(MOVIES.lastLap, "92% match · Drama"),
      withMeta(MOVIES.wildFern, "90% match · Adventure"),
      withMeta(MOVIES.velvetHour, "88% match · Romance"),
      withMeta(MOVIES.paperMoon, "86% match · Comedy"),
    ],
  },
  {
    id: "rainy",
    title: "Rainy-day picks",
    reason: "Matched to 18° and light rain in Beirut",
    icon: "rain",
    more: "/discover",
    weather: true,
    movies: [
      withMeta(MOVIES.deepBlue, "94% match · Thriller"),
      withMeta(MOVIES.velvetHour, "91% match · Romance"),
      withMeta(MOVIES.lastLap, "89% match · Drama"),
      withMeta(MOVIES.paperMoon, "87% match · Comedy"),
      withMeta(MOVIES.stardust, "85% match · Fantasy"),
      withMeta(MOVIES.neonTide, "83% match · Sci-fi"),
    ],
  },
  {
    id: "people",
    title: "More from Lena Okafor & Amara Mensah",
    reason: "Your favourite director and actor",
    icon: "sparkle",
    more: "/people/lena-okafor",
    movies: [
      withMeta(MOVIES.lastLap, "92% match · 2025"),
      withMeta(MOVIES.stardust, "89% match · 2025"),
      withMeta(MOVIES.velvetHour, "87% match · 2026"),
      withMeta(MOVIES.wildFern, "85% match · 2024"),
      withMeta(MOVIES.redSignal, "83% match · 2026"),
      withMeta(MOVIES.deepBlue, "81% match · 2025"),
    ],
  },
  {
    id: "friends",
    title: "Loved by people you follow",
    reason: "Omar, Lina and 6 others rated these ★4.5+",
    icon: "users",
    more: "/discover",
    movies: [
      withMeta(MOVIES.paperMoon, "8 friends · Comedy"),
      withMeta(MOVIES.redSignal, "7 friends · Action"),
      withMeta(MOVIES.neonTide, "6 friends · Sci-fi"),
      withMeta(MOVIES.velvetHour, "5 friends · Romance"),
      withMeta(MOVIES.wildFern, "4 friends · Adventure"),
      withMeta(MOVIES.stardust, "3 friends · Fantasy"),
    ],
  },
];
