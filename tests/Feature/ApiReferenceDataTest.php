<?php

namespace Tests\Feature;

use App\Models\Doctor;
use Tests\TestCase;

class ApiReferenceDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
    }

    public function test_authenticated_user_can_list_doctors(): void
    {
        $this->actingAsRole('viewer');

        $response = $this->getJson('/api/doctors');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'code', 'commission_type', 'commission_percentage'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/doctors')->assertUnauthorized();
    }

    public function test_doctors_are_loaded_from_database_not_hardcoded(): void
    {
        $this->actingAsRole('admin');

        $response = $this->getJson('/api/doctors');
        $jack = collect($response->json('data'))->firstWhere('code', 'JACK');

        $this->assertNotNull($jack);
        $this->assertSame(
            Doctor::query()->where('code', 'JACK')->value('commission_percentage'),
            $jack['commission_percentage'],
        );
    }
}
