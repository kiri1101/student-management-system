<?php

namespace App\Enums;

enum RoleName: string
{
    case Applicant = 'applicant';
    case Student = 'student';
    case Lecturer = 'lecturer';
    case Accountant = 'accountant';
    case Sao = 'sao';
    case Admin = 'admin';
}
