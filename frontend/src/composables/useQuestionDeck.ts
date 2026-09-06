import { computed, ref } from "vue";
import { fetchQuestions, submitQuestionFeedback } from "../api/client";
import type { Question, QuestionFeedback } from "../api/types";
import type { Rating } from "../config";
import { FLUSH_THRESHOLD } from "../config";
import { getOrCreateDeckId } from "../deck/deckId";
import { readJson, writeJson } from "../storage";

const QUESTIONS_KEY = "paarfragen:questions";
const RATED_IDS_KEY = "paarfragen:rated_question_ids";
const PENDING_KEY = "paarfragen:pending_feedback";

export interface UseQuestionDeckOptions {
  fetchImpl?: typeof fetch;
  storage?: Storage;
}

/**
 * No lifecycle hooks (onMounted/…) in here on purpose — this stays
 * callable directly from a plain test, not only from within a mounted
 * component. The caller (App.vue) wires loadQuestions()/flush() to
 * onMounted and the browser's `online` event itself.
 */
export function useQuestionDeck(options: UseQuestionDeckOptions = {}) {
  const fetchImpl = options.fetchImpl ?? fetch;
  const storage = options.storage ?? localStorage;

  const deckId = getOrCreateDeckId(storage);
  const questions = ref<Question[]>(readJson(QUESTIONS_KEY, [], storage));
  const ratedQuestionIds = ref<Set<string>>(
    new Set(readJson<string[]>(RATED_IDS_KEY, [], storage)),
  );
  const pendingFeedback = ref<QuestionFeedback[]>(
    readJson(PENDING_KEY, [], storage),
  );
  const currentQuestion = ref<Question | null>(null);
  const isLoading = ref(true);

  const pendingCount = computed(() => pendingFeedback.value.length);
  const isDone = computed(
    () =>
      questions.value.length > 0 &&
      questions.value.every((question) =>
        ratedQuestionIds.value.has(question.id),
      ),
  );

  function persistQuestions(): void {
    writeJson(QUESTIONS_KEY, questions.value, storage);
  }

  function persistRatedIds(): void {
    writeJson(RATED_IDS_KEY, [...ratedQuestionIds.value], storage);
  }

  function persistPending(): void {
    writeJson(PENDING_KEY, pendingFeedback.value, storage);
  }

  function pickNextQuestion(): void {
    const unrated = questions.value.filter(
      (question) => !ratedQuestionIds.value.has(question.id),
    );
    if (unrated.length === 0) {
      currentQuestion.value = null;
      return;
    }
    currentQuestion.value =
      unrated[Math.floor(Math.random() * unrated.length)] ?? null;
  }

  async function loadQuestions(): Promise<void> {
    try {
      const fetched = await fetchQuestions(fetchImpl);
      const known = new Set(questions.value.map((question) => question.id));
      for (const question of fetched) {
        if (!known.has(question.id)) {
          questions.value.push(question);
          known.add(question.id);
        }
      }
      persistQuestions();
    } catch {
      // Offline or unreachable — keep working from whatever's cached.
    }
    isLoading.value = false;
    pickNextQuestion();
  }

  function rate(rating: Rating, freeText: string | null): void {
    if (currentQuestion.value === null) {
      return;
    }

    const feedback: QuestionFeedback = {
      id: crypto.randomUUID(),
      questionId: currentQuestion.value.id,
      deckId,
      rating,
      freeText,
    };

    pendingFeedback.value.push(feedback);
    ratedQuestionIds.value.add(feedback.questionId);
    persistPending();
    persistRatedIds();

    pickNextQuestion();

    if (pendingFeedback.value.length >= FLUSH_THRESHOLD) {
      void flush();
    }
  }

  async function flush(): Promise<void> {
    const remaining: QuestionFeedback[] = [];

    for (const feedback of pendingFeedback.value) {
      const result = await submitQuestionFeedback(feedback, fetchImpl);

      if (result.outcome === "network-error") {
        remaining.push(feedback);
      } else if (result.outcome === "rejected") {
        console.error(
          `Dropping feedback ${feedback.id} (${result.status}): ${result.message}`,
        );
      }
      // outcome === "submitted" (fresh or a duplicate-id replay): drop it, nothing to keep.
    }

    pendingFeedback.value = remaining;
    persistPending();
  }

  return {
    currentQuestion,
    isLoading,
    isDone,
    pendingCount,
    loadQuestions,
    rate,
    /** Skip the current question without rating it — picks a new random unrated one. */
    skip: pickNextQuestion,
    flush,
  };
}
