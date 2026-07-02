<?php

namespace App\Exceptions;

/**
 * Thrown when income Excel export is blocked by reconciliation errors.
 */
class IncomeExportBlockedException extends UserFacingException
{
    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    public function __construct(
        private readonly array $issues,
    ) {
        parent::__construct('Income export blocked by reconciliation errors.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    /**
     * Plain-language guidance for practice staff (one paragraph per item).
     *
     * @return list<string>
     */
    public function userFacingMessages(): array
    {
        $messages = [
            'The income export could not be created. Please check the report configuration and try again.',
        ];

        foreach ($this->issues as $issue) {
            if (($issue['severity'] ?? '') !== 'error') {
                continue;
            }

            $messages[] = $this->describeIssue($issue);
        }

        $messages[] = 'Open the daily report, review the affected doctor and day, then save your changes. '
            .'If an extraction log exists for this report, it lists the same issues.';

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function describeIssue(array $issue): string
    {
        $doctor = (string) ($issue['doctor'] ?? 'this doctor');
        $workDate = isset($issue['work_date']) ? ' on '.$issue['work_date'] : '';

        return match ($issue['type'] ?? '') {
            'lab_without_payments' => "Doctor {$doctor} has external lab costs but no payments were recorded{$workDate}. "
                .'If the treatment should not incur lab fees, open Treatments and turn off External lab cost, then re-save the day in the daily report. '
                .'Otherwise enter the payment amounts for that day.',
            'payment_mismatch' => "The collected total for doctor {$doctor}{$workDate} does not match the payment fields. "
                .'Open that day in the daily report and check DHS, cheque, Tabby, USD, and VISA amounts.',
            'lab_without_treatment_text' => "Doctor {$doctor} has lab costs but no treatment text{$workDate}. "
                .'Add the treatments for that day in the daily report.',
            default => (string) ($issue['message'] ?? 'An accounting check failed for this report.'),
        };
    }
}
