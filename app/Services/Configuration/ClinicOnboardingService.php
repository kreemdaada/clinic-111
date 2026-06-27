<?php

namespace App\Services\Configuration;

use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Lab;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Support\EmailVerificationSupport;
use Illuminate\Support\Facades\DB;

/**
 * Transactional clinic onboarding — creates clinic, owner, and minimal default configuration (ADR-030).
 */
class ClinicOnboardingService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array{
     *     clinic_name: string,
     *     clinic_code: string,
     *     country: string,
     *     currency: string,
     *     timezone: string,
     *     owner_name: string,
     *     owner_email: string,
     *     owner_password: string,
     * }  $data
     * @return array{clinic: Clinic, owner: User, default_lab: Lab}
     */
    public function register(array $data): array
    {
        $result = DB::transaction(function () use ($data) {
            $clinic = Clinic::query()->create([
                'name' => trim($data['clinic_name']),
                'code' => strtoupper(trim($data['clinic_code'])),
                'currency' => strtoupper(trim($data['currency'])),
                'timezone' => trim($data['timezone']),
                'country' => trim($data['country']),
            ]);
            $clinic->is_active = true;
            $clinic->save();

            $this->auditLogService->logClinicCreated($clinic);

            $owner = new User;
            $owner->fill([
                'name' => trim($data['owner_name']),
                'email' => strtolower(trim($data['owner_email'])),
                'role' => UserRole::Admin,
            ]);
            $owner->clinic_id = $clinic->id;
            $owner->password = $data['owner_password'];
            $owner->is_active = true;
            $owner->save();

            $this->auditLogService->logUserCreated($owner);

            $this->auditLogService->logClinicRegistered($clinic, $owner);

            $defaultLab = $this->createDefaultLab($clinic);

            return [
                'clinic' => $clinic->fresh(),
                'owner' => $owner->fresh(),
                'default_lab' => $defaultLab,
            ];
        });

        if (EmailVerificationSupport::shouldAutoVerifyWithoutDelivery()) {
            $result['owner']->forceFill(['email_verified_at' => now()])->save();
            $this->auditLogService->logEmailVerified($result['owner']);
        } else {
            $result['owner']->sendEmailVerificationNotification();
            $this->auditLogService->logEmailVerificationSent($result['owner']);
        }

        return $result;
    }

    private function createDefaultLab(Clinic $clinic): Lab
    {
        $lab = Lab::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Main Laboratory',
            'code' => $this->defaultLabCode($clinic),
        ]);

        $this->auditLogService->logLabCreated($lab);

        return $lab->fresh();
    }

    private function defaultLabCode(Clinic $clinic): string
    {
        return strtoupper($clinic->code).'_MAIN_LAB';
    }
}
