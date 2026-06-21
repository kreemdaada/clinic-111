<?php

namespace Tests\Unit;

use App\Support\IncomeSheetColumnMap;
use Tests\TestCase;

class IncomeSheetColumnMapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_puriya_has_lab_treatment_columns(): void
    {
        $columns = IncomeSheetColumnMap::columnsForDoctor('PURIYA');

        $this->assertSame(['MC' => 'H', 'ZIR' => 'I', 'POST' => 'N', 'REMOV' => 'P'], $columns);
        $this->assertFalse(IncomeSheetColumnMap::isPaymentsOnlyDoctor('PURIYA'));
    }

    public function test_puriya_has_no_impl_column(): void
    {
        $this->assertNull(IncomeSheetColumnMap::columnFor('PURIYA', 'IMPL'));
        $this->assertSame('H', IncomeSheetColumnMap::columnFor('PURIYA', 'MC'));
    }

    public function test_riyad_alias_resolves_column_map(): void
    {
        $this->assertSame('I', IncomeSheetColumnMap::columnFor('RIYADH', 'ZIR'));
    }
}
