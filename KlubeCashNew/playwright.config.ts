import { defineConfig, devices } from "@playwright/test";

const projects = [
  ["desktop-light", { ...devices["Desktop Chrome"], defaultBrowserType: "chromium" as const, colorScheme: "light" as const }],
  ["desktop-dark", { ...devices["Desktop Chrome"], defaultBrowserType: "chromium" as const, colorScheme: "dark" as const }],
  ["tablet-light", { ...devices["iPad Mini"], defaultBrowserType: "chromium" as const, colorScheme: "light" as const }],
  ["tablet-dark", { ...devices["iPad Mini"], defaultBrowserType: "chromium" as const, colorScheme: "dark" as const }],
  ["mobile-light", { ...devices["Pixel 7"], defaultBrowserType: "chromium" as const, colorScheme: "light" as const }],
  ["mobile-dark", { ...devices["Pixel 7"], defaultBrowserType: "chromium" as const, colorScheme: "dark" as const }],
] as const;

export default defineConfig({
  testDir: "./tests/e2e",
  timeout: 45_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  reporter: "list",
  use: {
    baseURL: "http://127.0.0.1:3000",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: projects.map(([name, use]) => ({ name, use })),
  webServer: [
    {
      command: "php -d extension=openssl -d extension=pdo_mysql -S 127.0.0.1:8000 router.php",
      cwd: "..",
      url: "http://127.0.0.1:8000/api/health",
      reuseExistingServer: true,
      timeout: 30_000,
    },
    {
      command: "npm run dev",
      url: "http://127.0.0.1:3000/login",
      reuseExistingServer: true,
      timeout: 60_000,
    },
  ],
});
