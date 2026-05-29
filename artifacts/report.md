# Cognify Login/Logout UX Audit

Audit date: 2026-05-29T07:36:00.390Z
Base URL: http://127.0.0.1:8880
Persona: non-technical first-time office worker with moderate computer literacy and low patience for unclear workflows.

## Captured Screens

### 01. Desktop public landing

- URL: http://127.0.0.1:8880/
- Viewport: 1440 x 950
- Screenshot: artifacts/screenshots/01-desktop-public-landing.png
- HTML: artifacts/html/01-desktop-public-landing.html
- Observation: Opening the app naturally from `/` shows a sparse product landing page. A first-time office user has one clear next action, but no hint about expected credentials, workspace access, or whether this is a marketing page versus the actual product.

### 02. Desktop open workspace result

- URL: http://127.0.0.1:8880/dashboard
- Viewport: 1440 x 950
- Screenshot: artifacts/screenshots/02-desktop-open-workspace-result.png
- HTML: artifacts/html/02-desktop-open-workspace-result.html
- Observation: Clicking `Open workspace` should make the sign-in requirement obvious and preserve the sense that the user is entering the procurement workspace.

### 03. Desktop direct login screen

- URL: http://127.0.0.1:8880/login
- Viewport: 1440 x 950
- Screenshot: artifacts/screenshots/03-desktop-direct-login-screen.png
- HTML: artifacts/html/03-desktop-direct-login-screen.html
- Observation: Navigating directly to `/login` lets the user continue, but needing to know that URL is not a reasonable first-time-user recovery path from the workspace-unavailable screen.

### 04. Desktop filled invalid credentials

- URL: http://127.0.0.1:8880/login
- Viewport: 1440 x 950
- Screenshot: artifacts/screenshots/04-desktop-filled-invalid-credentials.png
- HTML: artifacts/html/04-desktop-filled-invalid-credentials.html
- Observation: Fields are filled before submit. This checks whether labels, required fields, and the primary action are visible before the user commits.

### 05. Desktop failed login feedback

- URL: http://127.0.0.1:8880/login
- Viewport: 1440 x 950
- Screenshot: artifacts/screenshots/05-desktop-failed-login-feedback.png
- HTML: artifacts/html/05-desktop-failed-login-feedback.html
- Observation: The user needs immediate, specific feedback that the credentials failed and a clear path to recover without wondering whether the button worked.

### 06. Desktop authenticated landing

- URL: http://127.0.0.1:8880/dashboard
- Viewport: 1440 x 950
- Screenshot: artifacts/screenshots/06-desktop-authenticated-landing.png
- HTML: artifacts/html/06-desktop-authenticated-landing.html
- Observation: After valid credentials, the app should clearly show that login succeeded and provide an obvious first task or dashboard orientation.

### 07. Desktop logout discovered

- URL: http://127.0.0.1:8880/dashboard
- Viewport: 1440 x 950
- Screenshot: artifacts/screenshots/07-desktop-logout-discovered.png
- HTML: artifacts/html/07-desktop-logout-discovered.html
- Observation: The signed-in top bar exposes a direct `Sign out` button, so logout is easier to discover than a hidden account-menu-only pattern. The user name and sign-out action compete slightly in the same utility cluster.

### 08. Desktop after logout

- URL: http://127.0.0.1:8880/login
- Viewport: 1440 x 950
- Screenshot: artifacts/screenshots/08-desktop-after-logout.png
- HTML: artifacts/html/08-desktop-after-logout.html
- Observation: After logout, the user should be back in a signed-out state with no ambiguity about whether the session ended.

### 09. Mobile login screen

- URL: http://127.0.0.1:8880/login
- Viewport: 390 x 664
- Screenshot: artifacts/screenshots/09-mobile-login-screen.png
- HTML: artifacts/html/09-mobile-login-screen.html
- Observation: Mobile sign-in should keep labels, fields, password recovery, and the primary action visible without horizontal scrolling or cramped taps.

### 10. Mobile authenticated landing

- URL: http://127.0.0.1:8880/dashboard
- Viewport: 390 x 664
- Screenshot: artifacts/screenshots/10-mobile-authenticated-landing.png
- HTML: artifacts/html/10-mobile-authenticated-landing.html
- Observation: Mobile authenticated landing should prioritize navigation clarity and the next useful action instead of simply shrinking the desktop dashboard.

## UX Findings

1. Critical: the natural first-time path is broken. The public landing page has one clear action, `Open workspace`, but it sends a signed-out user to `/dashboard` and shows `Workspace unavailable.` with no sign-in button, retry action, explanation, or recovery route. A non-technical user would likely stop here.
2. Login itself is simple and visually calm once `/login` is reached directly. Labels are visible, the primary `Sign in` button is clear, and the form does not overwhelm the user.
3. Failed login feedback appears below the action row as `Invalid credentials`. The message is understandable, but it is low-emphasis and not associated with either field, so a rushed user may miss it or not know whether email, password, or account access is the problem.
4. Successful login redirects to `/dashboard` and the landing state is understandable: user name, workspace, role, dashboard metrics, and primary actions are visible. The desktop layout is dense but appropriate for an enterprise work queue.
5. Logout is discoverable on desktop because `Sign out` is directly visible in the top bar. This is better than hiding it in an account menu, although the top utility cluster has many adjacent controls: Search, notifications, user profile, and Sign out.
6. After logout, the app returns to `/login`, which clearly indicates the session ended. There is no confirmation toast, but the state transition is unambiguous.
7. Mobile login is usable: fields and actions fit the viewport, labels remain visible, and target sizes look large enough. The password field lacks a show/hide affordance, which is common and helpful on mobile.
8. Mobile dashboard preserves core actions, but it is visually heavy. The hamburger, search, notifications, and sign-out controls compete in the first row, and the floating Next/dev badge overlaps the `Needs attention` card content in the screenshot.

## Concrete Improvements

- Redirect unauthenticated `/dashboard` access to `/login?next=/dashboard`, or put a clear `Sign in` button on the workspace-unavailable state. Do not strand signed-out users on a generic workspace error.
- Update the public landing `Open workspace` action so it either points directly to `/login` for signed-out users or preserves the requested dashboard destination through login.
- Put failed-login text directly adjacent to the form submit area with stronger visual treatment, and connect it to the form with `aria-describedby` or an alert region.
- Add a password visibility toggle and keep `type=email` on the email field for mobile keyboards.
- Consider moving mobile sign-out into a labeled account/menu surface or reduce competing controls in the mobile top bar; sign-out is important, but it currently takes substantial first-row space.
- Remove or disable the floating dev badge in UX audit/demo modes, or ensure it cannot cover application content on mobile screenshots.
- Verify keyboard tab order and visible focus across login, forgot password, dashboard utility controls, and sign-out.

## Runtime Signals

Console errors captured: 4
Network failures / HTTP error statuses captured: 10

Notable network/runtime observations:

- `GET /api/me` returned 500 when the signed-out user clicked `Open workspace`; this matches the dead-end workspace-unavailable UI.
- The failed login produced `POST /api/auth/login` 422, which is expected for invalid credentials but should still be presented clearly.
- After desktop sign-out, `GET /api/notifications?status=unread&limit=20` and `GET /api/me` returned 500 during teardown/transition; the user-facing flow still returned to login.
- Several `net::ERR_ABORTED` entries occurred around CSRF/login/logout navigations; these may be navigation-aborted requests rather than persistent backend failures, but they are recorded in `network-failures.log` for follow-up.

