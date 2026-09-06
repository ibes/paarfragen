export type Rating = -5 | -1 | 1 | 5;

/**
 * The rating scale and its labels are experimental — expect them to
 * change while chasing the sweet spot between "not annoying" and
 * "good feedback" (specs/2026-09-06-slice-3-frontend-api-wiring.md).
 * Kept in this one place on purpose.
 */
export const RATING_OPTIONS: ReadonlyArray<{ value: Rating; label: string }> = [
  { value: -5, label: "Trash" },
  { value: -1, label: "Rather negative" },
  { value: 1, label: "Rather positive" },
  { value: 5, label: "Really good" },
];

/** Same "keep it changeable" reasoning as RATING_OPTIONS above. */
export const FLUSH_THRESHOLD = 10;

export const API_BASE_URL: string =
  (import.meta.env.VITE_API_BASE_URL as string | undefined) ??
  "http://127.0.0.1:8000";
