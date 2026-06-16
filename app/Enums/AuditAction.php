<?php

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case StatusChanged = 'status_changed';
    case RoleAssigned = 'role_assigned';
    case RoleRevoked = 'role_revoked';
    case LoggedIn = 'logged_in';
    case LoginFailed = 'login_failed';
    case LoggedOut = 'logged_out';
    case ApplicationDecided = 'application_decided';
    case PaymentValidated = 'payment_validated';
    case PaymentRejected = 'payment_rejected';
    case ReceiptIssued = 'receipt_issued';
    case DeferralApproved = 'deferral_approved';
    case DeferralRejected = 'deferral_rejected';
    case CourseCreated = 'course_created';
    case LecturerAssigned = 'lecturer_assigned';
    case CoursePlanSubmitted = 'course_plan_submitted';
    case CoursePlanApproved = 'course_plan_approved';
    case CoursePlanRejected = 'course_plan_rejected';
    case CourseSessionScheduled = 'course_session_scheduled';
    case CourseSessionCancelled = 'course_session_cancelled';
    case CourseSessionRescheduled = 'course_session_rescheduled';
    case AttendanceMarked = 'attendance_marked';
    case AssignmentCreated = 'assignment_created';
    case AssignmentSubmitted = 'assignment_submitted';
    case AssignmentGraded = 'assignment_graded';
    case ResultRecorded = 'result_recorded';
    case ResultsPublished = 'results_published';
    case DisputeRaised = 'dispute_raised';
    case DisputeResolved = 'dispute_resolved';
}
