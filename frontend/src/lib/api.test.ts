import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError, api, apiBlob } from "./api";

const response = (body: unknown, status = 200) => new Response(
  body === undefined ? null : JSON.stringify(body),
  { status, headers: { "Content-Type": "application/json" } },
);

describe("api", () => {
  afterEach(() => {
    sessionStorage.clear();
    vi.unstubAllGlobals();
  });

  it("adds JSON and bearer headers and unwraps data responses", async () => {
    sessionStorage.setItem("minevision.access_token", "access");
    const fetchMock = vi.fn().mockResolvedValue(response({ data: { id: 7 } }));
    vi.stubGlobal("fetch", fetchMock);

    await expect(api<{ id: number }>("/mines", { method: "POST", body: "{}" })).resolves.toEqual({ id: 7 });
    expect(fetchMock).toHaveBeenCalledWith("/api/v1/mines", expect.objectContaining({
      headers: expect.any(Headers),
    }));
    const headers = fetchMock.mock.calls[0][1].headers as Headers;
    expect(headers.get("Authorization")).toBe("Bearer access");
    expect(headers.get("Content-Type")).toBe("application/json");
  });

  it("refreshes an expired session once and retries the original request", async () => {
    sessionStorage.setItem("minevision.access_token", "old");
    sessionStorage.setItem("minevision.refresh_token", "refresh");
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(response({ error: { message: "expired" } }, 401))
      .mockResolvedValueOnce(response({ data: { access_token: "new", refresh_token: "next" } }))
      .mockResolvedValueOnce(response({ data: { ok: true } }));
    vi.stubGlobal("fetch", fetchMock);

    await expect(api<{ ok: boolean }>("/me")).resolves.toEqual({ ok: true });
    expect(fetchMock.mock.calls.map(([url]) => url)).toEqual(["/api/v1/me", "/api/v1/auth/refresh", "/api/v1/me"]);
    expect(sessionStorage.getItem("minevision.access_token")).toBe("new");
  });

  it("preserves the API error message", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(response({ error: { message: "Sem acesso" } }, 403)));
    await expect(api("/restricted")).rejects.toEqual(expect.objectContaining({ status: 403, message: "Sem acesso" }));
  });

  it("rejects relative API paths before issuing a request", async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    await expect(api("mines")).rejects.toThrow("deve começar com /");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("returns blobs and reports failed downloads", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("report", { status: 200 })));
    expect(await (await apiBlob("/reports/1/download")).text()).toBe("report");
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status: 404 })));
    await expect(apiBlob("/reports/1/download")).rejects.toBeInstanceOf(ApiError);
  });

  it("refreshes an expired session before retrying a protected download", async () => {
    sessionStorage.setItem("minevision.access_token", "old");
    sessionStorage.setItem("minevision.refresh_token", "refresh");
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(new Response(null, { status: 401 }))
      .mockResolvedValueOnce(response({ data: { access_token: "new", refresh_token: "next" } }))
      .mockResolvedValueOnce(new Response("report", { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);

    await expect((await apiBlob("/reports/1/html")).text()).resolves.toBe("report");
    expect((fetchMock.mock.calls[2][1].headers as Record<string, string>).Authorization).toBe("Bearer new");
  });
});
