// Capture the Chapter-3 application screenshots for the academic report.
//
//   node plan/report/capture_screenshots.mjs
//
// Requires: the app reachable at BASE (Herd, self-signed cert) serving built
// assets (no public/hot), and the demo data from seed_demo.php. Logs in per
// role in an isolated context and writes PNGs to plan/report/screenshots/.

import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdirSync } from 'node:fs';

const BASE = 'https://student-management-system.test';
const __dirname = dirname(fileURLToPath(import.meta.url));
const OUT = join(__dirname, 'screenshots');
mkdirSync(OUT, { recursive: true });

const VIEWPORT = { width: 1440, height: 1024 };
const SCALE = 2; // crisp text in the printed report

const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });

/** A fresh, logged-in page for one role (or an anonymous page when creds omitted). */
async function newPage(creds) {
    const context = await browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: VIEWPORT,
        deviceScaleFactor: SCALE,
    });
    const page = await context.newPage();
    if (creds) {
        await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
        await page.fill('#email', creds.email);
        await page.fill('#password', creds.password);
        await Promise.all([
            page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30000 }),
            page.click('[data-test="login-button"]'),
        ]);
        await page.waitForLoadState('networkidle');
    }
    return page;
}

async function shot(page, name) {
    await page.waitForTimeout(900); // let fonts/animations settle
    await page.screenshot({ path: join(OUT, name) });
    console.log('captured', name);
}

const creds = {
    applicant: { email: 'applicant@example.com', password: 'password' },
    sao: { email: 'sao@example.com', password: 'password' },
    accountant: { email: 'accountant@example.com', password: 'password' },
    student: { email: 'student@example.com', password: 'password' },
    admin: { email: 'admin@example.com', password: 'password' },
};

try {
    // 1. Login (anonymous)
    {
        const p = await newPage(null);
        await p.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
        await p.waitForSelector('#email');
        await shot(p, '01-login.png');
        await p.context().close();
    }

    // 2. Applicant — application form
    {
        const p = await newPage(creds.applicant);
        await p.goto(`${BASE}/application/new`, { waitUntil: 'networkidle' });
        await shot(p, '02-applicant-application-form.png');
        await p.context().close();
    }

    // 3. SAO — review queue
    {
        const p = await newPage(creds.sao);
        await p.goto(`${BASE}/sao/applications`, { waitUntil: 'networkidle' });
        await p.waitForSelector('table, .p-datatable', { timeout: 15000 }).catch(() => {});
        await shot(p, '03-sao-review-queue.png');
        await p.context().close();
    }

    // 4. Accountant — payment review with the inline slip viewer open
    //    (payment #2 is the pending submission). Clicking "View slip" opens the
    //    in-app FileViewerDialog that renders the bank slip inline.
    {
        const p = await newPage(creds.accountant);
        await p.goto(`${BASE}/accountant/payments/2`, { waitUntil: 'networkidle' });
        await p.click('button:has-text("View slip")');
        await p.waitForSelector('.p-dialog', { timeout: 15000 });
        await p.waitForTimeout(1800); // slip image streams into the dialog
        await shot(p, '04-accountant-payment-review.png');
        await p.context().close();
    }

    // 5. Student — payments screen
    {
        const p = await newPage(creds.student);
        await p.goto(`${BASE}/student/payments`, { waitUntil: 'networkidle' });
        await shot(p, '05-student-payments.png');
        // 6. Student — printable receipt (validated payment #1)
        await p.goto(`${BASE}/student/payments/1/receipt`, { waitUntil: 'networkidle' });
        await p.waitForTimeout(1200); // QR code render
        await shot(p, '06-student-receipt.png');
        await p.context().close();
    }

    // 7. Public receipt verification (anonymous)
    {
        const p = await newPage(null);
        await p.goto(`${BASE}/receipts/verify/RCP-2026-00001`, { waitUntil: 'networkidle' });
        await shot(p, '07-receipt-verify.png');
        await p.context().close();
    }

    // 8. Admin — dashboard, then 9. audit-log modal
    {
        const p = await newPage(creds.admin);
        await p.goto(`${BASE}/admin/dashboard`, { waitUntil: 'networkidle' });
        await shot(p, '08-admin-dashboard.png');
        await p.click('button:has-text("Open audit log")');
        await p.waitForSelector('.p-dialog', { timeout: 15000 });
        await p.waitForTimeout(1500); // audit-logs fetch populates the table
        await shot(p, '09-admin-audit-log.png');
        await p.context().close();
    }

    console.log('ALL DONE');
} catch (err) {
    console.error('CAPTURE FAILED:', err);
    process.exitCode = 1;
} finally {
    await browser.close();
}
