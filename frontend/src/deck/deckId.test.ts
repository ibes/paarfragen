import { beforeEach, describe, expect, it } from "vitest";
import { getOrCreateDeckId } from "./deckId";

describe("getOrCreateDeckId", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("generates a UUID on first call", () => {
    const deckId = getOrCreateDeckId();

    expect(deckId).toMatch(/^[0-9a-f-]{36}$/i);
  });

  it("returns the same id on subsequent calls", () => {
    const first = getOrCreateDeckId();
    const second = getOrCreateDeckId();

    expect(second).toBe(first);
  });

  it("persists across a fresh read of the same storage", () => {
    const first = getOrCreateDeckId();

    expect(localStorage.getItem("paarfragen:deck_id")).toBe(first);
  });
});
