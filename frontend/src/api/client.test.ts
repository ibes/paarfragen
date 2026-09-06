import { describe, expect, it, vi } from "vitest";
import {
  fetchQuestions,
  submitAppFeedback,
  submitQuestionFeedback,
} from "./client";
import type { AppFeedback, QuestionFeedback } from "./types";

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

const FEEDBACK: QuestionFeedback = {
  id: "f1",
  questionId: "q1",
  deckId: "d1",
  rating: 5,
  freeText: "great question",
};

describe("fetchQuestions", () => {
  it("returns the parsed question list", async () => {
    const fetchImpl = vi.fn(async () =>
      jsonResponse([{ id: "q1", text: "..." }]),
    ) as unknown as typeof fetch;

    const questions = await fetchQuestions(fetchImpl);

    expect(questions).toEqual([{ id: "q1", text: "..." }]);
  });

  it("throws on a non-ok response", async () => {
    const fetchImpl = vi.fn(async () =>
      jsonResponse({}, 500),
    ) as unknown as typeof fetch;

    await expect(fetchQuestions(fetchImpl)).rejects.toThrow();
  });
});

describe("submitQuestionFeedback", () => {
  it("maps the camelCase feedback to the snake_case wire body", async () => {
    const fetchImpl = vi.fn(async () =>
      jsonResponse({}, 201),
    ) as unknown as typeof fetch;

    const result = await submitQuestionFeedback(FEEDBACK, fetchImpl);

    expect(result).toEqual({ outcome: "submitted" });
    const [, init] = vi.mocked(fetchImpl).mock.calls[0] ?? [];
    expect(JSON.parse((init?.body as string) ?? "{}")).toEqual({
      id: "f1",
      question_id: "q1",
      deck_id: "d1",
      rating: 5,
      free_text: "great question",
    });
  });

  it("reports a 404 as a rejected outcome with the server's message", async () => {
    const fetchImpl = vi.fn(async () =>
      jsonResponse({ error: { message: "Unknown question: q1" } }, 404),
    ) as unknown as typeof fetch;

    const result = await submitQuestionFeedback(FEEDBACK, fetchImpl);

    expect(result).toEqual({
      outcome: "rejected",
      status: 404,
      message: "Unknown question: q1",
    });
  });

  it("reports a thrown fetch error as a network-error outcome", async () => {
    const fetchImpl = vi.fn(async () => {
      throw new TypeError("network down");
    }) as unknown as typeof fetch;

    const result = await submitQuestionFeedback(FEEDBACK, fetchImpl);

    expect(result).toEqual({ outcome: "network-error" });
  });
});

const APP_FEEDBACK: AppFeedback = {
  id: "af1",
  deckId: "d1",
  freeText: "The rating scale feels a bit narrow.",
};

describe("submitAppFeedback", () => {
  it("maps the camelCase feedback to the snake_case wire body", async () => {
    const fetchImpl = vi.fn(async () =>
      jsonResponse({}, 201),
    ) as unknown as typeof fetch;

    const result = await submitAppFeedback(APP_FEEDBACK, fetchImpl);

    expect(result).toEqual({ outcome: "submitted" });
    const [, init] = vi.mocked(fetchImpl).mock.calls[0] ?? [];
    expect(JSON.parse((init?.body as string) ?? "{}")).toEqual({
      id: "af1",
      deck_id: "d1",
      free_text: "The rating scale feels a bit narrow.",
    });
  });

  it("reports a 400 as a rejected outcome with the server's message", async () => {
    const fetchImpl = vi.fn(async () =>
      jsonResponse(
        { error: { message: "free_text is required and must not be empty." } },
        400,
      ),
    ) as unknown as typeof fetch;

    const result = await submitAppFeedback(APP_FEEDBACK, fetchImpl);

    expect(result).toEqual({
      outcome: "rejected",
      status: 400,
      message: "free_text is required and must not be empty.",
    });
  });

  it("reports a thrown fetch error as a network-error outcome", async () => {
    const fetchImpl = vi.fn(async () => {
      throw new TypeError("network down");
    }) as unknown as typeof fetch;

    const result = await submitAppFeedback(APP_FEEDBACK, fetchImpl);

    expect(result).toEqual({ outcome: "network-error" });
  });
});
