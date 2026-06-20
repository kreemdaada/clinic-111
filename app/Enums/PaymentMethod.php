<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Dhs = 'dhs';
    case Usd = 'usd';
    case Visa = 'visa';
}
