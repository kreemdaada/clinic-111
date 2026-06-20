<?php

namespace App\Enums;

enum LabJobStatus: string
{
    case Calculated = 'calculated';
    case Adjusted = 'adjusted';
    case Cancelled = 'cancelled';
}
