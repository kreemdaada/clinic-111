<?php

namespace App\Services\Configuration;

use App\Enums\AuditAction;
use App\Enums\CommissionType;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use Illuminate\Database\Eloquent\Model;

/**
 * Aggregates configuration module statistics, recent audit activity, and health warnings.
 *
 * All reads are scoped to the authenticated user's clinic (ADR-028).
 */
class ConfigurationDashboardService
{
    use ScopesConfigurationQueries;

    private const RECENT_ACTIVITY_LIMIT = 15;

    /** @var list<AuditAction> */
    private const CONFIGURATION_AUDIT_ACTIONS = [
        AuditAction::DoctorCreated,
        AuditAction::DoctorUpdated,
        AuditAction::DoctorDeactivated,
        AuditAction::CommissionChange,
        AuditAction::LabCreated,
        AuditAction::LabUpdated,
        AuditAction::LabDeactivated,
        AuditAction::LabActivated,
        AuditAction::TreatmentCreated,
        AuditAction::TreatmentUpdated,
        AuditAction::TreatmentDeactivated,
        AuditAction::TreatmentActivated,
        AuditAction::LabPriceCreated,
        AuditAction::LabPriceDeactivated,
        AuditAction::LabPriceActivated,
        AuditAction::PriceChange,
        AuditAction::DoctorFixedFeeCreated,
        AuditAction::DoctorFixedFeeDeactivated,
        AuditAction::DoctorFixedFeeActivated,
        AuditAction::UserCreated,
        AuditAction::UserRoleChanged,
        AuditAction::UserDeactivated,
        AuditAction::PasswordReset,
    ];

    public function __construct(
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    /**
     * @return array{
     *     modules: list<array<string, mixed>>,
     *     recent_activity: list<array<string, mixed>>,
     *     health_warnings: list<array<string, string>>,
     * }
     */
    public function buildDashboard(): array
    {
        return [
            'modules' => $this->moduleStatistics(),
            'recent_activity' => $this->recentActivity(),
            'health_warnings' => $this->healthWarnings(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function moduleStatistics(): array
    {
        return [
            $this->moduleCard(
                key: 'doctors',
                label: 'Doctors',
                model: Doctor::class,
                indexRoute: 'doctors.index',
                quickActionLabel: 'Manage doctors',
            ),
            $this->moduleCard(
                key: 'labs',
                label: 'Laboratories',
                model: Lab::class,
                indexRoute: 'labs.index',
                quickActionLabel: 'Manage laboratories',
            ),
            $this->moduleCard(
                key: 'treatments',
                label: 'Treatments',
                model: Treatment::class,
                indexRoute: 'treatments.index',
                quickActionLabel: 'Manage treatments',
            ),
            $this->moduleCard(
                key: 'lab_prices',
                label: 'Lab prices',
                model: LabPrice::class,
                indexRoute: 'lab-prices.index',
                quickActionLabel: 'Manage lab prices',
            ),
            $this->moduleCard(
                key: 'doctor_fixed_fees',
                label: 'No-commission fees',
                model: DoctorFixedFee::class,
                indexRoute: 'doctor-fixed-fees.index',
                quickActionLabel: 'Manage fee rules',
            ),
            $this->moduleCard(
                key: 'users',
                label: 'Users',
                model: User::class,
                indexRoute: 'admin.users.index',
                quickActionLabel: 'Manage users',
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentActivity(int $limit = self::RECENT_ACTIVITY_LIMIT): array
    {
        $actionValues = array_map(
            fn (AuditAction $action) => $action->value,
            self::CONFIGURATION_AUDIT_ACTIONS,
        );

        $currentClinicId = $this->currentClinicId();
        $clinicMorph = (new Clinic)->getMorphClass();

        return AuditLog::query()
            ->with(['user', 'auditable'])
            ->whereIn('action', $actionValues)
            ->where(function ($query) use ($currentClinicId, $clinicMorph) {
                $query->whereHasMorph(
                    'auditable',
                    [Doctor::class, Lab::class, Treatment::class, LabPrice::class, DoctorFixedFee::class, User::class],
                    fn ($q) => $q->where('clinic_id', $currentClinicId),
                )->orWhere(function ($q) use ($currentClinicId, $clinicMorph) {
                    $q->where('auditable_type', $clinicMorph)
                        ->where('auditable_id', $currentClinicId);
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'date' => $log->created_at?->format('Y-m-d H:i'),
                'user' => $log->user?->email ?? 'System',
                'action' => $this->formatActionLabel($log->action),
                'target' => $this->formatAuditTarget($log),
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    public function healthWarnings(): array
    {
        $warnings = [];

        if ($this->forCurrentClinic(Lab::class)->where('is_active', true)->count() === 0) {
            $warnings[] = $this->warning('no_active_labs', 'No active laboratories are configured.');
        }

        if ($this->forCurrentClinic(Treatment::class)->where('is_active', true)->count() === 0) {
            $warnings[] = $this->warning('no_active_treatments', 'No active treatments are configured.');
        }

        if ($this->forCurrentClinic(LabPrice::class)->where('is_active', true)->count() === 0) {
            $warnings[] = $this->warning('no_active_lab_prices', 'No active lab prices are configured.');
        }

        $activeFixedDoctors = $this->forCurrentClinic(Doctor::class)
            ->where('is_active', true)
            ->where('commission_type', CommissionType::Fixed)
            ->count();

        if ($activeFixedDoctors > 0 && $this->forCurrentClinic(DoctorFixedFee::class)->where('is_active', true)->count() === 0) {
            $warnings[] = $this->warning('no_active_fixed_fees', 'No active no-commission fee rules are configured.');
        }

        $inactiveDoctors = $this->forCurrentClinic(Doctor::class)->where('is_active', false)->count();
        if ($inactiveDoctors > 0) {
            $warnings[] = $this->warning(
                'inactive_doctors',
                "{$inactiveDoctors} doctor(s) are inactive.",
            );
        }

        $fixedDoctorsMissingFees = $this->forCurrentClinic(Doctor::class)
            ->where('is_active', true)
            ->where('commission_type', CommissionType::Fixed)
            ->whereDoesntHave('doctorFixedFees', fn ($query) => $query->where('is_active', true))
            ->pluck('code');

        if ($fixedDoctorsMissingFees->isNotEmpty()) {
            $warnings[] = $this->warning(
                'fixed_doctors_without_fees',
                'Active no-commission doctor(s) without fee rules: '.$fixedDoctorsMissingFees->join(', ').'.',
            );
        }

        $labCostTreatmentsWithoutPrice = $this->forCurrentClinic(Treatment::class)
            ->where('is_active', true)
            ->where('has_lab_cost', true)
            ->whereDoesntHave('labPrices', fn ($query) => $query->where('is_active', true))
            ->pluck('code');

        if ($labCostTreatmentsWithoutPrice->isNotEmpty()) {
            $warnings[] = $this->warning(
                'lab_cost_treatments_without_prices',
                'Lab-cost treatment(s) without active prices: '.$labCostTreatmentsWithoutPrice->join(', ').'.',
            );
        }

        $percentageDoctorsMissingRate = $this->forCurrentClinic(Doctor::class)
            ->where('is_active', true)
            ->where('commission_type', CommissionType::Percentage)
            ->where(function ($query) {
                $query
                    ->whereNull('commission_percentage')
                    ->orWhere('commission_percentage', '<=', 0);
            })
            ->pluck('code');

        if ($percentageDoctorsMissingRate->isNotEmpty()) {
            $warnings[] = $this->warning(
                'percentage_doctors_missing_rate',
                'Percentage doctor(s) without a valid commission rate: '.$percentageDoctorsMissingRate->join(', ').'.',
            );
        }

        return $warnings;
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, mixed>
     */
    private function moduleCard(
        string $key,
        string $label,
        string $model,
        string $indexRoute,
        string $quickActionLabel,
    ): array {
        $total = $this->forCurrentClinic($model)->count();
        $active = $this->forCurrentClinic($model)->where('is_active', true)->count();

        return [
            'key' => $key,
            'label' => $label,
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'index_route' => $indexRoute,
            'quick_action_label' => $quickActionLabel,
        ];
    }

    /**
     * @return array{code: string, message: string}
     */
    private function warning(string $code, string $message): array
    {
        return [
            'code' => $code,
            'message' => $message,
        ];
    }

    private function formatActionLabel(AuditAction $action): string
    {
        return str_replace('_', ' ', $action->value);
    }

    private function formatAuditTarget(AuditLog $log): string
    {
        $auditable = $log->auditable;

        if ($auditable instanceof Doctor) {
            return $auditable->code;
        }

        if ($auditable instanceof Lab) {
            return $auditable->code;
        }

        if ($auditable instanceof Treatment) {
            return $auditable->code;
        }

        if ($auditable instanceof LabPrice) {
            return '#'.$auditable->id;
        }

        if ($auditable instanceof DoctorFixedFee) {
            return '#'.$auditable->id;
        }

        if ($auditable instanceof User) {
            return $auditable->email;
        }

        if ($log->auditable_type !== null && $log->auditable_id !== null) {
            return class_basename($log->auditable_type).' #'.$log->auditable_id;
        }

        return '—';
    }
}
