# SchuLyf — Pilot Funnel Prototype

A **navigable static HTML/CSS/JS prototype** of the SchuLyf redesign's auth +
applicant funnel. Its only purpose is to let the product owner **click through and
visually validate look & flow** before we build it in the real Vue/Inertia app.

> ⚠️ **Throwaway validation artifact — NOT the production app.** No build step, no
> framework, no backend, no real data. The real implementation lives in
> `resources/js/...` and uses Inertia + Vue 3 + PrimeVue/Aura + Wayfinder.

## How to open it

1. Double-click **`index.html`** (or `login.html`) — it opens in your browser over
   `file://`. No server, no `npm`, nothing to install.
2. `index.html` immediately redirects to `login.html`, the funnel entry point.
3. An internet connection is needed the first time so the CDNs (Tailwind Play, Lucide
   icons, Google Fonts) can load.

## What's included (5 screens)

| File | Screen | Shell |
|---|---|---|
| `login.html` | Login — split brand/form | none (auth) |
| `register.html` | Register — split brand/form | none (auth) |
| `dashboard.html` | Applicant dashboard — hero + status chips + table | app shell |
| `application.html` | New application — 4-step wizard | app shell |
| `index.html` | Entry redirect → `login.html` | — |
| `assets/app.css` | Shared tokens, focus rings, status badges, drawer/scrim | — |
| `assets/app.js` | Theme toggle, mobile drawer, menus, password toggle, wizard | — |

### Click-through paths (every link works)

- Login **Log in** → dashboard · Login ↔ Register
- Dashboard **New application** (hero, empty-state CTA, sidebar) → application
- Application **Submit application** → dashboard · breadcrumb **Dashboard** → dashboard
- Sidebar **Dashboard / New application** on both shelled pages
- User-menu / mobile-drawer **Sign out** → login (on every shelled page)

### Interactive bits to try

- **Theme toggle** (topbar on shelled pages; under the form on auth pages) — dark mode
  works on every screen and is **persisted in `localStorage`** (`schulyf-theme`).
- **Mobile drawer** — narrow the window below `lg`; the topbar `≡` opens an off-canvas
  sidebar with a scrim (click scrim / `Esc` / `✕` to close).
- **Password show/hide** on login + register.
- **Dashboard empty-state preview** — the dashed "Preview: empty state" button swaps the
  populated table for the empty-state design so the PO can see both.
- **Application wizard** — Back/Continue moves one step at a time; the step indicator
  fills in; the level − / + stepper is bounded 1–3; choosing files updates the card and
  the **Review** step mirrors every entered value + chosen filename live.
- **Table horizontal-scroll** on the dashboard at narrow widths.

## The 4 decisions baked in (this is what we're validating)

- **A — Shared sidebar app shell** for the applicant funnel (consistent with staff), with
  a mobile off-canvas drawer. (vs a lighter topbar-only shell)
- **B — Split brand/form auth layout** — emerald brand panel + form. (vs centered card)
- **C — Dashboard status-summary chips** — Submitted / In review / Admitted / Rejected
  above the applications table. (vs hero + table only)
- **D — Application form as a 4-step wizard** — ① Programme ② Personal ③ Documents
  ④ Review. (vs one long single-page form)

## Design tokens (realized here)

| Token | Value |
|---|---|
| Brand / primary | Tailwind **emerald** — `primary-600 #059669` (brand), `700 #047857` (hover), `50 #ecfdf5` / `100 #d1fae5` (tints) |
| Neutrals | **slate** surfaces & text (`slate-50` light canvas, `slate-950` dark canvas, `slate-900` cards in dark) |
| Status badges | mirror `statusDisplay.ts` severities — info→blue (Submitted), warn→amber (Under review), success→emerald (Admitted), danger→red (Rejected), secondary→slate |
| Font | **Inter** (Google Fonts) with `ui-sans-serif, system-ui` fallback |
| Radius | cards/inputs `rounded-lg`/`rounded-xl` (~0.5–0.875rem); pills/avatars fully rounded |
| Elevation | subtle `shadow-sm` on cards; `shadow-lg` on menus/drawer; 1px slate borders everywhere |
| Focus | 2px emerald `:focus-visible` ring (emerald-400 in dark) for keyboard operability |
| Dark mode | Tailwind `class` strategy + persisted toggle; every surface themed |

## Accuracy notes (grounded in the real pages)

Fields/content/flow were taken from the real source — **not invented**:

- **Login** — Username (email / employee ID / matricule) with the ⓘ tooltip, Password
  with show/hide, Remember me, Forgot password, Sign up link. (from `auth/Login.vue`)
- **Register** — Name, Email, Phone (optional), Password, Confirm. (from `auth/Register.vue`)
- **Dashboard table** — Department (name + muted code), Programme, Level, Status badge,
  Submitted, View. Badge severities from `statusDisplay.ts`. (from `dashboards/Applicant.vue`)
- **Application** — Programme (degree programme, department, level + "Allowed: 1–3" hint),
  Personal (first/last name, contact email, phone, DOB, previous institute optional),
  Documents (NID + Birth always required, plus a sample level doc; "PDF / JPG / PNG up to
  8 MB"). (from `applicant/applications/Create.vue`)

## What was simplified or left as a note (intentional, since this is mid-fi)

- **No real validation / backend / routing.** Forms `preventDefault` and navigate via
  `window.location` to emulate the happy path only.
- **Dropdowns / department cascade are static** `<select>`s with sample options — the real
  app loads departments and level-document requirements from `api/v1` JSON endpoints. The
  document list here is a representative sample (NID, Birth, GCE A-Level), not driven by the
  selected level.
- **Date of birth** uses a native `<input type="date">` (the app uses a PrimeVue
  `DatePicker` with the documented local-date / timezone handling — AUD-015).
- **The ⓘ tooltip + dropdowns** emulate the PrimeVue/Aura look with plain Tailwind; the real
  app uses the actual PrimeVue components.
- **Notifications bell** is decorative (shows an unread dot) — no panel.
- **Empty state** is preview-toggled on the dashboard rather than condition-driven.
- **Icons** use the Lucide CDN web build (`data-lucide` + `lucide.createIcons()`); the app
  uses `lucide-vue-next` via the PrimeVue `#icon` slot. Same icon set, different delivery.

## Not for production

Do not import these files into the app or copy the inline `tailwind.config` /
Play-CDN setup. When the look & flow are approved, the design language gets rebuilt
properly in `resources/js/...` per the project's PrimeVue/Aura + Wayfinder conventions
(this is backlog **#22**, the pilot redesign).
