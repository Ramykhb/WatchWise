export type TasteChip = { label: string; color: string; to: string };

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
