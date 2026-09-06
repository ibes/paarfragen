import { API_BASE_URL } from "../config";
import type { Question, QuestionFeedback } from "./types";

export async function fetchQuestions(
  fetchImpl: typeof fetch = fetch,
): Promise<Question[]> {
  const response = await fetchImpl(`${API_BASE_URL}/questions`);
  if (!response.ok) {
    throw new Error(`GET /questions failed: ${response.status}`);
  }
  return (await response.json()) as Question[];
}

/**
 * Mirrors the three outcomes the flush loop
 * (composables/useQuestionDeck.ts) needs to distinguish — see
 * specs/2026-09-06-slice-3-frontend-api-wiring.md § "Rating submission:
 * queued, not instant": a permanent rejection drops the row, a network
 * failure keeps it queued for the next flush attempt.
 */
export type SubmitFeedbackResult =
  | { outcome: "submitted" }
  | { outcome: "rejected"; status: number; message: string }
  | { outcome: "network-error" };

export async function submitQuestionFeedback(
  feedback: QuestionFeedback,
  fetchImpl: typeof fetch = fetch,
): Promise<SubmitFeedbackResult> {
  let response: Response;
  try {
    response = await fetchImpl(`${API_BASE_URL}/question-feedback`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id: feedback.id,
        question_id: feedback.questionId,
        deck_id: feedback.deckId,
        rating: feedback.rating,
        free_text: feedback.freeText,
      }),
    });
  } catch {
    return { outcome: "network-error" };
  }

  if (response.status === 201) {
    return { outcome: "submitted" };
  }

  if (response.status === 400 || response.status === 404) {
    const body = (await response.json().catch(() => null)) as {
      error?: { message?: string };
    } | null;
    return {
      outcome: "rejected",
      status: response.status,
      message: body?.error?.message ?? "Request rejected.",
    };
  }

  // Anything else (5xx, an unexpected status) is treated like a network
  // failure: keep the row queued and try again on the next flush.
  return { outcome: "network-error" };
}
