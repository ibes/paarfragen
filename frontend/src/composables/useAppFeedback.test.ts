import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useAppFeedback } from "./useAppFeedback";

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function fetchStub(
  handler: () => Response | Promise<Response> = () => jsonResponse({}, 201),
): typeof fetch {
  return vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input);
    if (url.endsWith("/app-feedback") && init?.method === "POST") {
      return handler();
    }
    throw new Error(`unexpected fetch: ${url}`);
  }) as unknown as typeof fetch;
}

describe("useAppFeedback", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("submit() posts immediately and keeps nothing queued on success", async () => {
    const fetchImpl = fetchStub();
    const feedback = useAppFeedback({ fetchImpl });

    await feedback.submit("The rating scale feels a bit narrow.");

    expect(fetchImpl).toHaveBeenCalledTimes(1);
    expect(feedback.pendingCount.value).toBe(0);
  });

  it("submit() ignores blank/whitespace-only text without calling fetch", async () => {
    const fetchImpl = fetchStub();
    const feedback = useAppFeedback({ fetchImpl });

    await feedback.submit("   ");

    expect(fetchImpl).not.toHaveBeenCalled();
    expect(feedback.pendingCount.value).toBe(0);
  });

  it("submit() queues the row on a network failure instead of dropping it", async () => {
    const fetchImpl = fetchStub(() => {
      throw new TypeError("network down");
    });
    const feedback = useAppFeedback({ fetchImpl });

    await feedback.submit("Feedback while offline.");

    expect(feedback.pendingCount.value).toBe(1);
  });

  it("submit() drops a permanently-rejected row and logs it", async () => {
    const errorSpy = vi.spyOn(console, "error").mockImplementation(() => {});
    const fetchImpl = fetchStub(() =>
      jsonResponse({ error: { message: "free_text is required." } }, 400),
    );
    const feedback = useAppFeedback({ fetchImpl });

    await feedback.submit("This gets rejected.");

    expect(feedback.pendingCount.value).toBe(0);
    expect(errorSpy).toHaveBeenCalledOnce();
    errorSpy.mockRestore();
  });

  it("flush() retries a queued row and clears it once it succeeds", async () => {
    let shouldFail = true;
    const fetchImpl = fetchStub(() => {
      if (shouldFail) {
        throw new TypeError("network down");
      }
      return jsonResponse({}, 201);
    });
    const feedback = useAppFeedback({ fetchImpl });
    await feedback.submit("Queued while offline.");
    expect(feedback.pendingCount.value).toBe(1);

    shouldFail = false;
    await feedback.flush();

    expect(feedback.pendingCount.value).toBe(0);
  });

  it("a queued row survives across instances reading the same storage", async () => {
    const fetchImpl = fetchStub(() => {
      throw new TypeError("network down");
    });
    const feedback = useAppFeedback({ fetchImpl });
    await feedback.submit("Still pending after reload.");
    expect(feedback.pendingCount.value).toBe(1);

    const reloaded = useAppFeedback({ fetchImpl });

    expect(reloaded.pendingCount.value).toBe(1);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });
});
