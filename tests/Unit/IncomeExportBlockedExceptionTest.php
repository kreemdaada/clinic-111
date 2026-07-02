<?php

namespace Tests\Unit;

use App\Exceptions\IncomeExportBlockedException;
use Tests\TestCase;

class IncomeExportBlockedExceptionTest extends TestCase
{
    public function test_formats_lab_without_payments_with_user_guidance(): void
    {
        $exception = new IncomeExportBlockedException([
            [
                'severity' => 'error',
                'type' => 'lab_without_payments',
                'doctor' => 'JACK',
                'message' => 'Doctor JACK has lab cost 600.00 AED but zero collected payments.',
            ],
        ]);

        $messages = $exception->userFacingMessages();

        $this->assertGreaterThanOrEqual(2, count($messages));
        $this->assertStringContainsString('could not be created', $messages[0]);
        $this->assertStringContainsString('JACK', implode(' ', $messages));
        $this->assertStringContainsString('External lab cost', implode(' ', $messages));
        $this->assertStringNotContainsString('RuntimeException', implode(' ', $messages));
    }
}
