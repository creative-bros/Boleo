<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait BillingReports
{
    private function paymentInformation(Collection $payments): array
    {
        return $payments->map(fn ($payment): array => [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'payment_date' => $payment->payment_date?->format('d/m/Y'),
            'applied_at' => $payment->paid_at?->format('d/m/Y'),
            'method' => $payment->payment_method,
            'status' => $payment->status,
        ])->values()->all();
    }

    /** One source of monthly balances for the screen and exports. */
    private function reportStatementRows(?ImportedResidentAccount $account, ?Unit $unit, CondominiumProfile $profile): Collection
    {
        $receipts = $unit?->residentReceipts->where('condominium_profile_id', $profile->id) ?? collect();
        $fee = $unit ? (float) $this->billingSnapshot($unit, $profile)['fee_amount'] : (float) $profile->ordinary_fee_amount;
        $rows = $this->residentAccountStatementRows($account, $unit, $fee);

        if ($this->isTotalOnlyStatement($rows) && $receipts->isNotEmpty()) {
            $rows = [];
        }

        if ($rows === [] && $receipts->isEmpty() && $account) {
            $rows = [[
                'name' => 'Saldo histórico sin desglose mensual', 'exigible_raw' => (float) $account->total_debt,
                'paid_raw' => 0, 'debt_raw' => (float) $account->total_debt,
                'status' => $account->total_debt > 0 ? 'PENDIENTE' : 'SIN ADEUDO',
            ]];
        }

        return collect($this->statementRowsWithReceiptState($rows, $receipts));
    }

    public function exportBillingReport(Request $request): StreamedResponse
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'condominium_profile_id' => ['required', 'integer', 'exists:condominium_profiles,id'],
            'report' => ['required', Rule::in(['base', 'debtors', 'captured', 'month-payments', 'overdue', 'boleo-maintenance', 'boleo-tags'])],
            'date' => ['required_if:report,captured', 'nullable', 'date_format:Y-m-d'],
        ]);
        $profile = CondominiumProfile::findOrFail($data['condominium_profile_id']);
        $type = $data['report'];
        $special = in_array($type, ['boleo-maintenance', 'boleo-tags'], true);
        abort_if($special && $this->normalizeCondominiumLookup((string) $profile->commercial_name) !== $this->normalizeCondominiumLookup('REAL DE BOLEO II'), 422, 'Este reporte corresponde a Real de Boleo II.');
        $current = now('America/Mexico_City')->startOfMonth();
        $cutoff = $this->accountStatusLetterCutoffPeriod();
        $units = Unit::where('condominium_profile_id', $profile->id)
            ->with(['payments', 'residentReceipts'])->orderBy('tower')->orderBy('unit_number')->get();

        if (in_array($type, ['captured', 'month-payments'], true)) {
            $headers = ['Condominio', 'Torre', 'Departamento', 'Propietario', 'Pago ID', 'Concepto', 'Monto', 'Fecha de pago', 'Fecha de aplicación', 'Fecha de captura', 'Método'];
            $rows = [];
            if ($type === 'month-payments') {
                $headers = ['Condominio', 'Torre', 'Departamento', 'Propietario', 'Total recibido en el mes', 'Número de pagos', 'Última fecha de pago'];
            }
            foreach ($units as $unit) {
                if ($type === 'month-payments') {
                    $payments = $unit->payments->filter(fn ($payment): bool => $payment->status === 'Completado'
                        && ($payment->payment_date ?? $payment->paid_at)?->format('Y-m') === $current->format('Y-m'));
                    if ($payments->isNotEmpty()) {
                        $rows[] = [$profile->commercial_name, $unit->tower, $unit->unit_number, $unit->owner_name,
                            round($payments->sum('amount'), 2), $payments->count(),
                            $payments->map(fn ($payment) => ($payment->payment_date ?? $payment->paid_at)?->toDateString())->max()];
                    }

                    continue;
                }
                foreach ($unit->payments as $payment) {
                    if ($payment->status !== 'Completado') {
                        continue;
                    }
                    $captureDate = $payment->getRawOriginal('created_at')
                        ? Carbon::parse($payment->getRawOriginal('created_at'), config('app.timezone', 'UTC'))->timezone('America/Mexico_City')->toDateString()
                        : null;
                    if ($captureDate !== $data['date']) {
                        continue;
                    }
                    $rows[] = [$profile->commercial_name, $unit->tower, $unit->unit_number, $unit->owner_name,
                        $payment->id, $payment->concept, $payment->amount, $payment->payment_date?->toDateString(),
                        $payment->paid_at?->toDateString(), $captureDate, $payment->payment_method];
                }
            }

            return $this->billingCsv($type, $profile->id, $headers, $rows);
        }

        $activeImport = $this->activeBillingBaseImport($profile);
        $accounts = ImportedResidentAccount::where('condominium_profile_id', $profile->id)
            ->when($activeImport, fn ($query) => $query->where('billing_base_import_id', $activeImport->id))
            ->orderByDesc('imported_at')->get();
        $used = [];
        $entries = [];
        foreach ($units as $unit) {
            $account = $accounts->firstWhere('unit_id', $unit->id) ?? $this->findImportedAccountForUnit($accounts, $unit);
            if ($account) {
                $used[] = $account->id;
            }
            $entries[] = [$unit, $account];
        }
        foreach ($accounts as $account) {
            if (! in_array($account->id, $used, true)) {
                $entries[] = [null, $account];
            }
        }
        $rows = [];
        foreach ($entries as [$unit, $account]) {
            $identity = [$profile->commercial_name, $unit?->tower ?? $account?->tower,
                $unit?->unit_number ?? $account?->unit_number, $unit?->owner_name ?? $account?->owner_name];
            $statement = $this->reportStatementRows($account, $unit, $profile);
            if ($type === 'base') {
                if ($statement->isEmpty()) {
                    $rows[] = [...$identity, '', 'Sin recibos registrados', '', '', '', 'Sin información', ''];
                }
                foreach ($statement as $row) {
                    $period = filled($row['period_year'] ?? null)
                        ? sprintf('%04d%s', $row['period_year'], filled($row['period_month'] ?? null) ? sprintf('-%02d', $row['period_month']) : '') : '';
                    $rows[] = [...$identity, $period, $row['name'], $row['exigible_raw'], $row['paid_raw'],
                        $this->statementRowPendingAmount($row), $row['status'],
                        $this->statementRowIsDueAsOf($row, $cutoff) ? 'Hasta mes anterior' : 'Mes corriente o futuro (fuera del adeudo vencido)'];
                }

                continue;
            }
            $due = $statement->filter(fn (array $row): bool => $this->statementRowIsDueAsOf($row, $cutoff));
            if ($type === 'overdue' || $special) {
                $due = $due->filter(fn (array $row): bool => ($row['receipt_type'] ?? '') === 'ordinaria'
                    && filled($row['period_month'] ?? null)
                    && (! $special || (int) $row['period_year'] >= ($type === 'boleo-tags' ? 2024 : 2023)));
            }
            $pending = $due->filter(fn (array $row): bool => $this->statementRowPendingAmount($row) > 0);
            $debt = round($pending->sum(fn (array $row): float => $this->statementRowPendingAmount($row)), 2);
            $months = $pending->filter(fn (array $row): bool => filled($row['period_month'] ?? null))->map(fn (array $row): string => sprintf('%04d-%02d', $row['period_year'] ?? 0, $row['period_month'] ?? 0))->unique()->count();
            $importedTag = collect($account?->raw_payload ?? [])->first(
                fn ($value, $key): bool => in_array($this->normalizePayloadHeader((string) $key), ['TAG VEHICULO', 'TAG VEHICULAR'], true)
            );
            $tag = trim((string) (filled($unit?->vehicle_tag) ? $unit->vehicle_tag : $importedTag));
            if (($type === 'debtors' && $debt <= 0) || ($type === 'overdue' && $months <= 2)
                || ($special && $debt < 2000) || ($type === 'boleo-tags' && $tag === '')) {
                continue;
            }
            $rows[] = [...$identity, $debt, $months, $cutoff->format('Y-m'), $special ? '1000.00' : '', $tag];
        }
        $headers = $type === 'base'
            ? ['Condominio', 'Torre', 'Departamento', 'Propietario', 'Periodo', 'Concepto', 'Exigible', 'Pagado', 'Adeudo', 'Estatus', 'Corte']
            : ['Condominio', 'Torre', 'Departamento', 'Propietario', 'Adeudo', 'Meses identificados con adeudo', 'Corte', 'Cuota base', 'TAG vehicular'];

        return $this->billingCsv($type, $profile->id, $headers, $rows);
    }

    private function billingCsv(string $type, int $profileId, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            foreach ([$headers, ...$rows] as $row) {
                // Spreadsheet applications must not execute user supplied cell formulas.
                $cells = array_map(static function ($value): string {
                    $value = (string) ($value ?? '');

                    return preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $value) ? "'".$value : $value;
                }, $row);
                fputcsv($stream, $cells, ',', '"', '');
            }
            fclose($stream);
        }, $type.'-condominio-'.$profileId.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
