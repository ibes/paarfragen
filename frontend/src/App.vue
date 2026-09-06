<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
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

function handleOnline(): void {
  void flush();
}

onMounted(() => {
  window.addEventListener("online", handleOnline);
  void loadQuestions();
  void flush();
});

onUnmounted(() => {
  window.removeEventListener("online", handleOnline);
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
  </main>
</template>
