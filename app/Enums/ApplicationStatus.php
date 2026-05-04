<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case DocumentsRequested = 'documents_requested';
    case Admitted = 'admitted';
    case Rejected = 'rejected';
    case Waitlisted = 'waitlisted';
    case Withdrawn = 'withdrawn';
}
