/** Shared by every composable that persists state to localStorage. */
export function readJson<T>(key: string, fallback: T, storage: Storage): T {
  const raw = storage.getItem(key);
  if (raw === null) {
    return fallback;
  }
  try {
    return JSON.parse(raw) as T;
  } catch {
    return fallback;
  }
}

export function writeJson(key: string, value: unknown, storage: Storage): void {
  storage.setItem(key, JSON.stringify(value));
}
