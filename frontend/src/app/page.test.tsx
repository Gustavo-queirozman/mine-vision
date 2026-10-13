import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import Home from "./page";
import { ApiError, api } from "@/lib/api";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));
vi.mock("@/lib/api", () => ({ ApiError: class ApiError extends Error { constructor(public status: number, message: string) { super(message); } }, api: vi.fn() }));

describe("login page", () => {
  beforeEach(() => { vi.clearAllMocks(); sessionStorage.clear(); });

  it("stores returned tokens and opens the dashboard", async () => {
    vi.mocked(api).mockResolvedValue({ access_token: "a", refresh_token: "r", user: { name: "Admin" } });
    render(<Home />);
    await userEvent.click(screen.getByRole("button", { name: "Entrar" }));
    expect(await screen.findByRole("button", { name: "Entrar" })).toBeTruthy();
    expect(sessionStorage.getItem("minevision.access_token")).toBe("a");
    expect(push).toHaveBeenCalledWith("/dashboard");
  });

  it("shows a credential-specific message for unauthorized access", async () => {
    vi.mocked(api).mockRejectedValue(new ApiError(401, "invalid"));
    render(<Home />);
    await userEvent.click(screen.getByRole("button", { name: "Entrar" }));
    expect((await screen.findByRole("alert")).textContent).toContain("E-mail ou senha inválidos");
  });
});
