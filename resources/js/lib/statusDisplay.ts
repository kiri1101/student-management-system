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
    submitted: { label: 'Submitted', severity: 'info' },
    under_review: { label: 'Under review', severity: 'warn' },
    documents_requested: { label: 'Documents requested', severity: 'warn' },
    admitted: { label: 'Admitted', severity: 'success' },
    rejected: { label: 'Rejected', severity: 'danger' },
    waitlisted: { label: 'Waitlisted', severity: 'secondary' },
    withdrawn: { label: 'Withdrawn', severity: 'secondary' },
};

/** `App\Enums\ApplicationDocumentStatus` */
const APPLICATION_DOCUMENT_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    pending: { label: 'Awaiting review', severity: 'warn' },
    accepted: { label: 'Accepted', severity: 'success' },
    rejected: { label: 'Rejected', severity: 'danger' },
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

/** `App\Enums\DeferralStatus` */
const DEFERRAL_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    requested: { label: 'Requested', severity: 'info' },
    approved: { label: 'Approved', severity: 'success' },
    rejected: { label: 'Rejected', severity: 'danger' },
};

/** `App\Enums\PaymentStanding` — computed access-gating verdict */
const PAYMENT_STANDING: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    cleared: { label: 'Cleared', severity: 'success' },
    blocked: { label: 'Blocked', severity: 'danger' },
    deferred: { label: 'Deferred', severity: 'warn' },
};

/** `App\Enums\CoursePlanStatus` */
const COURSE_PLAN_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    draft: { label: 'Draft', severity: 'secondary' },
    submitted: { label: 'Submitted', severity: 'info' },
    approved: { label: 'Approved', severity: 'success' },
    rejected: { label: 'Rejected', severity: 'danger' },
};

/** `App\Enums\SessionStatus` */
const SESSION_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    scheduled: { label: 'Scheduled', severity: 'info' },
    held: { label: 'Held', severity: 'success' },
    cancelled: { label: 'Cancelled', severity: 'danger' },
};

/** `App\Enums\AttendanceStatus` */
const ATTENDANCE_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    present: { label: 'Present', severity: 'success' },
    absent: { label: 'Absent', severity: 'danger' },
    late: { label: 'Late', severity: 'warn' },
    excused: { label: 'Excused', severity: 'secondary' },
};

/** `App\Enums\AssignmentSubmissionStatus` */
const ASSIGNMENT_SUBMISSION_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    submitted: { label: 'Submitted', severity: 'info' },
    graded: { label: 'Graded', severity: 'success' },
};

/** `App\Enums\ResultStatus` */
const RESULT_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    draft: { label: 'Draft', severity: 'secondary' },
    published: { label: 'Published', severity: 'success' },
};

/** `App\Enums\DisputeStatus` */
const DISPUTE_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    open: { label: 'Open', severity: 'info' },
    under_review: { label: 'Under review', severity: 'warn' },
    resolved: { label: 'Resolved', severity: 'success' },
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

export function applicationDocumentStatusLabel(status: string): string {
    return APPLICATION_DOCUMENT_STATUS[status]?.label ?? status;
}

export function applicationDocumentStatusSeverity(status: string): TagSeverity {
    return APPLICATION_DOCUMENT_STATUS[status]?.severity ?? 'secondary';
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

export function deferralStatusLabel(status: string): string {
    return DEFERRAL_STATUS[status]?.label ?? status;
}

export function deferralStatusSeverity(status: string): TagSeverity {
    return DEFERRAL_STATUS[status]?.severity ?? 'secondary';
}

export function standingLabel(status: string): string {
    return PAYMENT_STANDING[status]?.label ?? status;
}

export function standingSeverity(status: string): TagSeverity {
    return PAYMENT_STANDING[status]?.severity ?? 'secondary';
}

export function coursePlanStatusLabel(status: string): string {
    return COURSE_PLAN_STATUS[status]?.label ?? status;
}

export function coursePlanStatusSeverity(status: string): TagSeverity {
    return COURSE_PLAN_STATUS[status]?.severity ?? 'secondary';
}

export function sessionStatusLabel(status: string): string {
    return SESSION_STATUS[status]?.label ?? status;
}

export function sessionStatusSeverity(status: string): TagSeverity {
    return SESSION_STATUS[status]?.severity ?? 'secondary';
}

export function attendanceStatusLabel(status: string | null): string {
    return status ? (ATTENDANCE_STATUS[status]?.label ?? status) : '—';
}

export function attendanceStatusSeverity(
    status: string | null,
): TagSeverity {
    return status ? (ATTENDANCE_STATUS[status]?.severity ?? 'secondary') : 'secondary';
}

export function assignmentSubmissionStatusLabel(status: string): string {
    return ASSIGNMENT_SUBMISSION_STATUS[status]?.label ?? status;
}

export function assignmentSubmissionStatusSeverity(
    status: string,
): TagSeverity {
    return ASSIGNMENT_SUBMISSION_STATUS[status]?.severity ?? 'secondary';
}

export function resultStatusLabel(status: string): string {
    return RESULT_STATUS[status]?.label ?? status;
}

export function resultStatusSeverity(status: string): TagSeverity {
    return RESULT_STATUS[status]?.severity ?? 'secondary';
}

export function disputeStatusLabel(status: string): string {
    return DISPUTE_STATUS[status]?.label ?? status;
}

export function disputeStatusSeverity(status: string): TagSeverity {
    return DISPUTE_STATUS[status]?.severity ?? 'secondary';
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
