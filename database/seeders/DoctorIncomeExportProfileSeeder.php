<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorIncomeExportProfile;
use Illuminate\Database\Seeder;

/**
 * Original Income Excel sheet names and treatment column letters per doctor.
 *
 * Treatment counts include codes in profile (e.g. REMOV) regardless of lab-cost flag.
 * JOB column sums lab_jobs only.
 *
 * @see database/seeders/README.md
 */
class DoctorIncomeExportProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            'JACK' => [
                'sheet_name' => 'Dr.Jack',
                'layout' => 'standard',
                'first_day_row' => 2,
                'write_payment_headers' => true,
                'summary_shows_net_total' => false,
                'payment_columns' => [
                    'dhs' => 'B',
                    'cheque' => 'C',
                    'tabby' => 'D',
                    'usd' => 'E',
                    'usd_to_aed' => 'F',
                    'visa' => 'G',
                    'total' => 'H',
                    'job' => 'I',
                ],
                'treatment_columns' => [
                    'MC' => 'J', 'ZIR' => 'K', 'IMPL-CR' => 'L', 'IMPL-ZIR' => 'M', 'VENEER' => 'N',
                    'IMPL' => 'P', 'POST' => 'R', 'ABT' => 'S', 'REMOV' => 'T',
                ],
            ],
            'RIYAD' => [
                'sheet_name' => 'Dr.Riyadh',
                'layout' => 'standard',
                'first_day_row' => 3,
                'write_payment_headers' => false,
                'summary_shows_net_total' => true,
                'payment_columns' => [
                    'dhs' => 'B', 'usd' => 'C', 'usd_to_aed' => 'D', 'visa' => 'E', 'total' => 'F', 'job' => 'G',
                ],
                'treatment_columns' => [
                    'MC' => 'H', 'ZIR' => 'I', 'IMPL-CR' => 'J', 'IMPL-ZIR' => 'K', 'VENEER' => 'L',
                    'IMPL' => 'M', 'POST' => 'N', 'ABT' => 'O', 'REMOV' => 'P',
                ],
            ],
            'PURIYA' => [
                'sheet_name' => 'Dr Pouria',
                'layout' => 'standard',
                'first_day_row' => 3,
                'write_payment_headers' => false,
                'summary_shows_net_total' => true,
                'payment_columns' => [
                    'dhs' => 'B', 'usd' => 'C', 'usd_to_aed' => 'D', 'visa' => 'E', 'total' => 'F', 'job' => 'G',
                ],
                'treatment_columns' => [
                    'ZIR' => 'H', 'MC' => 'I', 'POST' => 'J', 'SXP' => 'K', 'CF' => 'L',
                    'RCT' => 'M', 'RCF' => 'N', 'AF' => 'O',
                ],
            ],
            'WA' => [
                'sheet_name' => 'wael',
                'layout' => 'wael',
                'first_day_row' => 5,
                'write_payment_headers' => false,
                'summary_shows_net_total' => false,
                'payment_columns' => null,
                'treatment_columns' => null,
            ],
        ];

        foreach ($profiles as $doctorCode => $config) {
            $doctor = Doctor::query()->where('code', $doctorCode)->firstOrFail();

            DoctorIncomeExportProfile::query()->updateOrCreate(
                ['doctor_id' => $doctor->id],
                [
                    'sheet_name' => $config['sheet_name'],
                    'layout' => $config['layout'],
                    'first_day_row' => $config['first_day_row'],
                    'write_payment_headers' => $config['write_payment_headers'],
                    'summary_shows_net_total' => $config['summary_shows_net_total'],
                    'payment_columns' => $config['payment_columns'],
                    'treatment_columns' => $config['treatment_columns'],
                ],
            );
        }
    }
}
