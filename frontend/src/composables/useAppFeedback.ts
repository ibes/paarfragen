import { computed, ref } from "vue";
import { submitAppFeedback } from "../api/client";
import type { AppFeedback } from "../api/types";
import { getOrCreateDeckId } from "../deck/deckId";
import { readJson, writeJson } from "../storage";

const PENDING_KEY = "paarfragen:pending_app_feedback";

export interface UseAppFeedbackOptions {
  fetchImpl?: typeof fetch;
  storage?: Storage;
}

/**
 * Deliberately different from useQuestionDeck's threshold/online/
 * app-start queue: app-feedback is rare (0-1 per session, not one per
 * rated question), so a count threshold would rarely fire, leaving a
 * submission stuck locally indefinitely. Instead: try the POST
 * immediately on submit(), and only fall back to a queue (flushed on
 * 'online'/app start, no threshold) on a network failure — see
 * specs/2026-09-06-slice-4-app-feedback.md § "Submission: offline
 * behavior". No lifecycle hooks here for the same reason as
 * useQuestionDeck — App.vue wires flush() to onMounted/'online'.
 */
export function useAppFeedback(options: UseAppFeedbackOptions = {}) {
  const fetchImpl = options.fetchImpl ?? fetch;
  const storage = options.storage ?? localStorage;

  const deckId = getOrCreateDeckId(storage);
  const pending = ref<AppFeedback[]>(readJson(PENDING_KEY, [], storage));
  const pendingCount = computed(() => pending.value.length);

  function persistPending(): void {
    writeJson(PENDING_KEY, pending.value, storage);
  }

  async function submit(freeText: string): Promise<void> {
    const trimmed = freeText.trim();
    if (trimmed === "") {
      return;
    }

    const feedback: AppFeedback = {
      id: crypto.randomUUID(),
      deckId,
      freeText: trimmed,
    };

    const result = await submitAppFeedback(feedback, fetchImpl);

    if (result.outcome === "network-error") {
      pending.value.push(feedback);
      persistPending();
    } else if (result.outcome === "rejected") {
      console.error(
        `Dropping app feedback ${feedback.id} (${result.status}): ${result.message}`,
      );
    }
    // outcome === "submitted": nothing to keep.
  }

  async function flush(): Promise<void> {
    const remaining: AppFeedback[] = [];

    for (const feedback of pending.value) {
      const result = await submitAppFeedback(feedback, fetchImpl);

      if (result.outcome === "network-error") {
        remaining.push(feedback);
      } else if (result.outcome === "rejected") {
        console.error(
          `Dropping app feedback ${feedback.id} (${result.status}): ${result.message}`,
        );
      }
    }

    pending.value = remaining;
    persistPending();
  }

  return {
    pendingCount,
    submit,
    flush,
  };
}
