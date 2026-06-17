# Frontend Redesign — SchuLyf

Reshaping the app's look and feel away from the Laravel starter-kit default into an
intentional, branded design system. Run by the `senior-frontend-engineer` specialist
through **wireframes → mockups → integration**, pilot-first.

## Brand

- **Name:** SchuLyf
- **Primary palette:** Tailwind **emerald** scale (brand/primary). Neutral greys for
  surfaces/text; semantic colors (info/success/warn/danger) keep PrimeVue Aura defaults
  unless they clash with emerald.
- **Voice:** clear, supportive, student-first. Tagline candidate: *"Your campus life, organized."*

## Scope & sequencing (pilot-first)

**Pilot slice — the auth + applicant funnel** (the "shop window" outsiders see):

1. **App shell** (authenticated layout) — foundational; frames every signed-in page.
2. **Login** (currently shadcn — migrate to the system, #22).
3. **Register** (currently shadcn — migrate, #22).
4. **Applicant dashboard** (`dashboards/Applicant.vue`).
5. **Application form** (`applicant/applications/Create.vue`).

Once the design language is locked on the pilot, roll it out to the staff surfaces
(SAO / admin / accountant / lecturer / student dashboards + feature pages).

## Phases & deliverables

| Phase | Deliverable | Location |
|---|---|---|
| Wireframes | Low-fi ASCII structure sketches (no color) | `plan/frontend-redesign/wireframes/` |
| Mockups | Styled Vue components in-browser + design-system reference (emerald realized) | built pages + a `/design-system` reference |
| Integration | Wired to real props, Wayfinder routes, forms | the real pages |

## Hard conventions (must hold)

PrimeVue + Aura, per-page imports (no globals, AUD-020), `lucide-vue-next` via `#icon`
slot, Wayfinder route helpers (no hardcoded URLs), `statusDisplay.ts` for label/severity
maps, lazy-loaded auth/settings layouts, async `AppSidebarNav.vue` route barrels, default
Vite `chunkSizeWarningLimit`, single root element per component, WCAG AA + dark mode on
every surface. See `~/.claude/agents/senior-frontend-engineer.md` for the full list.
