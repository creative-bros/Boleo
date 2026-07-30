<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CondominiumProfile;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class QuoteRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'external_reference' => ['nullable', 'string', 'max:100'],
            'source_system' => ['nullable', 'string', 'max:100'],
            'condominium_profile_id' => ['nullable', 'integer', 'exists:condominium_profiles,id'],
            'condominium' => ['nullable', 'required_without:condominium_profile_id', 'string', 'max:180'],
            'contact_name' => ['required', 'string', 'max:180'],
            'contact_email' => ['nullable', 'required_without:contact_phone', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'required_without:contact_email', 'string', 'max:40'],
            'service_type' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'desired_date' => ['nullable', 'date'],
            'budget_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'metadata' => ['nullable', 'array'],
        ]);

        $sourceSystem = trim((string) ($data['source_system'] ?? ''));
        $externalReference = trim((string) ($data['external_reference'] ?? ''));

        if ($externalReference !== '') {
            $existingRequest = QuoteRequest::query()
                ->where('source_system', $sourceSystem)
                ->where('external_reference', $externalReference)
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'ok' => true,
                    'folio' => $existingRequest->quote_number,
                    'status' => $existingRequest->status,
                    'duplicate' => true,
                ]);
            }
        }

        $profile = $this->resolveCondominiumProfile($data);
        $condominiumName = trim((string) ($data['condominium'] ?? $profile?->commercial_name ?? ''));

        $quoteRequest = QuoteRequest::query()->create([
            'condominium_profile_id' => $profile?->id,
            'condominium_name' => $condominiumName,
            'external_reference' => $externalReference !== '' ? $externalReference : null,
            'source_system' => $sourceSystem,
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'service_type' => $data['service_type'],
            'description' => $data['description'],
            'desired_date' => $data['desired_date'] ?? null,
            'budget_amount' => $data['budget_amount'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => QuoteRequest::STATUS_RECEIVED,
            'metadata' => $data['metadata'] ?? null,
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

    private function resolveCondominiumProfile(array $data): ?CondominiumProfile
    {
        if (! empty($data['condominium_profile_id'])) {
            return CondominiumProfile::query()->find($data['condominium_profile_id']);
        }

        $condominiumName = trim((string) ($data['condominium'] ?? ''));

        if ($condominiumName === '') {
            return null;
        }

        return CondominiumProfile::query()
            ->where('commercial_name', $condominiumName)
            ->first();
    }
}
