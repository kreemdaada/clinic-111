<?php

namespace Tests\Unit;

use App\Support\ImportSkipReasonLabel;
use Tests\TestCase;

class ImportSkipReasonLabelTest extends TestCase
{
    public function test_known_skip_reasons_have_english_labels(): void
    {
        $this->assertSame('Cash summary row', ImportSkipReasonLabel::for('cash_row'));
        $this->assertSame('Stale section (previous month)', ImportSkipReasonLabel::for('stale_section'));
        $this->assertSame('Not a daily subtotal row', ImportSkipReasonLabel::for('not_a_daily_subtotal'));
    }

    public function test_unknown_code_is_returned_as_is(): void
    {
        $this->assertSame('custom_reason', ImportSkipReasonLabel::for('custom_reason'));
    }
}
