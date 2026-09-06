import type { Rating } from "../config";

/** Wire shape from GET /questions — never carries `source` (specs/api.md). */
export interface Question {
  id: string;
  text: string;
}

/** App-side shape; api/client.ts maps this to the snake_case wire body. */
export interface QuestionFeedback {
  id: string;
  questionId: string;
  deckId: string;
  rating: Rating;
  freeText: string | null;
}

/**
 * App-side shape for POST /app-feedback. Unlike QuestionFeedback,
 * `freeText` is required non-empty (specs/2026-09-06-slice-4-app-feedback.md)
 * — there's no numeric rating to fall back on as signal.
 */
export interface AppFeedback {
  id: string;
  deckId: string;
  freeText: string;
}
