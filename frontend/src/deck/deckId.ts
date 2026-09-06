const STORAGE_KEY = "paarfragen:deck_id";

/**
 * Generated on first run, then persisted — never a shared/hardcoded
 * value. specs/2026-09-06-slice-3-frontend-api-wiring.md § deck_id: a
 * single hardcoded value would mean every install shares one deck.
 */
export function getOrCreateDeckId(storage: Storage = localStorage): string {
  const existing = storage.getItem(STORAGE_KEY);
  if (existing !== null) {
    return existing;
  }

  const generated = crypto.randomUUID();
  storage.setItem(STORAGE_KEY, generated);
  return generated;
}
