<?php

namespace App\Support;

use App\Models\CondominiumProfile;
use App\Models\MaintenanceExpense;
use App\Models\Payment;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class ResidentMonthlyReportPdf extends Fpdi
{
    public function __construct(
        private readonly CondominiumProfile $profile,
        private readonly Unit $unit,
        private readonly Carbon $period,
        private readonly array $summary,
        private readonly Collection $expenses,
        private readonly Collection $payments,
    ) {
        parent::__construct('P', 'mm', 'A4');

        $this->SetTitle($this->encode('Reporte mensual del residente'));
        $this->SetAuthor($this->encode('Boleo'));
        $this->SetAutoPageBreak(true, 18);
        $this->SetMargins(18, 18, 18);
        $this->AliasNbPages();
    }

    public function render(): string
    {
        $this->addCoverPage();
        $this->addExpenseBreakdownPage();
        $this->addResidentSummaryPage();
        $this->addAttachmentPages();

        return $this->Output('S');
    }

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(120, 130, 150);
        $this->Cell(120, 5, $this->encode($this->profile->commercial_name.' | Reporte mensual '.$this->periodLabel()), 0, 0, 'L');
        $this->Cell(0, 5, $this->encode('Página '.$this->PageNo().'/{nb}'), 0, 0, 'R');
    }

    private function addCoverPage(): void
    {
        $this->AddPage();

        $this->drawHeader('INFORME MENSUAL', 'Resumen del periodo y situación de la unidad');

        $this->SetFont('Arial', '', 11);
        $this->SetTextColor(55, 65, 81);
        $this->Cell(0, 7, $this->encode('Ciudad de México, a '.$this->period->copy()->endOfMonth()->locale('es_MX')->translatedFormat('d \d\e F \d\e Y').'.'), 0, 1, 'R');
        $this->Ln(4);

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, $this->encode('REPORTE DIRIGIDO A:'), 0, 1);
        $this->SetFont('Arial', '', 11);
        $this->MultiCell(0, 6, $this->encode(
            $this->unit->owner_name."\n".
            trim($this->unit->tower.' Unidad '.$this->unit->unit_number)."\n".
            ($this->unit->owner_email ?: 'Sin correo vinculado')
        ));
        $this->Ln(2);

        $body = "Por este medio se presenta el reporte mensual del condominio {$this->profile->commercial_name}, correspondiente al periodo de {$this->periodLabel()}, con el detalle de gastos operativos, pagos registrados y situación actual de la unidad del residente.\n\n".
            'Este informe resume los movimientos del mes y mantiene visibles los conceptos más relevantes para la consulta del usuario: cuota mensual, pagos realizados, saldo pendiente y gastos generales del condominio.';
        $this->MultiCell(0, 6.5, $this->encode($body));
        $this->Ln(4);

        $this->drawSectionTitle('Resumen del periodo');
        $this->drawStatRow('Condominio', $this->profile->commercial_name ?: 'Sin configurar');
        $this->drawStatRow('Periodo', $this->periodLabel());
        $this->drawStatRow('Cuota total del residente', $this->money($this->summary['fee_amount']));
        $this->drawStatRow('Pagado en el periodo', $this->money($this->summary['paid_amount']));
        $this->drawStatRow('Saldo pendiente', $this->money($this->summary['pending_amount']));
        $this->drawStatRow('Estatus de la unidad', $this->summary['status_label']);
        $this->drawStatRow('Cuenta para depósito', $this->bankReference());

        $this->Ln(5);
        $this->drawSectionTitle('Actividades y conceptos más representativos');
        $concepts = $this->expenses->pluck('concept')
            ->filter()
            ->unique()
            ->take(6)
            ->values();

        if ($concepts->isEmpty()) {
            $concepts = collect([
                'Sin gastos cargados para este mes.',
            ]);
        }

        foreach ($concepts as $concept) {
            $this->SetFont('Arial', '', 11);
            $this->Cell(8, 6, '-', 0, 0);
            $this->MultiCell(0, 6, $this->encode((string) $concept));
        }

        $this->Ln(4);
        $this->SetFont('Arial', '', 10.5);
        $this->MultiCell(0, 6, $this->encode(
            'Se adjunta el detalle de gastos del mes y, cuando existen, los comprobantes o documentos cargados en el sistema para su consulta.'
        ));

        $this->Ln(14);
        $adminName = $this->profile->admin_name ?: 'Administrador Boleo';
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, $this->encode('Atentamente,'), 0, 1, 'C');
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, $this->encode($adminName), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $this->encode('Administrador profesional | '.$this->profile->commercial_name), 0, 1, 'C');
    }

    private function addExpenseBreakdownPage(): void
    {
        $this->AddPage();
        $this->drawHeader('DETALLE DE GASTOS DEL MES', 'Listado de egresos registrados en el periodo');

        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(220, 239, 255);
        $this->Cell(12, 8, '#', 1, 0, 'C', true);
        $this->Cell(24, 8, $this->encode('Fecha'), 1, 0, 'C', true);
        $this->Cell(60, 8, $this->encode('Concepto'), 1, 0, 'C', true);
        $this->Cell(38, 8, $this->encode('Proveedor'), 1, 0, 'C', true);
        $this->Cell(22, 8, $this->encode('Tipo'), 1, 0, 'C', true);
        $this->Cell(34, 8, $this->encode('Monto'), 1, 1, 'C', true);

        $rows = $this->expenses->isEmpty()
            ? collect([[
                'date' => '--',
                'concept' => 'Sin gastos registrados para este mes.',
                'provider' => '--',
                'group' => '--',
                'amount' => '--',
            ]])
            : $this->expenses->values()->map(fn (MaintenanceExpense $expense) => [
                'date' => optional($expense->spent_at)->format('d/m/Y') ?: '--',
                'concept' => $expense->concept,
                'provider' => $expense->provider?->name ?? 'Sin proveedor',
                'group' => $expense->expense_group === 'fixed' ? 'Fijo' : 'Variable',
                'amount' => $this->money((float) $expense->amount),
            ]);

        $this->SetFont('Arial', '', 9.5);
        foreach ($rows as $index => $row) {
            if ($this->GetY() > 255) {
                $this->AddPage();
                $this->drawHeader('DETALLE DE GASTOS DEL MES', 'Continuación');
                $this->SetFont('Arial', 'B', 10);
                $this->SetFillColor(220, 239, 255);
                $this->Cell(12, 8, '#', 1, 0, 'C', true);
                $this->Cell(24, 8, $this->encode('Fecha'), 1, 0, 'C', true);
                $this->Cell(60, 8, $this->encode('Concepto'), 1, 0, 'C', true);
                $this->Cell(38, 8, $this->encode('Proveedor'), 1, 0, 'C', true);
                $this->Cell(22, 8, $this->encode('Tipo'), 1, 0, 'C', true);
                $this->Cell(34, 8, $this->encode('Monto'), 1, 1, 'C', true);
                $this->SetFont('Arial', '', 9.5);
            }

            $y = $this->GetY();
            $conceptHeight = max(10, $this->lineHeight($row['concept'], 60, 5));
            $providerHeight = max(10, $this->lineHeight($row['provider'], 38, 5));
            $rowHeight = max($conceptHeight, $providerHeight, 10);

            $this->Cell(12, $rowHeight, (string) ($index + 1), 1, 0, 'C');
            $this->Cell(24, $rowHeight, $this->encode($row['date']), 1, 0, 'C');

            $x = $this->GetX();
            $this->MultiCell(60, 5, $this->encode($row['concept']), 1);
            $this->SetXY($x + 60, $y);

            $x = $this->GetX();
            $this->MultiCell(38, 5, $this->encode($row['provider']), 1);
            $this->SetXY($x + 38, $y);

            $this->Cell(22, $rowHeight, $this->encode($row['group']), 1, 0, 'C');
            $this->Cell(34, $rowHeight, $this->encode($row['amount']), 1, 1, 'R');
        }

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(156, 9, $this->encode('TOTAL DEL MES'), 1, 0, 'R', true);
        $this->Cell(34, 9, $this->encode($this->money((float) $this->expenses->sum('amount'))), 1, 1, 'R', true);
    }

    private function addResidentSummaryPage(): void
    {
        $this->AddPage();
        $this->drawHeader('SITUACIÓN DEL RESIDENTE', 'Datos de la unidad y pagos registrados');

        $this->drawSectionTitle('Ficha del residente');
        $this->drawStatRow('Nombre', $this->unit->owner_name);
        $this->drawStatRow('Correo vinculado', $this->unit->owner_email ?: 'Sin correo vinculado');
        $this->drawStatRow('Unidad', trim($this->unit->tower.' '.$this->unit->unit_number));
        $this->drawStatRow('Tipo', $this->unit->unit_type ?: 'Sin definir');
        $this->drawStatRow('Cuota ordinaria', $this->money((float) $this->unit->ordinary_fee));
        $this->drawStatRow('Cuota extraordinaria', $this->money((float) $this->unit->extraordinary_fee));
        $this->drawStatRow('Renta de cajones', $this->money((float) $this->unit->parking_rent));
        $this->drawStatRow('Renta de bodega', $this->money((float) $this->unit->storage_rent));

        $this->Ln(6);
        $this->drawSectionTitle('Pagos del periodo');

        if ($this->payments->isEmpty()) {
            $this->SetFont('Arial', '', 10.5);
            $this->MultiCell(0, 6, $this->encode('No hay pagos registrados para esta unidad durante el periodo seleccionado.'));
            return;
        }

        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(232, 240, 254);
        $this->Cell(30, 8, $this->encode('Fecha'), 1, 0, 'C', true);
        $this->Cell(90, 8, $this->encode('Concepto'), 1, 0, 'C', true);
        $this->Cell(30, 8, $this->encode('Monto'), 1, 0, 'C', true);
        $this->Cell(40, 8, $this->encode('Estatus'), 1, 1, 'C', true);

        $this->SetFont('Arial', '', 10);
        foreach ($this->payments as $payment) {
            if (! $payment instanceof Payment) {
                continue;
            }

            $height = max(8, $this->lineHeight($payment->concept, 90, 5));
            $y = $this->GetY();
            $this->Cell(30, $height, $this->encode(optional($payment->paid_at)->format('d/m/Y') ?: '--'), 1, 0, 'C');

            $x = $this->GetX();
            $this->MultiCell(90, 5, $this->encode($payment->concept), 1);
            $this->SetXY($x + 90, $y);

            $this->Cell(30, $height, $this->encode($this->money((float) $payment->amount)), 1, 0, 'R');
            $this->Cell(40, $height, $this->encode($payment->status), 1, 1, 'C');
        }
    }

    private function addAttachmentPages(): void
    {
        $attachments = $this->expenses
            ->filter(fn (MaintenanceExpense $expense) => filled($expense->document_path) && Storage::disk('local')->exists($expense->document_path))
            ->values();

        foreach ($attachments as $expense) {
            if (! $expense instanceof MaintenanceExpense) {
                continue;
            }

            $path = Storage::disk('local')->path($expense->document_path);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            $this->AddPage();
            $this->drawHeader('COMPROBANTE ANEXO', $expense->concept);
            $this->drawStatRow('Fecha', optional($expense->spent_at)->format('d/m/Y') ?: '--');
            $this->drawStatRow('Categoría', $expense->category);
            $this->drawStatRow('Proveedor', $expense->provider?->name ?? 'Sin proveedor');
            $this->drawStatRow('Monto', $this->money((float) $expense->amount));
            $this->drawStatRow('Observaciones', $expense->observations ?: 'Sin observaciones');

            if ($extension === 'pdf') {
                $pageCount = $this->setSourceFile($path);
                for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                    $template = $this->importPage($pageNumber);
                    $size = $this->getTemplateSize($template);
                    $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

                    $this->AddPage($orientation);
                    $pageWidth = $orientation === 'L' ? 297 : 210;
                    $pageHeight = $orientation === 'L' ? 210 : 297;
                    $this->useTemplate($template, 0, 0, $pageWidth, $pageHeight, true);
                }
                continue;
            }

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                [$widthPx, $heightPx] = getimagesize($path) ?: [1000, 1400];
                $maxWidth = 170;
                $maxHeight = 220;
                $ratio = min($maxWidth / $widthPx, $maxHeight / $heightPx);
                $renderWidth = $widthPx * $ratio;
                $renderHeight = $heightPx * $ratio;

                $this->Image($path, (210 - $renderWidth) / 2, $this->GetY() + 6, $renderWidth, $renderHeight);
            }
        }
    }

    private function drawHeader(string $title, string $subtitle): void
    {
        $this->SetFillColor(20, 56, 118);
        $this->Rect(0, 0, 210, 26, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 18);
        $this->SetXY(18, 10);
        $this->Cell(0, 6, $this->encode($title), 0, 1);

        $this->SetFont('Arial', '', 10);
        $this->SetX(18);
        $this->Cell(0, 5, $this->encode($subtitle), 0, 1);

        $this->Ln(8);
        $this->SetTextColor(31, 41, 55);
    }

    private function drawSectionTitle(string $title): void
    {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(20, 56, 118);
        $this->Cell(0, 7, $this->encode($title), 0, 1);
        $this->SetDrawColor(210, 219, 230);
        $this->Line(18, $this->GetY(), 192, $this->GetY());
        $this->Ln(4);
        $this->SetTextColor(31, 41, 55);
    }

    private function drawStatRow(string $label, string $value): void
    {
        $startX = $this->GetX();
        $startY = $this->GetY();
        $height = max(9, $this->lineHeight($value, 114, 5));

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(58, $height, $this->encode($label), 0, 0);

        $this->SetFont('Arial', '', 10);
        $x = $this->GetX();
        $this->MultiCell(114, 5, $this->encode($value), 0);
        $this->SetXY($startX, $startY + $height + 1);
    }

    private function bankReference(): string
    {
        $pieces = array_filter([
            $this->profile->bank,
            $this->profile->account_holder,
            $this->profile->account_number ? 'Cuenta '.$this->profile->account_number : null,
            $this->profile->clabe ? 'CLABE '.$this->profile->clabe : null,
        ]);

        return empty($pieces) ? 'Sin datos bancarios registrados.' : implode(' | ', $pieces);
    }

    private function periodLabel(): string
    {
        return $this->period->copy()->locale('es_MX')->translatedFormat('F Y');
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    private function encode(string $text): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $text) ?: $text;
    }

    private function lineHeight(string $text, float $width, float $lineHeight): float
    {
        $maxWidth = $width - 2;
        $words = preg_split('/\s+/', trim($text)) ?: [];

        if ($words === []) {
            return $lineHeight;
        }

        $lines = 1;
        $current = '';

        foreach ($words as $word) {
            $candidate = trim($current.' '.$word);
            if ($this->GetStringWidth($this->encode($candidate)) <= $maxWidth) {
                $current = $candidate;
                continue;
            }

            $lines++;
            $current = $word;
        }

        return $lines * $lineHeight;
    }
}
