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
}
