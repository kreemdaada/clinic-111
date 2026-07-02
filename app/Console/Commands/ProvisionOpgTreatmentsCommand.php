<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Services\Configuration\OpgTreatmentProvisioner;
use Illuminate\Console\Command;

/**
 * Idempotent OPG_NORMAL / OPG_3D provisioning for existing clinics.
 */
class ProvisionOpgTreatmentsCommand extends Command
{
    protected $signature = 'clinic:provision-opg-treatments
                            {--dry-run : Report changes without writing}
                            {--clinic= : Limit to one clinic id}';

    protected $description = 'Provision OPG_NORMAL and OPG_3D treatments for clinics';

    public function handle(OpgTreatmentProvisioner $provisioner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $clinicId = $this->option('clinic');

        $query = Clinic::query()->where('is_active', true)->orderBy('id');

        if ($clinicId !== null) {
            $query->where('id', (int) $clinicId);
        }

        $clinics = $query->get();

        if ($clinics->isEmpty()) {
            $this->warn('No clinics matched.');

            return self::FAILURE;
        }

        $hasConflicts = false;

        foreach ($clinics as $clinic) {
            $result = $provisioner->provisionForClinic($clinic, $dryRun);

            $this->line("Clinic {$clinic->code} (#{$clinic->id})");

            foreach ($result->created as $code) {
                $this->info("  created: {$code}");
            }

            foreach ($result->updated as $code) {
                $this->info("  updated: {$code}");
            }

            foreach ($result->skipped as $code) {
                $this->comment("  skipped: {$code}");
            }

            foreach ($result->conflicts as $conflict) {
                $hasConflicts = true;
                $this->error("  conflict: {$conflict}");
            }
        }

        if ($dryRun) {
            $this->comment('Dry run — no changes written.');
        }

        return $hasConflicts ? self::FAILURE : self::SUCCESS;
    }
}
