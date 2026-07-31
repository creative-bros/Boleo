<?php

namespace Tests\Feature;

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

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/v1/solicitudes-cotizacion', $this->validPayload());

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', QuoteRequest::STATUS_RECEIVED)
            ->assertJsonStructure(['folio']);

        $quoteRequest = QuoteRequest::query()->firstOrFail();

        $this->assertSame('COT-'.now()->format('Y').'-000001', $quoteRequest->quote_number);
        $this->assertNull($quoteRequest->condominium_profile_id);
        $this->assertSame('Av. Paseo de la Reforma 123, CDMX', $quoteRequest->property_location);
        $this->assertSame('Av. Paseo de la Reforma 123, CDMX', $quoteRequest->condominium_name);
        $this->assertSame('Laura Nieto', $quoteRequest->client_name);
        $this->assertSame('laura@example.com', $quoteRequest->client_email);
        $this->assertSame('5512345678', $quoteRequest->client_phone);
        $this->assertSame('$25,000 - $30,000', $quoteRequest->monthly_budget);
        $this->assertTrue($quoteRequest->has_administration);
        $this->assertFalse($quoteRequest->has_prosoc_certification);
        $this->assertSame('24', $quoteRequest->apartment_count);
        $this->assertSame('Requiere propuesta de administracion mensual.', $quoteRequest->comment);
        $this->assertSame('2026-07-30 10:45', $quoteRequest->consultation_date);
        $this->assertSame('Website Form', $quoteRequest->source);
        $this->assertSame('Website Form', $quoteRequest->source_system);
        $this->assertSame('Administracion de condominios', $quoteRequest->service_type);
    }

    public function test_quote_request_endpoint_normalizes_boolean_text_values(): void
    {
        config(['services.external_quote_requests.token' => 'test-token']);

        $headers = ['Authorization' => 'Bearer test-token'];

        $this->withHeaders($headers)
            ->postJson('/api/v1/solicitudes-cotizacion', $this->validPayload([
                'cuenta_con_administracion' => 'Sí',
                'cuenta_con_certificacion_prosoc' => 'no',
            ]))
            ->assertCreated();

        $quoteRequest = QuoteRequest::query()->firstOrFail();

        $this->assertTrue($quoteRequest->has_administration);
        $this->assertFalse($quoteRequest->has_prosoc_certification);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'nombre_cliente' => 'Laura Nieto',
            'correo_cliente' => 'laura@example.com',
            'telefono_cliente' => '5512345678',
            'ubicacion_inmueble' => 'Av. Paseo de la Reforma 123, CDMX',
            'presupuesto_mensual' => '$25,000 - $30,000',
            'cuenta_con_administracion' => true,
            'cuenta_con_certificacion_prosoc' => false,
            'cantidad_departamentos' => '24',
            'comentario' => 'Requiere propuesta de administracion mensual.',
            'fecha_consulta' => '2026-07-30 10:45',
            'source' => 'Website Form',
        ], $overrides);
    }
}
