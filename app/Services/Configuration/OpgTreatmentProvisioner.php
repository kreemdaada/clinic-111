<?php

namespace App\Services\Configuration;

use App\DTOs\OpgProvisionResult;
use App\Models\Clinic;
use App\Models\Treatment;
use App\Support\OpgTreatmentCodes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent OPG_NORMAL and OPG_3D provisioning per clinic (ADR-039).
 */
class OpgTreatmentProvisioner
{
    /** @var array<string, array{name: string, treatment_price: string, treatment_price_currency: string}> */
    private const DEFINITIONS = [
        'OPG_NORMAL' => [
            'name' => 'OPG-Normal',
            'treatment_price' => '200.00',
            'treatment_price_currency' => 'AED',
        ],
        'OPG_3D' => [
            'name' => 'OPG 3D',
            'treatment_price' => '360.00',
            'treatment_price_currency' => 'AED',
        ],
    ];

    public function provisionForClinic(Clinic $clinic, bool $dryRun = false): OpgProvisionResult
    {
        return DB::transaction(function () use ($clinic, $dryRun) {
            $created = [];
            $updated = [];
            $skipped = [];
            $conflicts = [];

            $existing = Treatment::query()
                ->where('clinic_id', $clinic->id)
                ->get();

            foreach (self::DEFINITIONS as $code => $definition) {
                $exact = $existing->firstWhere('code', $code);

                if ($exact === null) {
                    $similar = $this->findSimilarConflict($existing, $code);

                    if ($similar !== null) {
                        $conflicts[] = "Clinic {$clinic->code}: found similar treatment code \"{$similar->code}\" instead of required \"{$code}\". Review manually before provisioning.";

                        continue;
                    }

                    if (! $dryRun) {
                        $this->createTreatment($clinic, $code, $definition);
                    }

                    $created[] = $code;

                    continue;
                }

                $changes = $this->reconcileExisting($exact, $definition, $dryRun);

                if ($changes['conflict'] !== null) {
                    $conflicts[] = $changes['conflict'];

                    continue;
                }

                if ($changes['updated']) {
                    $updated[] = $code;
                } else {
                    $skipped[] = $code;
                }
            }

            return new OpgProvisionResult($created, $updated, $skipped, $conflicts);
        });
    }

    /**
     * @param  array{name: string, treatment_price: string, treatment_price_currency: string}  $definition
     * @return array{updated: bool, conflict: string|null}
     */
    private function reconcileExisting(Treatment $treatment, array $definition, bool $dryRun): array
    {
        $conflict = null;
        $updated = false;

        if ($treatment->treatment_price !== null
            && bccomp((string) $treatment->treatment_price, $definition['treatment_price'], 2) !== 0
        ) {
            $conflict = "Treatment {$treatment->code} has price {$treatment->treatment_price} {$treatment->treatment_price_currency}; expected {$definition['treatment_price']} {$definition['treatment_price_currency']}.";
        }

        if ($treatment->treatment_price_currency !== null
            && strtoupper((string) $treatment->treatment_price_currency) !== $definition['treatment_price_currency']
            && $treatment->treatment_price !== null
        ) {
            $conflict ??= "Treatment {$treatment->code} has currency {$treatment->treatment_price_currency}; expected {$definition['treatment_price_currency']}.";
        }

        if ($conflict !== null) {
            return ['updated' => false, 'conflict' => $conflict];
        }

        $needsSave = false;

        if ($treatment->treatment_price === null) {
            $treatment->treatment_price = $definition['treatment_price'];
            $needsSave = true;
        }

        if ($treatment->treatment_price_currency === null) {
            $treatment->treatment_price_currency = $definition['treatment_price_currency'];
            $needsSave = true;
        }

        if (! $treatment->requires_nurse_commission) {
            $treatment->requires_nurse_commission = true;
            $needsSave = true;
        }

        if ($treatment->has_lab_cost) {
            $treatment->has_lab_cost = false;
            $needsSave = true;
        }

        if (! $treatment->is_active) {
            $treatment->is_active = true;
            $needsSave = true;
        }

        if ($needsSave && ! $dryRun) {
            $treatment->save();
            $updated = true;
        } elseif ($needsSave) {
            $updated = true;
        }

        return ['updated' => $updated, 'conflict' => null];
    }

    /**
     * @param  array{name: string, treatment_price: string, treatment_price_currency: string}  $definition
     */
    private function createTreatment(Clinic $clinic, string $code, array $definition): Treatment
    {
        $treatment = Treatment::query()->create([
            'clinic_id' => $clinic->id,
            'code' => $code,
            'name' => $definition['name'],
            'treatment_price' => $definition['treatment_price'],
            'treatment_price_currency' => $definition['treatment_price_currency'],
        ]);
        $treatment->requires_nurse_commission = true;
        $treatment->has_lab_cost = false;
        $treatment->is_active = true;
        $treatment->save();

        return $treatment;
    }

    /**
     * @param  Collection<int, Treatment>  $existing
     */
    private function findSimilarConflict($existing, string $expectedCode): ?Treatment
    {
        $expectedNormalized = $this->normalizeCode($expectedCode);

        foreach ($existing as $treatment) {
            if ($treatment->code === $expectedCode) {
                continue;
            }

            if ($this->normalizeCode($treatment->code) === $expectedNormalized) {
                return $treatment;
            }
        }

        return null;
    }

    private function normalizeCode(string $code): string
    {
        return OpgTreatmentCodes::normalize($code);
    }
}
