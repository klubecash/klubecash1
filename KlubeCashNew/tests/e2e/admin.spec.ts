import { expect, test } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";

const root = path.resolve(process.cwd(), "..");
const sessionScript = path.join(root, "tests", "php", "admin_http_session.php");
const phpArguments = ["-d", "extension=openssl", "-d", "extension=pdo_mysql"];
let sessionId = "";

test.beforeAll(() => {
  const output = execFileSync("php", [...phpArguments, sessionScript], {
    cwd: root,
    encoding: "utf8",
  });
  sessionId = (JSON.parse(output) as { sessionId: string }).sessionId;
});

test.afterAll(() => {
  if (!sessionId) return;
  execFileSync("php", [...phpArguments, sessionScript, "destroy", sessionId], {
    cwd: root,
    encoding: "utf8",
  });
});

test.beforeEach(async ({ context }) => {
  await context.addCookies([
    {
      name: "KLCSESSID",
      value: sessionId,
      domain: "127.0.0.1",
      path: "/",
      httpOnly: true,
      sameSite: "Lax",
    },
  ]);
});

test("renderiza, respeita o tema e navega sem recarregar", async ({ page }, testInfo) => {
  await page.goto("/admin/dashboard");
  await expect(page.locator("header h1")).toHaveText("Visão geral");
  await expect(page.getByRole("complementary", { name: "Navegação administrativa" })).toBeAttached();
  await expect(page.locator("main")).toContainText("Modelo financeiro atual");

  const expectedTheme = testInfo.project.name.endsWith("dark") ? "dark" : "light";
  await expect(page.locator("html")).toHaveAttribute("data-admin-theme", expectedTheme);
  const themeButton = page.getByRole("button", {
    name: expectedTheme === "dark" ? "Ativar tema claro" : "Ativar tema escuro",
  });
  await themeButton.click();
  const changedTheme = expectedTheme === "dark" ? "light" : "dark";
  await expect(page.locator("html")).toHaveAttribute("data-admin-theme", changedTheme);
  await page.reload();
  await expect(page.locator("html")).toHaveAttribute("data-admin-theme", changedTheme);

  const mobileMenu = page.getByRole("button", { name: "Abrir menu" });
  if (await mobileMenu.isVisible()) await mobileMenu.click();
  const navigationEntriesBefore = await page.evaluate(
    () => performance.getEntriesByType("navigation").length,
  );
  await page.getByRole("link", { name: "Lojas", exact: true }).click();
  await expect(page).toHaveURL(/\/admin\/lojas$/);
  await expect(page.locator("header h1")).toHaveText("Lojas parceiras");
  const navigationEntriesAfter = await page.evaluate(
    () => performance.getEntriesByType("navigation").length,
  );
  expect(navigationEntriesAfter).toBe(navigationEntriesBefore);
});

test("mantém foco, Escape e largura responsiva", async ({ page }) => {
  await page.goto("/admin/dashboard");
  const mobileMenu = page.getByRole("button", { name: "Abrir menu" });
  if (await mobileMenu.isVisible()) {
    await mobileMenu.focus();
    await page.keyboard.press("Enter");
    await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe("hidden");
    await page.keyboard.press("Escape");
    await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe("");
  } else {
    const collapse = page.getByRole("button", { name: "Recolher menu" });
    await collapse.focus();
    await page.keyboard.press("Enter");
    await expect.poll(() => page.evaluate(() => localStorage.getItem("klube-admin-sidebar"))).toBe("collapsed");
  }
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  );
  expect(overflow).toBeLessThanOrEqual(1);

  await page.goto("/admin/usuarios");
  const newUser = page.getByRole("button", { name: "Novo usuário" });
  await newUser.click();
  const dialog = page.getByRole("dialog", { name: "Novo usuário" });
  await expect(dialog).toBeVisible();
  await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe("hidden");
  expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press("Escape");
  await expect(dialog).toBeHidden();
  await expect(newUser).toBeFocused();
});

test("protege a rota quando a sessão não existe", async ({ browser }) => {
  const context = await browser.newContext();
  const page = await context.newPage();
  await page.goto("http://127.0.0.1:3000/admin/dashboard");
  await expect(page).toHaveURL(/\/login\?redirect=/);
  await context.close();
});
