/**
 * Single source of truth for the label + Tag severity of every backend enum
 * rendered in the UI (AUDIT.md AUD-027). Values mirror the PHP enums in
 * `app/Enums`; add new cases here, not in page-local maps.
 */

export type TagSeverity =
    | 'success'
    | 'info'
    | 'warn'
    | 'danger'
    | 'secondary'
    | 'contrast';

/** `App\Enums\ApplicationStatus` */
const APPLICATION_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    draft: { label: 'Draft', severity: 'secondary' },
    submitted: { label: 'Submitted', severity: 'info' },
    under_review: { label: 'Under review', severity: 'warn' },
    documents_requested: { label: 'Documents requested', severity: 'warn' },
    admitted: { label: 'Admitted', severity: 'success' },
    rejected: { label: 'Rejected', severity: 'danger' },
    waitlisted: { label: 'Waitlisted', severity: 'secondary' },
    withdrawn: { label: 'Withdrawn', severity: 'secondary' },
};

/** `App\Enums\StudentStatus` */
const ENROLLMENT_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    active: { label: 'Active', severity: 'success' },
    suspended: { label: 'Suspended', severity: 'warn' },
    graduated: { label: 'Graduated', severity: 'info' },
    withdrawn: { label: 'Withdrawn', severity: 'danger' },
};

/** `App\Enums\PaymentStatus` */
const PAYMENT_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    submitted: { label: 'Submitted', severity: 'info' },
    validated: { label: 'Validated', severity: 'success' },
    rejected: { label: 'Rejected', severity: 'danger' },
};

/** `App\Enums\DegreeProgram` */
const DEGREE_LABELS: Record<string, string> = {
    hnd: 'HND',
    bachelors: 'Bachelors',
    masters: 'Masters',
};

/** `App\Enums\RoleName` — labels mirror `RoleName::label()` */
const ROLE_DISPLAY: Record<string, { label: string; severity: TagSeverity }> = {
    admin: { label: 'Administrator', severity: 'danger' },
    sao: { label: 'SAO', severity: 'info' },
    lecturer: { label: 'Lecturer', severity: 'success' },
    accountant: { label: 'Accountant', severity: 'warn' },
    student: { label: 'Student', severity: 'secondary' },
    applicant: { label: 'Applicant', severity: 'secondary' },
};

export function statusLabel(status: string): string {
    return APPLICATION_STATUS[status]?.label ?? status;
}

export function statusSeverity(status: string): TagSeverity {
    return APPLICATION_STATUS[status]?.severity ?? 'secondary';
}

export function enrollmentLabel(status: string): string {
    return ENROLLMENT_STATUS[status]?.label ?? status;
}

export function enrollmentSeverity(status: string): TagSeverity {
    return ENROLLMENT_STATUS[status]?.severity ?? 'secondary';
}

export function paymentStatusLabel(status: string): string {
    return PAYMENT_STATUS[status]?.label ?? status;
}

export function paymentStatusSeverity(status: string): TagSeverity {
    return PAYMENT_STATUS[status]?.severity ?? 'secondary';
}

export function degreeLabel(value: string | null | undefined): string {
    return value ? (DEGREE_LABELS[value] ?? value) : '—';
}

export function roleLabel(role: string): string {
    return ROLE_DISPLAY[role]?.label ?? role;
}

export function roleSeverity(role: string): TagSeverity {
    return ROLE_DISPLAY[role]?.severity ?? 'secondary';
}
