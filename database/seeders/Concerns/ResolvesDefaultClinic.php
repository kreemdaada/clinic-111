<?php

namespace Database\Seeders\Concerns;

use App\Models\Clinic;

trait ResolvesDefaultClinic
{
    protected function defaultClinic(): Clinic
    {
        return Clinic::query()->where('code', 'CLINIC_111')->firstOrFail();
    }
}
