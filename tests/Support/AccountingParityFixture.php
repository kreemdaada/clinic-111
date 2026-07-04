<?php

namespace Tests\Support;

use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\Treatment;

/**
 * Shared accounting fixture for parity characterization tests.
 *
 * Values document the current production behavior for Clinic 111 (JACK, April 2028).
 */
final readonly class AccountingParityFixture
{
    public const MONTH = '2028-04';

    public const WORK_DATE = '2028-04-01';

    public const DHS_PAYMENT = '1500.00';

    public const LAB_COST_AED = '105.00';

    public const NET_TOTAL_AED = '1395.00';

    public const DOCTOR_INCOME_AED = '488.25';

    public const NURSE_COMMISSION_AED = '48.00';

    public const CLINIC_INCOME_AED = '858.75';

    public const OPG_NORMAL_VALUE_AED = '600.00';

    public const OPG_3D_VALUE_AED = '360.00';

    public const OVERVIEW_RESULT_AED = '1347.00';

    public const CLINIC_B_REVENUE_AED = '8888.00';

    public const CLINIC_B_NURSE_COMMISSION_AED = '20.00';

    public const CLINIC_B_OPG_NORMAL_VALUE_AED = '400.00';

    public function __construct(
        public Doctor $doctor,
        public DailyReport $report,
        public DailyWorkRow $workRow,
        public Treatment $opgNormalTreatment,
        public Treatment $opg3dTreatment,
        public Treatment $mcTreatment,
        public Nurse $opgNormalNurse,
        public Nurse $opg3dNurse,
        public int $exportDayRow,
        public int $exportTotalRow,
    ) {}
}
