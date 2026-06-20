<?php

namespace App\Enums;

enum CommissionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
