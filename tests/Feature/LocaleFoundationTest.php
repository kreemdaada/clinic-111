<?php

namespace Tests\Feature;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Lab;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_language_dropdown_in_layout(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('name="locale"', false)
            ->assertSee(__('settings.language.options.en'), false)
            ->assertSee(__('settings.language.options.de'), false)
            ->assertSee(__('settings.language.options.ar'), false)
            ->assertDontSee(__('common.actions.save'), false);
    }

    public function test_authenticated_user_with_locale_de_sets_app_locale(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk();

        $this->assertSame('de', app()->getLocale());
    }

    public function test_authenticated_user_with_locale_ar_sets_app_locale_and_rtl(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'ar']);

        $response = $this->actingAs($user)->get(route('imports.index'));

        $response->assertOk();
        $this->assertSame('ar', app()->getLocale());
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
    }

    public function test_user_without_custom_locale_falls_back_to_english(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'en']);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_german_user_sees_ltr_direction(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('lang="de"', false);
    }

    public function test_carbon_uses_german_month_name_for_de_locale(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)->get(route('imports.index'));

        $this->assertSame('Juni', Carbon::create(2026, 6, 1)->translatedFormat('F'));
    }

    public function test_invalid_locale_is_rejected_on_update(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->from(route('imports.index'))
            ->put(route('settings.language.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_user_can_switch_language_to_german_and_returns_to_previous_page(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->from(route('imports.index'))
            ->put(route('settings.language.update'), ['locale' => 'de'])
            ->assertRedirect(route('imports.index'))
            ->assertSessionMissing('status');

        $this->assertSame('de', $user->fresh()->locale);
    }

    public function test_user_can_switch_language_to_arabic(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->from(route('imports.index'))
            ->put(route('settings.language.update'), ['locale' => 'ar'])
            ->assertRedirect(route('imports.index'));

        $this->assertSame('ar', $user->fresh()->locale);
    }

    public function test_user_can_switch_language_to_english(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->from(route('imports.index'))
            ->put(route('settings.language.update'), ['locale' => 'en'])
            ->assertRedirect(route('imports.index'));

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_german_user_sees_translated_navigation_and_dashboard(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('Importe', false)
            ->assertSee('Abmelden', false)
            ->assertDontSee('>Logout<', false);

        $this->actingAs($user)
            ->get(route('configuration.dashboard'))
            ->assertOk()
            ->assertSee('Konfiguration', false)
            ->assertSee('Letzte Aktivität', false);
    }

    public function test_arabic_user_sees_translated_navigation_with_rtl(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'ar']);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('الاستيراد', false)
            ->assertSee('تسجيل الخروج', false);

        $this->actingAs($user)
            ->get(route('configuration.dashboard'))
            ->assertOk()
            ->assertSee('النشاط الأخير', false);
    }

    public function test_german_user_sees_translated_doctors_and_practice_overview_pages(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('Ärzte', false)
            ->assertSee('Provisionstyp', false)
            ->assertDontSee('<h1 class="page-title">Doctors</h1>', false);

        $this->actingAs($user)
            ->get(route('clinic.financial-overview'))
            ->assertOk()
            ->assertSee('Praxisübersicht', false)
            ->assertDontSee('<h1 class="page-title">Practice Overview</h1>', false);
    }

    public function test_arabic_user_sees_translated_doctors_and_monthly_income_with_rtl(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'ar']);

        $this->actingAs($user)
            ->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('الأطباء', false)
            ->assertDontSee('<h1 class="page-title">Doctors</h1>', false);

        $this->actingAs($user)
            ->get(route('monthly-income.index'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('دخل الشهر', false)
            ->assertDontSee('<h1 class="page-title">Monthly Income</h1>', false);
    }

    public function test_german_user_sees_translated_validation_error_for_invalid_locale(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->from(route('imports.index'))
            ->put(route('settings.language.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $errors = session('errors')->get('locale');
        $this->assertContains(__('validation.custom.locale.in', [], 'de'), $errors);
    }

    public function test_german_user_sees_translated_configuration_dashboard_module_labels(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('configuration.dashboard'))
            ->assertOk()
            ->assertSee('Ärzte verwalten', false)
            ->assertDontSee('Manage doctors', false);
    }

    public function test_arabic_user_sees_translated_doctor_flash_message_after_create(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'ar']);
        $mainLab = Lab::query()->where('code', 'MAIN_LAB')->firstOrFail();

        $this->actingAs($user)
            ->post(route('doctors.store'), [
                'code' => 'AR_DOC',
                'name' => 'Dr Arabic',
                'commission_type' => 'percentage',
                'commission_percentage' => '30',
                'default_lab_id' => $mainLab->id,
            ])
            ->assertRedirect(route('doctors.index'))
            ->assertSessionHas('success', __('doctors.flash.created', ['code' => 'AR_DOC'], 'ar'));
    }

    public function test_arabic_user_pages_use_western_digits_on_representative_screens(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'ar']);

        $pages = [
            route('imports.index'),
            route('doctors.index'),
            route('nurses.index'),
            route('treatments.index'),
            route('monthly-income.index', ['month' => '2026-06']),
            route('clinic.financial-overview', ['month' => '2026-06']),
            route('configuration.dashboard'),
        ];

        foreach ($pages as $url) {
            $response = $this->actingAs($user)->get($url);

            $response->assertOk();
            $response->assertSee('dir="rtl"', false);
            $this->assertDoesNotMatchRegularExpression(
                '/[٠-٩۰-۹]/u',
                (string) $response->getContent(),
                'Arabic-Indic digits found on '.$url,
            );
        }
    }

    public function test_german_user_sees_translated_buttons_on_key_pages(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee(__('import.index.start_import', [], 'de'), false)
            ->assertSee(__('import.index.upload_file', [], 'de'), false)
            ->assertDontSee('>Start import<', false);

        $this->actingAs($user)
            ->get(route('labs.index'))
            ->assertOk()
            ->assertSee(__('common.filter.reset', [], 'de'), false)
            ->assertSee(__('common.actions.save', [], 'de'), false)
            ->assertDontSee('>Save<', false);

        $this->actingAs($user)
            ->get(route('daily-report.index'))
            ->assertOk()
            ->assertSee(__('daily_reports.actions.create_report', [], 'de'), false)
            ->assertDontSee('>Create report<', false);
    }

    public function test_arabic_user_sees_translated_buttons_on_key_pages(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'ar']);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('import.index.start_import', [], 'ar'), false)
            ->assertDontSee('>Start import<', false);

        $this->actingAs($user)
            ->get(route('labs.index'))
            ->assertOk()
            ->assertSee(__('common.actions.delete', [], 'ar'), false)
            ->assertSee(__('common.actions.save', [], 'ar'), false);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(__('configuration.actions.create_user', [], 'ar'), false)
            ->assertSee(__('configuration.actions.reset_password', [], 'ar'), false)
            ->assertDontSee('>Create user<', false);
    }

    public function test_arabic_import_overview_month_label_uses_western_year_digits(): void
    {
        $this->seedAccountingData();
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->update(['locale' => 'ar']);

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'test-report',
            'status' => ReportStatus::Uploaded,
        ]);

        $response = $this->actingAs($user)->get(route('logs.extraction', $report));

        $response->assertOk();
        $response->assertSee('2026', false);
        $this->assertDoesNotMatchRegularExpression('/[٠-٩۰-۹]/u', (string) $response->getContent());
    }
}
