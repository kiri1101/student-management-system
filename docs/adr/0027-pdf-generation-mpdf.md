# ADR-0027: Transcript PDFs via mpdf; QR via the existing bacon/bacon-qr-code

- **Status:** Accepted
- **Date:** 2026-07-06
- **Deciders:** SchuLyf maintainers

## Context
The transcript feature (#71) is the app's first server-side PDF: an official, multi-page,
QR-bearing document. No PDF pipeline existed. The app is destined for Laravel Cloud, which must stay
**Chromium-free** (no headless-browser process to provision/scale). The candidates were:

- **mpdf** — pure PHP, good multi-page table support (repeating headers, per-page footers), inline
  SVG. Needs `ext-gd` + `ext-mbstring`.
- **dompdf** — pure PHP but weaker multi-page header/footer control.
- **Browsershot / headless Chromium** — best fidelity, but requires a Chromium runtime in production,
  which contradicts the Chromium-free target.

For the QR code, the plan originally named the `simplesoftwareio/simple-qrcode` wrapper.

## Decision
Render transcript PDFs with **`mpdf/mpdf`** (pure PHP) from a Blade template
(`resources/views/pdf/transcript.blade.php`), driven by `TranscriptPdfRenderer`.

Generate the QR **directly with `bacon/bacon-qr-code`**, which is **already installed** (Fortify's 2FA
depends on it, `^3.0`). The renderer uses `Writer` + `ImageRenderer` + `SvgImageBackEnd` +
`RendererStyle(120, 0)` and strips the `<?xml?>` prolog so mpdf can embed the SVG inline.

`simplesoftwareio/simple-qrcode` was **rejected**: even its latest release pins
`bacon/bacon-qr-code ^2.0`, which conflicts with Fortify's `^3.0` — installing it would force a
risky downgrade of a security dependency. Using bacon directly removes a dependency instead of adding
one, and produces the same SVG.

**#71 therefore adds exactly one new composer dependency: `mpdf/mpdf`.**

## Consequences
- One new composer dep (`mpdf/mpdf`); QR reuses the already-present `bacon/bacon-qr-code`.
- mpdf needs `ext-gd` + `ext-mbstring`. Both are present on Laragon locally; CI's two
  `shivammathur/setup-php` steps in `.github/workflows/tests.yml` were updated to
  `extensions: sockets, gd, mbstring`.
- The PDF renders from the stored **snapshot**, never live models, so re-rendering an old transcript
  reproduces its content at issue time (see [ADR-0028](0028-transcript-verification.md)).
- The `%PDF` output is exercised end-to-end by `TranscriptPdfRendererTest` (real mpdf + real QR
  embed), so a dependency or extension regression fails a test rather than a production download.
- If a future reference template proves visually elaborate beyond mpdf's comfort, revisit Browsershot
  — but only if the Chromium-free constraint is relaxed.

## As-built vs. planned
The plan ([plan/transcripts/design.md](../../plan/transcripts/design.md) §13) specified mpdf +
`simplesoftwareio/simple-qrcode`. The QR library was swapped to a direct `bacon/bacon-qr-code`
call during implementation to avoid the Fortify version conflict — a net reduction of one dependency.
Delivered on `feat/student-transcripts` (#71).
