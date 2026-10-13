import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import Dashboard from "./page";
import { api } from "@/lib/api";

vi.mock("next/navigation", () => ({ useRouter: () => ({ replace: vi.fn() }) }));
vi.mock("@/lib/api", () => ({ api: vi.fn(), apiBlob: vi.fn() }));
vi.mock("./OperationalMap", () => ({ default: () => null }));

describe("image pagination", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    sessionStorage.setItem("minevision.access_token", "test");
    vi.mocked(api).mockImplementation(async path => {
      if (path === "/auth/me") return { id: 1, name: "Admin" };
      if (path === "/dashboard/summary") return { counts: {}, recent_alerts: [] };
      if (path.startsWith("/satellite-images?")) {
        const params = new URLSearchParams(path.split("?")[1]);
        const page = Number(params.get("page"));
        return {
          data: page === 1 ? Array.from({ length: 20 }, (_, i) => ({ id: i + 1 })) : [{ id: 21 }],
          meta: { page, per_page: 20, total: 21, last_page: 2 },
        };
      }
      return { data: [], meta: { page: 1, per_page: 100, total: 0, last_page: 1 } };
    });
  });

  it("loads the next image page and resets to page one when the search changes", async () => {
    const user = userEvent.setup();
    render(<Dashboard />);
    await user.click(screen.getByRole("button", { name: "Imagens" }));
    expect(await screen.findByText("1–20 de 21 imagens")).toBeTruthy();
    expect(vi.mocked(api)).toHaveBeenCalledWith(expect.stringContaining("/satellite-images?per_page=20&page=1"));

    await user.click(screen.getByRole("button", { name: "Próxima" }));
    expect(await screen.findByText("21–21 de 21 imagens")).toBeTruthy();
    expect(vi.mocked(api)).toHaveBeenCalledWith(expect.stringContaining("/satellite-images?per_page=20&page=2"));
    expect(screen.getByRole("button", { name: "Próxima" }).hasAttribute("disabled")).toBe(true);

    await user.type(screen.getByRole("textbox", { name: "Buscar registros" }), "nova");
    await waitFor(() => expect(screen.getByText("1–20 de 21 imagens")).toBeTruthy());
    expect(screen.getByText("Página 1 de 2")).toBeTruthy();
  });
});

describe("regulation module", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    sessionStorage.setItem("minevision.access_token", "test");
    vi.mocked(api).mockImplementation(async path => {
      if (path === "/auth/me") return { id: 1, name: "Admin" };
      if (path === "/dashboard/summary") return { counts: {}, recent_alerts: [] };
      return { data: [], meta: { page: 1, per_page: 100, total: 0, last_page: 1 } };
    });
  });

  it("centralizes regulatory records outside the generic registrations", async () => {
    const user = userEvent.setup();
    render(<Dashboard />);
    await user.click(screen.getByRole("button", { name: "Regulamentação" }));
    expect(await screen.findByRole("heading", { name: "Regulamentação em um único lugar." })).toBeTruthy();
    expect(screen.getByRole("heading", { name: "Camadas regulatórias" })).toBeTruthy();
    expect(screen.getByRole("heading", { name: "Licenças e estruturas" })).toBeTruthy();
    expect(screen.getByRole("heading", { name: "Regras de acompanhamento" })).toBeTruthy();
  });
});
