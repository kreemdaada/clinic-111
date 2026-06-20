<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Viewer = 'viewer';
}
