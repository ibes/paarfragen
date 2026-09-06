<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
import { useAppFeedback } from "./composables/useAppFeedback";
import { useQuestionDeck } from "./composables/useQuestionDeck";
import { RATING_OPTIONS, type Rating } from "./config";

const { currentQuestion, isLoading, isDone, loadQuestions, rate, skip, flush } =
  useQuestionDeck();
const freeText = ref("");

function submitRating(value: Rating): void {
  const trimmed = freeText.value.trim();
  rate(value, trimmed === "" ? null : trimmed);
  freeText.value = "";
}

const appFeedback = useAppFeedback();
const feedbackModalOpen = ref(false);
const feedbackText = ref("");
const feedbackJustSubmitted = ref(false);
let feedbackCloseTimeout: ReturnType<typeof setTimeout> | undefined;

function openFeedbackModal(): void {
  feedbackModalOpen.value = true;
  feedbackJustSubmitted.value = false;
}

/**
 * Doesn't await appFeedback.submit() before clearing/confirming/
 * closing — the whole point (specs/2026-09-06-slice-4-app-feedback.md
 * § "Submission: offline behavior") is that the UI never waits on the
 * network, whether the POST resolves immediately or the row falls
 * back to the local pending queue.
 */
function submitFeedback(): void {
  const trimmed = feedbackText.value.trim();
  if (trimmed === "") {
    return;
  }

  void appFeedback.submit(trimmed);
  feedbackText.value = "";
  feedbackJustSubmitted.value = true;

  feedbackCloseTimeout = setTimeout(() => {
    feedbackModalOpen.value = false;
    feedbackJustSubmitted.value = false;
  }, 1500);
}

function handleOnline(): void {
  void flush();
  void appFeedback.flush();
}

onMounted(() => {
  window.addEventListener("online", handleOnline);
  void loadQuestions();
  void flush();
  void appFeedback.flush();
});

onUnmounted(() => {
  window.removeEventListener("online", handleOnline);
  clearTimeout(feedbackCloseTimeout);
});
</script>

<template>
  <main>
    <h1>paarfragen</h1>

    <div v-if="currentQuestion" class="question-screen">
      <p class="question">{{ currentQuestion.text }}</p>

      <div class="ratings">
        <button
          v-for="option in RATING_OPTIONS"
          :key="option.value"
          type="button"
          @click="submitRating(option.value)"
        >
          {{ option.label }}
        </button>
      </div>

      <textarea
        v-model="freeText"
        placeholder="Anything you want to add? (optional)"
      ></textarea>

      <button type="button" class="next" @click="skip">Next</button>
    </div>

    <p v-else-if="isDone">
      You've rated every question we've got right now — thank you!
    </p>

    <p v-else-if="isLoading">Loading…</p>

    <p v-else>No questions available right now.</p>

    <button
      type="button"
      class="feedback-entry"
      aria-label="Give app feedback"
      @click="openFeedbackModal"
    >
      💬
    </button>

    <div v-if="feedbackModalOpen" class="feedback-modal-backdrop">
      <div class="feedback-modal" role="dialog" aria-modal="true">
        <template v-if="feedbackJustSubmitted">
          <p>Thanks for the feedback!</p>
        </template>
        <template v-else>
          <textarea
            v-model="feedbackText"
            placeholder="What's on your mind about the app itself?"
            autofocus
          ></textarea>
          <div class="feedback-modal-actions">
            <button type="button" @click="feedbackModalOpen = false">
              Cancel
            </button>
            <button type="button" @click="submitFeedback">Send</button>
          </div>
        </template>
      </div>
    </div>
  </main>
</template>
