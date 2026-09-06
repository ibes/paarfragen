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
