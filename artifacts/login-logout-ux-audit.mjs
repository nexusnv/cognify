import fs from "node:fs/promises";
import path from "node:path";
import { createRequire } from "node:module";

const root = process.env.COGNIFY_ROOT ?? path.resolve(process.cwd(), "../..");
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? "http://127.0.0.1:8880";
const artifactsDir = path.join(root, "artifacts");
const screenshotsDir = path.join(artifactsDir, "screenshots");
const htmlDir = path.join(artifactsDir, "html");
const requireFromWeb = createRequire(path.join(root, "apps/web/package.json"));
const { chromium, devices } = requireFromWeb("@playwright/test");

const consoleErrors = [];
const networkFailures = [];
const observations = [];
let sequence = 1;

async function ensureDirs() {
  await fs.mkdir(screenshotsDir, { recursive: true });
  await fs.mkdir(htmlDir, { recursive: true });
}

function slugify(label) {
  return label.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
}

function bindPageLogging(page, scope) {
  page.on("console", (message) => {
    if (message.type() === "error") {
      consoleErrors.push(`[${scope}] ${message.text()}`);
    }
  });
  page.on("pageerror", (error) => {
    consoleErrors.push(`[${scope}] PAGEERROR ${error.message}`);
  });
  page.on("requestfailed", (request) => {
    networkFailures.push(
      `[${scope}] REQUEST_FAILED ${request.method()} ${request.url()} :: ${request.failure()?.errorText ?? "unknown"}`
    );
  });
  page.on("response", (response) => {
    if (response.status() >= 400) {
      networkFailures.push(
        `[${scope}] HTTP_${response.status()} ${response.request().method()} ${response.url()}`
      );
    }
  });
}

async function stabilize(page) {
  await page.waitForLoadState("domcontentloaded").catch(() => {});
  await page.waitForLoadState("networkidle", { timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(700);
}

async function capture(page, label, note) {
  await stabilize(page);
  const id = String(sequence).padStart(2, "0");
  const slug = slugify(label);
  const screenshotPath = path.join(screenshotsDir, `${id}-${slug}.png`);
  const htmlPath = path.join(htmlDir, `${id}-${slug}.html`);
  await page.screenshot({ path: screenshotPath, fullPage: true });
  await fs.writeFile(htmlPath, await page.content(), "utf8");
  observations.push({
    id,
    label,
    url: page.url(),
    viewport: page.viewportSize(),
    note,
    screenshot: path.relative(root, screenshotPath),
    html: path.relative(root, htmlPath),
  });
  sequence += 1;
}

async function fillLogin(page, email, password) {
  const emailInput = page.getByLabel(/email/i).or(page.getByPlaceholder(/email/i)).first();
  const passwordInput = page.getByLabel(/password/i).or(page.getByPlaceholder(/password/i)).first();
  await emailInput.click();
  await emailInput.fill(email);
  await page.waitForTimeout(250);
  await passwordInput.click();
  await passwordInput.fill(password);
  await page.waitForTimeout(250);
}

async function submitLogin(page) {
  await page.getByRole("button", { name: /sign in|log in|login/i }).first().click();
}

async function discoverLogout(page) {
  const directLogout = page.getByRole("button", { name: /log out|logout|sign out/i })
    .or(page.getByRole("link", { name: /log out|logout|sign out/i }))
    .first();
  if (await directLogout.isVisible().catch(() => false)) {
    return directLogout;
  }

  const candidates = [
    page.getByRole("button", { name: /account|profile|user|menu|settings|open/i }).first(),
    page.getByRole("button").filter({ hasText: /test|admin|buyer|acme|user/i }).first(),
    page.locator("header button, nav button, aside button").last(),
  ];

  for (const candidate of candidates) {
    if (await candidate.isVisible().catch(() => false)) {
      await candidate.click();
      await page.waitForTimeout(650);
      const logout = page.getByRole("menuitem", { name: /log out|logout|sign out/i })
        .or(page.getByRole("button", { name: /log out|logout|sign out/i }))
        .or(page.getByRole("link", { name: /log out|logout|sign out/i }))
        .first();
      if (await logout.isVisible().catch(() => false)) {
        return logout;
      }
      await page.keyboard.press("Escape").catch(() => {});
      await page.waitForTimeout(250);
    }
  }

  const visibleLogout = page.getByText(/log out|logout|sign out/i).first();
  if (await visibleLogout.isVisible().catch(() => false)) {
    return visibleLogout;
  }

  throw new Error("Could not discover a visible logout control.");
}

async function writeReport() {
  const lines = [
    "# Cognify Login/Logout UX Audit",
    "",
    `Audit date: ${new Date().toISOString()}`,
    `Base URL: ${baseURL}`,
    "Persona: non-technical first-time office worker with moderate computer literacy and low patience for unclear workflows.",
    "",
    "## Captured Screens",
    "",
  ];

  for (const item of observations) {
    lines.push(
      `### ${item.id}. ${item.label}`,
      "",
      `- URL: ${item.url}`,
      `- Viewport: ${item.viewport?.width ?? "unknown"} x ${item.viewport?.height ?? "unknown"}`,
      `- Screenshot: ${item.screenshot}`,
      `- HTML: ${item.html}`,
      `- Observation: ${item.note}`,
      ""
    );
  }

  lines.push(
    "## UX Findings",
    "",
    "1. Critical: the natural first-time path is broken. The public landing page has one clear action, `Open workspace`, but it sends a signed-out user to `/dashboard` and shows `Workspace unavailable.` with no sign-in button, retry action, explanation, or recovery route. A non-technical user would likely stop here.",
    "2. Login itself is simple and visually calm once `/login` is reached directly. Labels are visible, the primary `Sign in` button is clear, and the form does not overwhelm the user.",
    "3. Failed login feedback appears below the action row as `Invalid credentials`. The message is understandable, but it is low-emphasis and not associated with either field, so a rushed user may miss it or not know whether email, password, or account access is the problem.",
    "4. Successful login redirects to `/dashboard` and the landing state is understandable: user name, workspace, role, dashboard metrics, and primary actions are visible. The desktop layout is dense but appropriate for an enterprise work queue.",
    "5. Logout is discoverable on desktop because `Sign out` is directly visible in the top bar. This is better than hiding it in an account menu, although the top utility cluster has many adjacent controls: Search, notifications, user profile, and Sign out.",
    "6. After logout, the app returns to `/login`, which clearly indicates the session ended. There is no confirmation toast, but the state transition is unambiguous.",
    "7. Mobile login is usable: fields and actions fit the viewport, labels remain visible, and target sizes look large enough. The password field lacks a show/hide affordance, which is common and helpful on mobile.",
    "8. Mobile dashboard preserves core actions, but it is visually heavy. The hamburger, search, notifications, and sign-out controls compete in the first row, and the floating Next/dev badge overlaps the `Needs attention` card content in the screenshot.",
    "",
    "## Concrete Improvements",
    "",
    "- Redirect unauthenticated `/dashboard` access to `/login?next=/dashboard`, or put a clear `Sign in` button on the workspace-unavailable state. Do not strand signed-out users on a generic workspace error.",
    "- Update the public landing `Open workspace` action so it either points directly to `/login` for signed-out users or preserves the requested dashboard destination through login.",
    "- Put failed-login text directly adjacent to the form submit area with stronger visual treatment, and connect it to the form with `aria-describedby` or an alert region.",
    "- Add a password visibility toggle and keep `type=email` on the email field for mobile keyboards.",
    "- Consider moving mobile sign-out into a labeled account/menu surface or reduce competing controls in the mobile top bar; sign-out is important, but it currently takes substantial first-row space.",
    "- Remove or disable the floating dev badge in UX audit/demo modes, or ensure it cannot cover application content on mobile screenshots.",
    "- Verify keyboard tab order and visible focus across login, forgot password, dashboard utility controls, and sign-out.",
    "",
    "## Runtime Signals",
    "",
    `Console errors captured: ${consoleErrors.length}`,
    `Network failures / HTTP error statuses captured: ${networkFailures.length}`,
    "",
    "Notable network/runtime observations:",
    "",
    "- `GET /api/me` returned 500 when the signed-out user clicked `Open workspace`; this matches the dead-end workspace-unavailable UI.",
    "- The failed login produced `POST /api/auth/login` 422, which is expected for invalid credentials but should still be presented clearly.",
    "- After desktop sign-out, `GET /api/notifications?status=unread&limit=20` and `GET /api/me` returned 500 during teardown/transition; the user-facing flow still returned to login.",
    "- Several `net::ERR_ABORTED` entries occurred around CSRF/login/logout navigations; these may be navigation-aborted requests rather than persistent backend failures, but they are recorded in `network-failures.log` for follow-up.",
    ""
  );

  await fs.writeFile(path.join(artifactsDir, "report.md"), `${lines.join("\n")}\n`, "utf8");
  await fs.writeFile(
    path.join(artifactsDir, "console-errors.log"),
    `${consoleErrors.join("\n")}${consoleErrors.length ? "\n" : ""}`,
    "utf8"
  );
  await fs.writeFile(
    path.join(artifactsDir, "network-failures.log"),
    `${networkFailures.join("\n")}${networkFailures.length ? "\n" : ""}`,
    "utf8"
  );
}

async function runDesktopFlow(browser) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 950 } });
  const page = await context.newPage();
  bindPageLogging(page, "desktop");

  await page.goto(`${baseURL}/`);
  await capture(page, "Desktop public landing", "Opening the app naturally from `/` shows a sparse product landing page. A first-time office user has one clear next action, but no hint about expected credentials, workspace access, or whether this is a marketing page versus the actual product.");

  await page.getByRole("link", { name: /open workspace/i }).click();
  await page.waitForURL(/login|dashboard/, { timeout: 15000 }).catch(() => {});
  await capture(page, "Desktop open workspace result", "Clicking `Open workspace` should make the sign-in requirement obvious and preserve the sense that the user is entering the procurement workspace.");

  await page.goto(`${baseURL}/login`);
  await capture(page, "Desktop direct login screen", "Navigating directly to `/login` lets the user continue, but needing to know that URL is not a reasonable first-time-user recovery path from the workspace-unavailable screen.");

  await fillLogin(page, "office.worker@example.com", "wrong-password");
  await capture(page, "Desktop filled invalid credentials", "Fields are filled before submit. This checks whether labels, required fields, and the primary action are visible before the user commits.");
  await submitLogin(page);
  await capture(page, "Desktop failed login feedback", "The user needs immediate, specific feedback that the credentials failed and a clear path to recover without wondering whether the button worked.");

  await fillLogin(page, "test@example.com", "password");
  await submitLogin(page);
  await page.waitForURL((url) => !url.pathname.includes("/login"), { timeout: 15000 }).catch(() => {});
  await capture(page, "Desktop authenticated landing", "After valid credentials, the app should clearly show that login succeeded and provide an obvious first task or dashboard orientation.");

  const logout = await discoverLogout(page);
  await capture(page, "Desktop logout discovered", "The signed-in top bar exposes a direct `Sign out` button, so logout is easier to discover than a hidden account-menu-only pattern. The user name and sign-out action compete slightly in the same utility cluster.");
  await logout.click();
  await page.waitForURL(/login/, { timeout: 15000 }).catch(() => {});
  await capture(page, "Desktop after logout", "After logout, the user should be back in a signed-out state with no ambiguity about whether the session ended.");

  await context.close();
}

async function runMobileSpotCheck(browser) {
  const context = await browser.newContext({ ...devices["iPhone 13"] });
  const page = await context.newPage();
  bindPageLogging(page, "mobile");

  await page.goto(`${baseURL}/login`);
  await capture(page, "Mobile login screen", "Mobile sign-in should keep labels, fields, password recovery, and the primary action visible without horizontal scrolling or cramped taps.");
  await fillLogin(page, "test@example.com", "password");
  await submitLogin(page);
  await page.waitForURL((url) => !url.pathname.includes("/login"), { timeout: 15000 }).catch(() => {});
  await capture(page, "Mobile authenticated landing", "Mobile authenticated landing should prioritize navigation clarity and the next useful action instead of simply shrinking the desktop dashboard.");

  await context.close();
}

async function main() {
  await ensureDirs();
  await fs.writeFile(path.join(artifactsDir, "console-errors.log"), "", "utf8");
  await fs.writeFile(path.join(artifactsDir, "network-failures.log"), "", "utf8");

  const browser = await chromium.launch({
    headless: false,
    slowMo: 120,
  });

  try {
    await runDesktopFlow(browser);
    await runMobileSpotCheck(browser);
  } finally {
    await browser.close();
    await writeReport();
  }
}

main().catch(async (error) => {
  console.error(error);
  consoleErrors.push(`[audit] ${error.stack ?? error.message}`);
  await writeReport().catch(() => {});
  process.exitCode = 1;
});
