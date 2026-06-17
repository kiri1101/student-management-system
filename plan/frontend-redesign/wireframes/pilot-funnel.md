# Pilot Wireframes — Auth + Applicant Funnel (v1, for discussion)

Low-fidelity **structure only** — no color/branding yet (that's the mockup phase).
Goal: agree the layout, hierarchy, and states before any styling. Each screen lists
the recommendation + the open decision to settle.

Legend: `◆` logo · `▾` dropdown · `ⓘ` info tooltip · `→` link/next · `[ ]` button/input

---

## 0. App shell (authenticated) — frames every signed-in page

```
┌──────────────────────────┬───────────────────────────────────────────────┐
│ ◆ SchuLyf                │ [≡]  Dashboard › New application      🔔 ◑ ▾  │  ← topbar
│                          ├───────────────────────────────────────────────┤
│  ▸ Dashboard             │                                               │
│  ▸ New application       │     ┌─────────────────────────────────────┐   │
│                          │     │  PAGE CONTENT                       │   │
│  (role-aware nav —       │     │  centered max-w container,          │   │
│   lean for applicants,   │     │  generous padding                   │   │
│   rich for staff)        │     │                                     │   │
│                          │     └─────────────────────────────────────┘   │
│                          │                                               │
├──────────────────────────┤                                               │
│ [av] Jane Doe          ▾ │                                               │
│  Account · Sign out      │                                               │
└──────────────────────────┴───────────────────────────────────────────────┘
```

- Brand lockup top-left; **role-aware nav** (the existing `AppSidebarNav` logic, restyled);
  user menu pinned bottom.
- Topbar: collapse toggle + breadcrumb (left), theme toggle + notifications bell (future) +
  avatar menu (right).
- **Mobile:** sidebar → off-canvas drawer behind the `[≡]`; content full-width.
- **Decision A:** keep this sidebar shell for applicants (consistent with staff, but sparse
  for a 2-item nav) — *or* give the applicant funnel a lighter **topbar-only** shell and
  reserve the sidebar for staff. Recommend: **keep one sidebar shell** (consistency + it's
  what staff need); the lean applicant nav is fine.

---

## 1. Login — split brand/form layout (replaces centered card)

```
┌───────────────────────────────┬───────────────────────────────┐
│  BRAND PANEL                  │   Welcome back                │
│                               │   Sign in to your account     │
│   ◆ SchuLyf                   │                               │
│                               │   Username  ⓘ (email/ID/matr.)│
│   Your campus life,           │   [___________________________]│
│   organized.                  │   Password                    │
│                               │   [_______________________] 👁 │
│   ✓ Apply & track admission   │   [✓] Remember me   Forgot? → │
│   ✓ Pay & verify receipts     │                               │
│   ✓ Results, attendance, more │   [          Log in          ]│
│                               │                               │
│   (pattern / illustration)    │   No account?  Sign up →      │
└───────────────────────────────┴───────────────────────────────┘
```

- Left = emerald brand panel (logo + one-line value props); right = the form.
- Status/flash banner sits above the form when present.
- Same field set as today (Username w/ tooltip, Password, Remember, Forgot, Sign up).
- **Mobile:** brand panel collapses to a slim branded header strip; form stacks full-width.
- **Decision B:** **split layout** (recommended — great canvas for SchuLyf) vs the current
  centered card.

---

## 2. Register — same split shell

```
│  BRAND PANEL                  │   Create your account         │
│   ◆ SchuLyf                   │   Enter your details below    │
│   (reuse value props)         │                               │
│                               │   Name      [_______________] │
│                               │   Email     [_______________] │
│                               │   Phone (optional) [_________]│
│                               │   Password  [_____________] 👁 │
│                               │   Confirm   [_____________] 👁 │
│                               │   [        Create account     ]│
│                               │   Have an account?  Log in →  │
```

- Mirrors Login's shell for continuity. Same fields as today.

---

## 3. Applicant dashboard — hero + summary + list

```
┌─────────────────────────────────────────────────────────────────┐
│  Welcome back, Jane 👋                        [ + New application ]│  ← hero
│  Track your admission applications below.                        │
├─────────────────────────────────────────────────────────────────┤
│  ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐     │
│  │ Submitted 2│ │ In review 1│ │ Admitted  0│ │ Rejected  0│     │  ← status summary (optional)
│  └────────────┘ └────────────┘ └────────────┘ └────────────┘     │
├─────────────────────────────────────────────────────────────────┤
│  My applications                                                │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ Department     Programme    Level  Status      Submitted     ││
│  │ Computer Sci.  Bachelors    1      ◗ Submitted  12 Jun  · View →│
│  │ …                                                           ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                 │
│  EMPTY STATE: illustration + "Start your first application" CTA  │
└─────────────────────────────────────────────────────────────────┘
```

- Adds a **hero header** (greeting + primary CTA) and an **at-a-glance status summary row**
  above the existing applications table; keeps all current columns + the View action + empty state.
- **Decision C:** include the status-summary chips (recommended — cheap, adds orientation)
  vs keep it minimal (hero + table only).

---

## 4. Application form — guided stepper (replaces one long page)

```
┌─────────────────────────────────────────────────────────────────┐
│  New application                                                │
│  ① Programme ──── ② Personal ──── ③ Documents ──── ④ Review     │  ← step indicator
├─────────────────────────────────────────────────────────────────┤
│  STEP 1 · Programme                                             │
│   Degree programme [▾]    Department [▾]    Level [ − 1 + ]      │
│   (Allowed: 1–3)                                                │
│                                              [ Continue → ]      │
└─────────────────────────────────────────────────────────────────┘

② Personal   → first/last name · contact email · phone · DOB · prev. institute   [← Back][Continue →]
③ Documents  → dynamic upload cards: NID, BIRTH (always) + level-specific docs    [← Back][Continue →]
④ Review     → read-only summary of every field + uploaded files, then           [← Back][ Submit ✓ ]
```

- Converts today's single long form into a **4-step wizard**. The cascade already gates
  progression (you can't pick a level before a programme), so the steps map naturally;
  the **Review** step cuts submission errors. All current fields, validation, dynamic
  uploads, error/info messages, and the DOB-timezone handling are preserved — only the
  presentation changes.
- **Mobile:** step indicator becomes a compact "Step 1 of 4" + progress bar.
- **Decision D:** **stepper/wizard** (recommended — friendlier for first-time applicants)
  vs keep the **single-page** form (faster for repeat/power users). This is the biggest
  UX call in the pilot.

---

## Open decisions to settle (then I revise to v2)

| # | Decision | My recommendation |
|---|---|---|
| A | Applicant shell: shared sidebar vs lighter topbar | Shared sidebar (consistency) |
| B | Auth layout: split brand/form vs centered card | Split (brand canvas) |
| C | Dashboard: status-summary chips vs minimal | Include chips |
| D | Application form: stepper vs single-page | Stepper |
