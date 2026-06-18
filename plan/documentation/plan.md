# Documentation Initiative — Scope & Build Plan (#68)

Status: **SCOPING LOCKED 2026-06-18.** Step 1 (agree the doc set) complete; this file is the
contract for steps 2 (skills) and 3 (specialized agents → parallel production).

Tracks GitHub issue **#68**. Owner-defined process (do in order, don't skip ahead):

1. ✅ **Agree the documentation types + scope.** (this document)
2. **Build skills** (`.claude/skills/`) that assist the documentation task.
3. **Build specialized agents** — one per doc-type workstream — then fan out to produce docs in
   parallel, per [[feedback-parallelism-subagents]] + the drafter→orchestrator integration recipe
   [[feedback-parallel-subagent-integration]] (agents draft; orchestrator runs the gate + commits).

---

## Decisions locked (2026-06-18)

| Fork | Decision |
|---|---|
| **Format / tooling** | In-repo **Markdown** under a new `/docs` tree (+ root `README.md`). No new deps, renders on GitHub, matches the `plan/` + `AUDIT.md` convention. |
| **Audience** | **Everything** — developer/maintainer docs **and** the 6 per-role end-user guides, this initiative. |
| **ADRs** | **Formal ADR folder** — `docs/adr/NNNN-*.md`, one numbered file per locked decision. |
| **Code docs** | **Include a PHPDoc / inline-comment pass** across models, actions, services (touches `app/`, runs the full gate). |

### Guardrail — document the SHIPPED code, not the plan
`plan/context.md` is a planning log; several §4 decisions were later superseded. Every doc/ADR must
be verified against the current source before it ships. Known drifts to handle explicitly:
- §4.3 "multi-role via `role_user` pivot" → staff are **single-role** in practice
  (`CreateUserAction` / `ChangeUserRoleAction` enforce it; #20 closed not-planned). Applicant+Student
  union still exists. ADR must record both the intent and the as-built reality.
- The §15 audit (AUD-001…034, all Fixed) and §17/§18 changes (invite-link creds, login resolver,
  PrimeVue-no-globals AUD-020, status maps, redesign) override earlier per-phase notes.

---

## Locked documentation set

```
README.md                              # root entry point
docs/
  index.md                             # docs home / table of contents
  architecture.md                      # request lifecycle, stack, patterns
  onboarding.md                        # local setup + the quality gate
  data-model.md                        # 24-model schema reference + mermaid ER diagram
  routes.md                            # endpoint reference across 8 route files + api/v1 + public verify
  security.md                          # auth, gates, audit log, HMAC receipts, file-viewer hardening
  testing.md                           # Pest feature + tests/Browser smoke conventions
  deployment.md                        # ops runbook (Laravel Cloud, queue, mail, Redis/predis)
  modules/
    admissions.md                      # applicant funnel + SAO admission decisions
    payments.md                        # #6 — slip upload, validation, HMAC school receipts
    exam-gating.md                     # #8 — payment standing + tuition deferrals
    course-management.md               # #11 — catalog, attendance, assignments, results, disputes
    notifications.md                   # #12/#18 — Laravel Notifications channel strategy
    admin-user-management.md           # #30 — invite-link users, role change, CSV import, audit modal
  guides/
    applicant.md
    student.md
    sao.md
    accountant.md
    lecturer.md
    admin.md
  adr/
    README.md                          # ADR index + status legend
    0001-*.md … (one per locked decision — see list below)
```

### ADR candidates (≈20, one per locked decision — verify each against code)
1. Custom `Role` model + `role_user` pivot + Gate/Policy (no Spatie permission package)
2. Multi-role intent → **single-role-for-staff** as-built (superseded; document both)
3. Separate per-role profile models (no `applicant_profile`; admin has no profile)
4. String columns + PHP backed-enum casts (no native DB `ENUM`)
5. Soft deletes everywhere **except** `audit_logs`
6. `restrictOnDelete` on all FKs to tables with real data (no cascade)
7. Three-identifier login — email / matricule / employee_id via Fortify resolver
8. Email verification required for all users
9. Single institution per install (no tenancy column)
10. `DegreeProgram` fixed enum + departments × `program_offerings` + per-offering levels + per-level credential requirements
11. `applications` carry their own snapshot of applicant data
12. Immutable, significant-writes-only audit log
13. PrimeVue/Aura for new UI, **per-page imports / no globals** (AUD-020); shadcn-vue coexistence
14. Session-auth web routes + `api/v1` same-origin JSON lookups (no token API)
15. Invite-link credential flow for staff (admin never sets passwords)
16. Money stored as integer XAF
17. HMAC-signed school receipts + public verification endpoint
18. No enrollment table — implicit cohort membership (`program_offering_id` + `level` + `academic_year`)
19. Notification channel strategy — email transactional / in-app broadcast / SMS deferred (#18)
20. Reference-data read-through cache, no tags (#39); re-registration = verify-first via password reset (§13)

*(Final numbering assigned during the ADR phase; some may merge/split after code verification.)*

---

## Step 2 — Skills to build (`.claude/skills/`)

Skills encode shared **house style + methodology**, reused by the agents:

- **`write-reference-doc`** — structure + conventions for the mechanical extraction docs
  (data-model, routes): how to read migrations/`route:list`, the ER-diagram mermaid format, tables.
- **`write-module-doc`** — house structure for a domain/module doc: Purpose → Roles & gates →
  Data model → Routes/endpoints → Actions & flows → Notifications/audit → Tests → File map.
- **`write-user-guide`** — task-oriented, plain-language per-role guide format (what the user sees,
  step-by-step flows, no implementation detail).
- **`write-adr`** — the numbered ADR template (Context / Decision / Status / Consequences /
  As-built-vs-planned) + the verify-against-code rule.
- **`docs-refresh`** *(maintenance — the "stay current" answer)* — after a feature ships, locate the
  affected docs and update them; run as part of the per-feature gate going forward.

## Step 3 — Specialized agents (parallel producers, drafter pattern)

All are **drafters** (Read/Grep/Glob/Write/Edit only — no shell, no commit); the orchestrator runs
the gate + commits. Multiple instances of the per-item writers fan out in parallel.

- **`docs-architecture-writer`** — architecture, onboarding, security, testing, deployment.
- **`docs-reference-writer`** — data-model (+ER diagram), routes. Uses `write-reference-doc`.
- **`docs-module-writer`** — the 6 module docs (6 parallel instances). Uses `write-module-doc`.
- **`docs-user-guide-writer`** — the 6 role guides (6 parallel instances). Uses `write-user-guide`.
- **`adr-writer`** — extract the ~20 ADRs from §4 + later decisions. Uses `write-adr`.
- **PHPDoc pass** — reuse `laravel-backend-drafter` (it edits PHP); orchestrator runs Pint + Pest.

---

## Build phases (per [[feedback-phased-implementation]] — audit + commit each)

| Phase | Deliverable | Notes |
|---|---|---|
| **D0** | `/docs` skeleton + `index.md` + the 5 skills + the doc agents | Foundation; commit. |
| **D1** | Architecture + reference + ops docs (parallel) | architecture, onboarding, data-model+ER, routes, security, testing, deployment. |
| **D2** | 6 module docs (parallel) | One drafter instance per module. |
| **D3** | ADR folder (parallel extraction) | ~20 ADRs + index; verified against code. |
| **D4** | 6 per-role user guides (parallel) | Mostly new authoring. |
| **D5** | PHPDoc / inline-comment pass | Touches `app/`; runs Pint + full Pest gate. |
| **D6** | Root `README.md` + cross-linking + verification | Link from both `CLAUDE.md`s; wire `docs-refresh` into the gate; final pass. |

## Source material to mine (extract + organize, not author-from-scratch)
`plan/context.md` (§4 decisions, §6 schema, §7 routes, §13 reactivation, §14 progress, §15 audit,
§16 deploy, §17 backlog, §18 redesign) · `AUDIT.md` · `plan/payments|exam-gating|course-management/plan.md`
· `plan/frontend-redesign/overview.md` · both `CLAUDE.md` files · the project memories (`MEMORY.md` + `project_*`).
