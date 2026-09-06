import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { FLUSH_THRESHOLD } from "../config";
import { useQuestionDeck } from "./useQuestionDeck";

const SEED_QUESTIONS = [
  { id: "q1", text: "What made you smile today?" },
  { id: "q2", text: "What's a small habit you love?" },
];

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function fetchStub(handlers: {
  questions?: () => Response;
  feedback?: () => Response | Promise<Response>;
}): typeof fetch {
  return vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input);
    if (url.endsWith("/questions")) {
      return (handlers.questions ?? (() => jsonResponse(SEED_QUESTIONS)))();
    }
    if (url.endsWith("/question-feedback") && init?.method === "POST") {
      return (handlers.feedback ?? (() => jsonResponse({}, 201)))();
    }
    throw new Error(`unexpected fetch: ${url}`);
  }) as unknown as typeof fetch;
}

describe("useQuestionDeck", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("has no current question until loadQuestions resolves", () => {
    const deck = useQuestionDeck({ fetchImpl: fetchStub({}) });

    expect(deck.currentQuestion.value).toBeNull();
    expect(deck.isLoading.value).toBe(true);
    expect(deck.isDone.value).toBe(false);
  });

  it("picks a question after loading", async () => {
    const deck = useQuestionDeck({ fetchImpl: fetchStub({}) });

    await deck.loadQuestions();

    expect(deck.isLoading.value).toBe(false);
    expect(deck.currentQuestion.value).not.toBeNull();
    expect(SEED_QUESTIONS.map((q) => q.id)).toContain(
      deck.currentQuestion.value?.id,
    );
  });

  it("rate() queues locally and advances without calling fetch", async () => {
    const fetchImpl = fetchStub({});
    const deck = useQuestionDeck({ fetchImpl });
    await deck.loadQuestions();
    const rated = deck.currentQuestion.value;

    deck.rate(5, "loved it");

    expect(deck.pendingCount.value).toBe(1);
    expect(deck.currentQuestion.value?.id).not.toBe(rated?.id);
    expect(fetchImpl).toHaveBeenCalledTimes(1); // only the GET /questions from loadQuestions
  });

  it("skip() advances without recording feedback", async () => {
    const deck = useQuestionDeck({ fetchImpl: fetchStub({}) });
    await deck.loadQuestions();

    deck.skip();

    expect(deck.pendingCount.value).toBe(0);
  });

  it("reaching the flush threshold triggers an automatic flush", async () => {
    const manyQuestions = Array.from({ length: FLUSH_THRESHOLD }, (_, i) => ({
      id: `q${i}`,
      text: `question ${i}`,
    }));
    const fetchImpl = fetchStub({
      questions: () => jsonResponse(manyQuestions),
    });
    const deck = useQuestionDeck({ fetchImpl });
    await deck.loadQuestions();

    for (let i = 0; i < FLUSH_THRESHOLD; i++) {
      deck.rate(1, null);
    }
    await vi.waitFor(() => expect(deck.pendingCount.value).toBe(0));

    // 1 GET /questions + FLUSH_THRESHOLD POSTs.
    expect(fetchImpl).toHaveBeenCalledTimes(1 + FLUSH_THRESHOLD);
  });

  it("flush() drops a permanently-rejected row and logs it", async () => {
    const errorSpy = vi.spyOn(console, "error").mockImplementation(() => {});
    const deck = useQuestionDeck({
      fetchImpl: fetchStub({
        feedback: () => jsonResponse({ error: { message: "nope" } }, 404),
      }),
    });
    await deck.loadQuestions();
    deck.rate(1, null);

    await deck.flush();

    expect(deck.pendingCount.value).toBe(0);
    expect(errorSpy).toHaveBeenCalledOnce();
    errorSpy.mockRestore();
  });

  it("flush() keeps a row queued after a network failure", async () => {
    const deck = useQuestionDeck({
      fetchImpl: fetchStub({
        feedback: () => {
          throw new TypeError("network down");
        },
      }),
    });
    await deck.loadQuestions();
    deck.rate(1, null);

    await deck.flush();

    expect(deck.pendingCount.value).toBe(1);
  });

  it("rated_question_ids stays true after a successful flush (no reappearing question)", async () => {
    const deck = useQuestionDeck({ fetchImpl: fetchStub({}) });
    await deck.loadQuestions();
    deck.rate(5, null);
    deck.rate(5, null); // rates all of SEED_QUESTIONS
    await deck.flush();
    expect(deck.pendingCount.value).toBe(0);

    // A fresh instance reading the same storage must still treat that
    // question as rated, even though the queue that carried it is now empty.
    const secondDeck = useQuestionDeck({ fetchImpl: fetchStub({}) });
    await secondDeck.loadQuestions();

    expect(secondDeck.isDone.value).toBe(true);
    expect(secondDeck.currentQuestion.value).toBeNull();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });
});
