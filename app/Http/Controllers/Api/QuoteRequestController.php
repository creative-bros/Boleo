<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuoteRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->normalizeBooleanFields($request);

        $data = $request->validate([
            'nombre_cliente' => ['required', 'string', 'max:180'],
            'correo_cliente' => ['required', 'email', 'max:255'],
            'telefono_cliente' => ['required', 'string', 'max:40'],
            'ubicacion_inmueble' => ['required', 'string', 'max:255'],
            'presupuesto_mensual' => ['required', 'string', 'max:100'],
            'cuenta_con_administracion' => ['required', 'boolean'],
            'cuenta_con_certificacion_prosoc' => ['required', 'boolean'],
            'cantidad_departamentos' => ['required', 'string', 'max:80'],
            'comentario' => ['required', 'string', 'max:5000'],
            'fecha_consulta' => ['required', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:100'],
            'fuente' => ['nullable', 'string', 'max:100'],
        ]);

        $source = trim((string) ($data['source'] ?? $data['fuente'] ?? '')) ?: 'Website Form';

        $quoteRequest = QuoteRequest::query()->create([
            'condominium_name' => $data['ubicacion_inmueble'],
            'source_system' => $source,
            'client_name' => $data['nombre_cliente'],
            'client_email' => $data['correo_cliente'],
            'client_phone' => $data['telefono_cliente'],
            'property_location' => $data['ubicacion_inmueble'],
            'monthly_budget' => $data['presupuesto_mensual'],
            'has_administration' => $data['cuenta_con_administracion'],
            'has_prosoc_certification' => $data['cuenta_con_certificacion_prosoc'],
            'apartment_count' => $data['cantidad_departamentos'],
            'comment' => $data['comentario'],
            'consultation_date' => $data['fecha_consulta'],
            'source' => $source,
            'contact_name' => $data['nombre_cliente'],
            'contact_email' => $data['correo_cliente'],
            'contact_phone' => $data['telefono_cliente'],
            'service_type' => 'Administracion de condominios',
            'description' => $data['comentario'],
            'priority' => 'normal',
            'status' => QuoteRequest::STATUS_RECEIVED,
            'origin_ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);

        $quoteRequest->forceFill([
            'quote_number' => sprintf('COT-%s-%06d', now()->format('Y'), $quoteRequest->id),
        ])->save();

        return response()->json([
            'ok' => true,
            'folio' => $quoteRequest->quote_number,
            'status' => $quoteRequest->status,
        ], Response::HTTP_CREATED);
    }

    private function normalizeBooleanFields(Request $request): void
    {
        foreach (['cuenta_con_administracion', 'cuenta_con_certificacion_prosoc'] as $field) {
            if ($request->has($field)) {
                $request->merge([
                    $field => $this->normalizeBooleanValue($request->input($field)),
                ]);
            }
        }
    }

    private function normalizeBooleanValue(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $normalized = str_replace(['í', 'Í'], 'i', strtolower(trim($value)));

        return match ($normalized) {
            'si', 'yes', 'true', '1', 'on' => true,
            'no', 'false', '0', 'off' => false,
            default => $value,
        };
    }
}
