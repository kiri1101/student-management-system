/*
 * SchuLyf prototype — shared vanilla JS.
 * Theme toggle (persisted), mobile sidebar drawer, password show/hide,
 * dashboard empty-state preview toggle, and the 4-step application wizard.
 * No framework — runs over file:// by double-clicking the HTML files.
 */

/* ------------------------------------------------------------------ *
 * Theme (dark mode) — class strategy, persisted in localStorage.
 * Applied as early as possible to avoid a flash of the wrong theme.
 * ------------------------------------------------------------------ */
(function applyStoredTheme() {
    try {
        const stored = localStorage.getItem('schulyf-theme');
        const prefersDark =
            window.matchMedia &&
            window.matchMedia('(prefers-color-scheme: dark)').matches;
        const dark = stored ? stored === 'dark' : prefersDark;
        document.documentElement.classList.toggle('dark', dark);
    } catch (_) {
        /* localStorage may be unavailable on some file:// setups — ignore. */
    }
})();

function setTheme(dark) {
    document.documentElement.classList.toggle('dark', dark);
    try {
        localStorage.setItem('schulyf-theme', dark ? 'dark' : 'light');
    } catch (_) {
        /* ignore */
    }
    syncThemeToggles();
}

function syncThemeToggles() {
    const dark = document.documentElement.classList.contains('dark');
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
        btn.setAttribute('aria-pressed', String(dark));
        const sun = btn.querySelector('[data-icon-sun]');
        const moon = btn.querySelector('[data-icon-moon]');
        if (sun) sun.hidden = dark;
        if (moon) moon.hidden = !dark;
    });
}

/* ------------------------------------------------------------------ *
 * Mobile sidebar drawer (off-canvas + scrim).
 * ------------------------------------------------------------------ */
function openDrawer() {
    const drawer = document.querySelector('[data-drawer]');
    const scrim = document.querySelector('[data-scrim]');
    if (!drawer) return;
    drawer.classList.remove('-translate-x-full');
    if (scrim) {
        scrim.classList.remove('pointer-events-none', 'opacity-0');
    }
    drawer.setAttribute('aria-hidden', 'false');
    const firstLink = drawer.querySelector('a, button');
    if (firstLink) firstLink.focus();
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    const drawer = document.querySelector('[data-drawer]');
    const scrim = document.querySelector('[data-scrim]');
    if (!drawer) return;
    drawer.classList.add('-translate-x-full');
    if (scrim) {
        scrim.classList.add('pointer-events-none', 'opacity-0');
    }
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    const toggle = document.querySelector('[data-drawer-toggle]');
    if (toggle) toggle.focus();
}

/* ------------------------------------------------------------------ *
 * Dropdown menus (user menu / avatar menu) — click + outside-close.
 * ------------------------------------------------------------------ */
function wireMenus() {
    document.querySelectorAll('[data-menu]').forEach((menu) => {
        const trigger = menu.querySelector('[data-menu-trigger]');
        const panel = menu.querySelector('[data-menu-panel]');
        if (!trigger || !panel) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = !panel.hidden;
            // close any other open menus first
            document
                .querySelectorAll('[data-menu-panel]')
                .forEach((p) => (p.hidden = true));
            panel.hidden = open;
            trigger.setAttribute('aria-expanded', String(!open));
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('[data-menu-panel]').forEach((p) => {
            p.hidden = true;
            const t = p
                .closest('[data-menu]')
                ?.querySelector('[data-menu-trigger]');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document
                .querySelectorAll('[data-menu-panel]')
                .forEach((p) => (p.hidden = true));
            const drawer = document.querySelector('[data-drawer]');
            if (drawer && drawer.getAttribute('aria-hidden') === 'false') {
                closeDrawer();
            }
        }
    });
}

/* ------------------------------------------------------------------ *
 * Password show/hide toggles.
 * ------------------------------------------------------------------ */
function wirePasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(
                btn.getAttribute('data-password-toggle'),
            );
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', String(show));
            btn.setAttribute(
                'aria-label',
                show ? 'Hide password' : 'Show password',
            );
            const eye = btn.querySelector('[data-icon-eye]');
            const eyeOff = btn.querySelector('[data-icon-eye-off]');
            if (eye) eye.hidden = show;
            if (eyeOff) eyeOff.hidden = !show;
        });
    });
}

/* ------------------------------------------------------------------ *
 * Dashboard: toggle the empty-state preview on/off.
 * ------------------------------------------------------------------ */
function wireEmptyStateToggle() {
    const btn = document.querySelector('[data-empty-toggle]');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const populated = document.querySelector('[data-state-populated]');
        const empty = document.querySelector('[data-state-empty]');
        if (!populated || !empty) return;
        const showEmpty = empty.hidden;
        empty.hidden = !showEmpty;
        populated.hidden = showEmpty;
        btn.textContent = showEmpty
            ? 'Preview: populated state'
            : 'Preview: empty state';
    });
}

/* ------------------------------------------------------------------ *
 * Application wizard — 4 steps, Back/Continue, live review mirror.
 * ------------------------------------------------------------------ */
function wireWizard() {
    const wizard = document.querySelector('[data-wizard]');
    if (!wizard) return;

    const steps = Array.from(wizard.querySelectorAll('[data-step]'));
    const indicators = Array.from(
        wizard.querySelectorAll('[data-step-indicator]'),
    );
    const lines = Array.from(wizard.querySelectorAll('[data-step-line]'));
    const progressBar = wizard.querySelector('[data-progress-bar]');
    const progressLabel = wizard.querySelector('[data-progress-label]');
    let current = 0;

    function render() {
        steps.forEach((s, i) => (s.hidden = i !== current));

        indicators.forEach((ind, i) => {
            const circle = ind.querySelector('[data-step-circle]');
            const label = ind.querySelector('[data-step-label]');
            const done = i < current;
            const active = i === current;

            circle.classList.remove(
                'bg-primary-600',
                'text-white',
                'border-primary-600',
                'bg-white',
                'dark:bg-slate-900',
                'text-slate-400',
                'border-slate-300',
                'dark:border-slate-700',
                'dark:text-slate-500',
                'bg-primary-100',
                'dark:bg-primary-900/40',
                'text-primary-700',
                'dark:text-primary-300',
                'border-primary-300',
            );

            const check = circle.querySelector('[data-step-check]');
            const num = circle.querySelector('[data-step-num]');

            if (done) {
                circle.classList.add(
                    'bg-primary-600',
                    'text-white',
                    'border-primary-600',
                );
                if (check) check.hidden = false;
                if (num) num.hidden = true;
            } else if (active) {
                circle.classList.add(
                    'bg-primary-100',
                    'dark:bg-primary-900/40',
                    'text-primary-700',
                    'dark:text-primary-300',
                    'border-primary-600',
                );
                if (check) check.hidden = true;
                if (num) num.hidden = false;
            } else {
                circle.classList.add(
                    'bg-white',
                    'dark:bg-slate-900',
                    'text-slate-400',
                    'dark:text-slate-500',
                    'border-slate-300',
                    'dark:border-slate-700',
                );
                if (check) check.hidden = true;
                if (num) num.hidden = false;
            }

            if (label) {
                label.classList.toggle('text-slate-900', active || done);
                label.classList.toggle('dark:text-white', active || done);
                label.classList.toggle('font-semibold', active);
                label.classList.toggle('text-slate-500', !active && !done);
                label.classList.toggle('dark:text-slate-400', !active && !done);
            }
            ind.setAttribute('aria-current', active ? 'step' : 'false');
        });

        lines.forEach((line, i) => {
            const filled = i < current;
            line.classList.toggle('bg-primary-600', filled);
            line.classList.toggle('bg-slate-200', !filled);
            line.classList.toggle('dark:bg-slate-700', !filled);
        });

        if (progressBar) {
            progressBar.style.width = `${((current + 1) / steps.length) * 100}%`;
        }
        if (progressLabel) {
            progressLabel.textContent = `Step ${current + 1} of ${steps.length}`;
        }

        if (current === steps.length - 1) {
            updateReview();
        }

        // Focus the step heading for screen-reader context.
        const heading = steps[current].querySelector('[data-step-heading]');
        if (heading) heading.focus();
        wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function go(delta) {
        const next = current + delta;
        if (next < 0 || next > steps.length - 1) return;
        current = next;
        render();
    }

    wizard
        .querySelectorAll('[data-next]')
        .forEach((b) => b.addEventListener('click', () => go(1)));
    wizard
        .querySelectorAll('[data-back]')
        .forEach((b) => b.addEventListener('click', () => go(-1)));

    // Level number-stepper (− / +) within the bounds hint.
    const levelInput = wizard.querySelector('[data-level-input]');
    if (levelInput) {
        const min = Number(levelInput.getAttribute('min') || 1);
        const max = Number(levelInput.getAttribute('max') || 3);
        wizard.querySelector('[data-level-dec]')?.addEventListener('click', () => {
            levelInput.value = String(
                Math.max(min, Number(levelInput.value || min) - 1),
            );
        });
        wizard.querySelector('[data-level-inc]')?.addEventListener('click', () => {
            levelInput.value = String(
                Math.min(max, Number(levelInput.value || min) + 1),
            );
        });
    }

    // File "choose" controls — reflect the selected filename in the card + review.
    wizard.querySelectorAll('[data-file]').forEach((input) => {
        input.addEventListener('change', () => {
            const card = input.closest('[data-doc-card]');
            const name = input.files && input.files[0] && input.files[0].name;
            const nameEl = card?.querySelector('[data-file-name]');
            const emptyEl = card?.querySelector('[data-file-empty]');
            if (name && nameEl) {
                nameEl.textContent = name;
                nameEl.hidden = false;
                if (emptyEl) emptyEl.hidden = true;
                card.classList.add(
                    'border-primary-300',
                    'dark:border-primary-700',
                );
            }
        });
    });

    function val(name) {
        const el = wizard.querySelector(`[name="${name}"]`);
        if (!el) return '';
        if (el.tagName === 'SELECT') {
            return el.options[el.selectedIndex]?.text || '';
        }
        return el.value || '';
    }

    function updateReview() {
        const map = {
            'review-degree': val('degree_program'),
            'review-department': val('department'),
            'review-level': val('level'),
            'review-first': val('first_name'),
            'review-last': val('last_name'),
            'review-email': val('contact_email'),
            'review-phone': val('phone'),
            'review-dob': val('date_of_birth'),
            'review-prev': val('previous_institute'),
        };
        Object.entries(map).forEach(([id, value]) => {
            const el = wizard.querySelector(`[data-review="${id}"]`);
            if (el) el.textContent = value && value.trim() ? value : '—';
        });

        const docList = wizard.querySelector('[data-review-docs]');
        if (docList) {
            docList.innerHTML = '';
            wizard.querySelectorAll('[data-doc-card]').forEach((card) => {
                const docName =
                    card.getAttribute('data-doc-name') || 'Document';
                const input = card.querySelector('[data-file]');
                const file =
                    input && input.files && input.files[0]
                        ? input.files[0].name
                        : 'Not uploaded';
                const uploaded = Boolean(
                    input && input.files && input.files[0],
                );
                const li = document.createElement('li');
                li.className =
                    'flex items-center justify-between gap-3 py-2 border-b border-slate-100 dark:border-slate-800 last:border-0';
                // Built with textContent (not innerHTML) so the user-supplied
                // file name can never be interpreted as markup.
                const nameSpan = document.createElement('span');
                nameSpan.className =
                    'text-sm font-medium text-slate-700 dark:text-slate-200';
                nameSpan.textContent = docName;
                const fileSpan = document.createElement('span');
                fileSpan.className = uploaded
                    ? 'text-xs text-primary-700 dark:text-primary-300 font-medium'
                    : 'text-xs text-slate-400 dark:text-slate-500 italic';
                fileSpan.textContent = file;
                li.append(nameSpan, fileSpan);
                docList.appendChild(li);
            });
        }
    }

    render();
}

/* ------------------------------------------------------------------ *
 * Bootstrap.
 * ------------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
        btn.addEventListener('click', () =>
            setTheme(!document.documentElement.classList.contains('dark')),
        );
    });
    syncThemeToggles();

    document
        .querySelector('[data-drawer-toggle]')
        ?.addEventListener('click', openDrawer);
    document
        .querySelectorAll('[data-drawer-close]')
        .forEach((el) => el.addEventListener('click', closeDrawer));

    wireMenus();
    wirePasswordToggles();
    wireEmptyStateToggle();
    wireWizard();
});
