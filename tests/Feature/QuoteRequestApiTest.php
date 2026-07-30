<?php

namespace Tests\Feature;

use App\Models\CondominiumProfile;
use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_request_endpoint_requires_external_token(): void
    {
        config(['services.external_quote_requests.token' => 'test-token']);

        $this->postJson('/api/v1/solicitudes-cotizacion', $this->validPayload())
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No autorizado.');
    }

    public function test_quote_request_endpoint_stores_valid_requests(): void
    {
        config(['services.external_quote_requests.token' => 'test-token']);

        $profile = CondominiumProfile::query()->create([
            'commercial_name' => 'Boleo Condominio',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/v1/solicitudes-cotizacion', $this->validPayload([
                'condominium_profile_id' => $profile->id,
                'condominium' => null,
            ]));

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', QuoteRequest::STATUS_RECEIVED)
            ->assertJsonStructure(['folio']);

        $quoteRequest = QuoteRequest::query()->firstOrFail();

        $this->assertSame('COT-'.now()->format('Y').'-000001', $quoteRequest->quote_number);
        $this->assertSame($profile->id, $quoteRequest->condominium_profile_id);
        $this->assertSame('Boleo Condominio', $quoteRequest->condominium_name);
        $this->assertSame('EXT-123', $quoteRequest->external_reference);
        $this->assertSame('erp-demo', $quoteRequest->source_system);
        $this->assertSame('Impermeabilizacion', $quoteRequest->service_type);
    }

    public function test_quote_request_endpoint_is_idempotent_by_source_and_external_reference(): void
    {
        config(['services.external_quote_requests.token' => 'test-token']);

        $headers = ['Authorization' => 'Bearer test-token'];

        $this->withHeaders($headers)
            ->postJson('/api/v1/solicitudes-cotizacion', $this->validPayload())
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/v1/solicitudes-cotizacion', $this->validPayload())
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('folio', 'COT-'.now()->format('Y').'-000001');

        $this->assertSame(1, QuoteRequest::query()->count());
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'external_reference' => 'EXT-123',
            'source_system' => 'erp-demo',
            'condominium' => 'Boleo Condominio',
            'contact_name' => 'Laura Nieto',
            'contact_email' => 'laura@example.com',
            'contact_phone' => '5512345678',
            'service_type' => 'Impermeabilizacion',
            'description' => 'Se requiere cotizacion para impermeabilizar la azotea principal.',
            'desired_date' => now()->addWeek()->toDateString(),
            'budget_amount' => 25000,
            'priority' => 'normal',
            'metadata' => [
                'department' => 'mantenimiento',
            ],
        ], $overrides);
    }
}
