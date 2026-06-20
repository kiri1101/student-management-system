# ADR-0013: PrimeVue/Aura for new UI, per-page imports (no global registration)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
The starter kit shipped shadcn-vue primitives and a vue-sonner toaster. New feature work needed a
richer component set (data tables, dropdowns, file uploads, modals, toasts). Globally registering every
PrimeVue component is convenient but defeats tree-shaking and bloats the bundle — flagged as audit
finding **AUD-020**.

## Decision
**PrimeVue with the Aura preset is the default for new UI**, imported **per page** — e.g.
`import Button from 'primevue/button'` in each component that uses it. **No PrimeVue components are
registered globally.** `resources/js/app.ts` registers only the `ToastService` plugin and the `tooltip`
directive app-wide (`theme.preset: Aura`, `darkModeSelector: '.dark'`,
`cssLayer.order: 'theme, base, primevue, utilities'`); `resources/css/app.css` imports
`tailwindcss-primeui` immediately after `tailwindcss`. **`lucide-vue-next` is the single icon library**
(PrimeIcons intentionally not installed). shadcn-vue + vue-sonner **coexist** on existing pages.

## Consequences
- Each page's bundle includes only the components it uses; keep `vite.config.ts` at the default
  `chunkSizeWarningLimit` — a warning there signals a bundle regression, not a limit to raise.
- New components must add their own per-page imports; there is no global registry to lean on.
- Two component systems live side by side; migrate a shadcn-vue page to PrimeVue only when it is being
  substantially modified anyway (tracked as #22).

## As-built vs. planned
The original PrimeVue integration registered components globally. **AUD-020 reversed that** to per-page
imports, which is the shipped `app.ts`. See the project `CLAUDE.md` "UI Components" section.
