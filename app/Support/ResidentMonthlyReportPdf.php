<?php

namespace App\Support;

use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;
use App\Models\MaintenanceExpense;
use App\Models\Payment;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ResidentMonthlyReportPdf extends LetterheadPdf
{
    public function __construct(
        private readonly CondominiumProfile $profile,
        private readonly Unit $unit,
        private readonly Carbon $period,
        private readonly array $summary,
        private readonly Collection $expenses,
        private readonly Collection $payments,
        private readonly ?ImportedResidentAccount $account = null,
        private readonly array $statementRows = [],
    ) {
        parent::__construct('P', 'mm', 'A4');
        $this->setReportSignaturePath($this->profile->report_signature_path);

        $this->SetTitle($this->encode('Reporte mensual del residente'));
        $this->SetAuthor($this->encode('Boleo'));
        $this->SetAutoPageBreak(true, 18);
        $this->SetMargins(18, 18, 18);
        $this->AliasNbPages();
    }

    public function render(): string
    {
        $this->addLetterPage();
        $this->addResidentStatementPage();
        $this->addExpenseDetailPage();

        return $this->Output('S');
    }

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(120, 130, 150);
        $this->Cell(120, 5, $this->encode($this->profile->commercial_name.' | '.$this->periodLabel()), 0, 0, 'L');
        $this->Cell(0, 5, $this->encode('Pagina '.$this->PageNo().'/{nb}'), 0, 0, 'R');
    }

    private function addLetterPage(): void
    {
        $this->AddPage();
        $this->drawHeader('INFORME MENSUAL', 'Carta de presentacion del periodo');

        $this->SetFont('Arial', '', 11);
        $this->SetTextColor(31, 41, 55);
        $this->Cell(0, 7, $this->encode('CDMX a '.$this->period->copy()->endOfMonth()->locale('es_MX')->translatedFormat('d \d\e F \d\e Y').'.'), 0, 1, 'R');
        $this->Ln(10);

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, $this->encode('COMITE DE VIGILANCIA'), 0, 1);
        $this->Ln(8);
        $this->Cell(0, 7, $this->encode('P R E S E N T E.'), 0, 1);
        $this->Ln(6);

        $this->SetFont('Arial', '', 11);
        $body = 'Por este medio presento al H. Comité de Vigilancia del condominio '.$this->profile->commercial_name
            .', ubicado en '.($this->profile->address ?: 'domicilio pendiente de configurar')
            .', el reporte mensual correspondiente a la unidad '.$this->unitLabel()
            .' del residente '.$this->residentName().', del periodo que comprende del '
            .$this->period->copy()->startOfMonth()->format('d').' al '.$this->period->copy()->endOfMonth()->format('d')
            .' '.$this->periodLabel().'.';
        $this->MultiCell(0, 6.5, $this->encode($body));
        $this->Ln(5);

        $this->drawSectionTitle('Resumen del residente');
        $this->drawBullet('Residente: '.$this->residentName());
        $this->drawBullet('Correo vinculado: '.$this->residentEmail());
        $this->drawBullet('Cuota total del mes: '.$this->money($this->summary['fee_amount']));
        $this->drawBullet('Pagado en el periodo: '.$this->money($this->summary['paid_amount']));
        $this->drawBullet('Saldo pendiente: '.$this->money($this->summary['pending_amount']));
        $this->drawBullet('Estatus: '.$this->summary['status_label']);

        if ($this->account) {
            $this->drawBullet('Base Excel: '.($this->account->billingBaseImport?->original_name ?: 'Registro importado en Boleo'));
        }

        $this->drawBullet('Cuenta para depósito: '.$this->bankReference());

        $this->Ln(5);
        $this->drawSectionTitle('Actividades mas representativas');

        $activities = $this->expenses
            ->map(function (MaintenanceExpense $expense): string {
                $prefix = $expense->expense_group === 'fixed' ? 'PAGO DE' : 'MANTENIMIENTO DE';

                return $prefix.' '.$expense->concept;
            })
            ->filter()
            ->unique()
            ->take(6)
            ->values();

        if ($activities->isEmpty()) {
            $activities = collect(['SIN GASTOS REGISTRADOS EN EL PERIODO.']);
        }

        foreach ($activities as $activity) {
            $this->drawBullet(mb_strtoupper((string) $activity, 'UTF-8'));
        }

        if ($this->payments->isNotEmpty()) {
            $this->Ln(5);
            $this->drawSectionTitle('Pagos registrados del residente');
            foreach ($this->payments->take(4) as $payment) {
                if (! $payment instanceof Payment) {
                    continue;
                }

                $line = (optional($payment->paid_at)->format('d/m/Y') ?: '--')
                    .' | '.$payment->concept
                    .' | '.$this->money((float) $payment->amount)
                    .' | '.$payment->status;

                $this->drawBullet($line);
            }
        }

        $this->Ln(8);
        $this->MultiCell(0, 6.5, $this->encode('Se adjunta la lista detallada de los gastos realizados del periodo para consulta y seguimiento del residente.'));

        $this->Ln(18);
        $adminName = $this->profile->admin_name ?: 'Administrador Boleo';
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, $this->encode('Atentamente,'), 0, 1, 'C');
        $this->drawInlineReportSignature(42);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, $this->encode($adminName), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $this->encode('Administrador profesional'), 0, 1, 'C');
    }

    private function addResidentStatementPage(): void
    {
        $this->AddPage();
        $this->drawHeader('BASE DE DATOS DEL RESIDENTE', 'Estado importado desde Excel', 56);

        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(31, 41, 55);
        $this->Cell(0, 6, $this->encode('Residente: '.$this->residentName()), 0, 1);
        $this->Cell(0, 6, $this->encode('Unidad: '.$this->unitLabel()), 0, 1);
        $this->Ln(4);

        $this->drawResidentStatementHeader();

        if ($this->statementRows === []) {
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 9, $this->encode('Sin tabla importada para este residente.'), 1, 1, 'C');

            return;
        }

        $this->SetFont('Arial', '', 8.5);

        foreach ($this->statementRows as $row) {
            $this->ensureStatementSpace(9);
            $this->Cell(44, 8, $this->encode((string) ($row['name'] ?? '--')), 1, 0, 'L');
            $this->Cell(33, 8, $this->encode((string) ($row['status'] ?? '--')), 1, 0, 'C');
            $this->Cell(34, 8, $this->encode((string) ($row['exigible'] ?? '$0.00')), 1, 0, 'R');
            $this->Cell(31, 8, $this->encode((string) ($row['paid'] ?? '$0.00')), 1, 0, 'R');
            $this->Cell(32, 8, $this->encode((string) ($row['debt'] ?? '-')), 1, 1, 'R');
        }
    }

    private function addExpenseDetailPage(): void
    {
        $this->AddPage();
        $this->drawHeader('DETALLE DE GASTOS REALIZADOS', 'Resumen financiero del mes', 56);
        $fixedTotal = (float) $this->expenses->where('expense_group', 'fixed')->sum('amount');
        $variableTotal = (float) $this->expenses->where('expense_group', 'variable')->sum('amount');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(
            0,
            7,
            $this->encode(
                'DETALLE DE GASTOS REALIZADOS DEL '.$this->period->copy()->startOfMonth()->format('d')
                .' AL '.$this->period->copy()->endOfMonth()->format('d').' '.$this->periodLabel()
            ),
            0,
            1,
            'C'
        );
        $this->Ln(5);
        $this->drawExpenseTableHeader();

        $rows = $this->expenses->isEmpty()
            ? collect([[
                'date' => '--',
                'concept' => 'Sin gastos registrados para este mes.',
                'group' => '--',
                'amount' => '--',
                'notes' => 'Sin observaciones',
            ]])
            : $this->expenses->values()->map(fn (MaintenanceExpense $expense) => [
                'date' => optional($expense->spent_at)->format('d/m/Y') ?: '--',
                'concept' => $expense->concept,
                'group' => $expense->expense_group === 'fixed' ? 'Fijo' : 'No fijo',
                'amount' => $this->money((float) $expense->amount),
                'notes' => $expense->observations ?: ($expense->provider?->name ?? 'Sin observaciones'),
            ]);

        $this->SetFont('Arial', '', 9);
        $widths = $this->expenseTableWidths();

        foreach ($rows as $index => $row) {
            $conceptHeight = max(10, $this->lineHeight($row['concept'], $widths['concept'] - 3, 4.5) + 3);
            $notesHeight = max(10, $this->lineHeight($row['notes'], $widths['notes'] - 3, 4.5) + 3);
            $rowHeight = max($conceptHeight, $notesHeight, 10);
            $this->ensureDetailSpace($rowHeight + 2);

            $y = $this->GetY();

            $this->Cell($widths['index'], $rowHeight, (string) ($index + 1), 1, 0, 'C');
            $this->Cell($widths['date'], $rowHeight, $this->encode($row['date']), 1, 0, 'C');
            $this->drawWrappedTableCell($widths['concept'], $rowHeight, $row['concept']);
            $this->Cell($widths['type'], $rowHeight, $this->encode($row['group']), 1, 0, 'C');
            $this->Cell($widths['amount'], $rowHeight, $this->encode($row['amount']), 1, 0, 'R');
            $this->drawWrappedTableCell($widths['notes'], $rowHeight, $row['notes']);
            $this->SetXY(18, $y + $rowHeight);
        }

        $this->ensureDetailSpace(52, false);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($widths['index'] + $widths['date'] + $widths['concept'] + $widths['type'], 9, $this->encode('TOTAL'), 1, 0, 'R');
        $this->Cell($widths['amount'], 9, $this->encode($this->money((float) $this->expenses->sum('amount'))), 1, 0, 'R');
        $this->Cell($widths['notes'], 9, $this->encode('Reporte mensual'), 1, 1, 'C');

        $this->Ln(8);
        $this->SetFont('Arial', '', 9.5);
        $this->Cell(0, 6, $this->encode('Sumatoria de gastos fijos: '.$this->money($fixedTotal)), 0, 1);
        $this->Cell(0, 6, $this->encode('Sumatoria de gastos no fijos: '.$this->money($variableTotal)), 0, 1);

        $this->Ln(14);
        $this->SetFont('Arial', '', 10.5);
        $this->MultiCell(
            0,
            6,
            $this->encode(
                'Este reporte resume el mantenimiento, los gastos y el monto correspondiente al residente '.$this->residentName()
                .' para el periodo '.$this->periodLabel().'.'
            )
        );
    }

    private function drawHeader(string $title, string $subtitle, float $startY = 28): void
    {
        $this->SetTextColor(20, 56, 118);
        $this->SetFont('Arial', 'B', 17);
        $this->SetXY(18, $startY);
        $this->Cell(0, 6, $this->encode($title), 0, 1);

        $this->SetFont('Arial', '', 9.5);
        $this->SetX(18);
        $this->Cell(0, 5, $this->encode($subtitle), 0, 1);
        $this->Ln(8);
        $this->SetTextColor(31, 41, 55);
    }

    private function drawSectionTitle(string $title): void
    {
        $this->SetFont('Arial', 'B', 11.5);
        $this->SetTextColor(20, 56, 118);
        $this->Cell(0, 6.5, $this->encode($title), 0, 1);
        $this->SetDrawColor(210, 219, 230);
        $this->Line(18, $this->GetY(), 192, $this->GetY());
        $this->Ln(4);
        $this->SetTextColor(31, 41, 55);
    }

    private function drawBullet(string $text): void
    {
        $this->SetFont('Arial', '', 10.5);
        $this->Cell(8, 6, '-', 0, 0);
        $this->MultiCell(0, 6, $this->encode($text));
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

    private function residentName(): string
    {
        return $this->account?->owner_name ?: $this->unit->owner_name;
    }

    private function residentEmail(): string
    {
        $email = collect($this->account?->raw_payload ?? [])
            ->first(function (mixed $value, string $key): bool {
                $normalizedKey = preg_replace('/\s+/', ' ', mb_strtoupper(trim($key), 'UTF-8')) ?: '';

                return filled($value)
                    && (str_contains($normalizedKey, 'CORREO') || str_contains($normalizedKey, 'EMAIL'));
            });

        return filled($email) ? (string) $email : ($this->unit->owner_email ?: 'Sin correo vinculado');
    }

    private function unitLabel(): string
    {
        return trim(collect([$this->account?->tower ?: $this->unit->tower, $this->account?->unit_number ?: $this->unit->unit_number])
            ->filter()
            ->implode(' ')) ?: trim($this->unit->tower.' '.$this->unit->unit_number);
    }

    private function expenseTableWidths(): array
    {
        return [
            'index' => 10,
            'date' => 24,
            'concept' => 60,
            'type' => 20,
            'amount' => 25,
            'notes' => 35,
        ];
    }

    private function drawExpenseTableHeader(): void
    {
        $widths = $this->expenseTableWidths();

        $this->SetFont('Arial', 'B', 8.5);
        $this->SetFillColor(213, 234, 181);
        $this->SetDrawColor(210, 219, 230);
        $this->Cell($widths['index'], 8, '#', 1, 0, 'C', true);
        $this->Cell($widths['date'], 8, $this->encode('Fecha'), 1, 0, 'C', true);
        $this->Cell($widths['concept'], 8, $this->encode('Motivo'), 1, 0, 'C', true);
        $this->Cell($widths['type'], 8, $this->encode('Tipo'), 1, 0, 'C', true);
        $this->Cell($widths['amount'], 8, $this->encode('Cantidad'), 1, 0, 'C', true);
        $this->Cell($widths['notes'], 8, $this->encode('Observaciones'), 1, 1, 'C', true);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(31, 41, 55);
    }

    private function drawResidentStatementHeader(): void
    {
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetFillColor(184, 215, 236);
        $this->SetTextColor(31, 41, 55);
        $this->SetDrawColor(70, 90, 110);
        $this->Cell(44, 8, $this->encode('Nombre'), 1, 0, 'C', true);
        $this->Cell(33, 8, $this->encode('ESTATUS'), 1, 0, 'C', true);
        $this->SetTextColor(190, 35, 55);
        $this->Cell(34, 8, $this->encode('EXIGIBLE'), 1, 0, 'C', true);
        $this->SetTextColor(31, 41, 55);
        $this->Cell(31, 8, $this->encode('PAGADO'), 1, 0, 'C', true);
        $this->Cell(32, 8, $this->encode('ADEUDO'), 1, 1, 'C', true);
        $this->SetTextColor(31, 41, 55);
        $this->SetFont('Arial', '', 8.5);
    }

    private function ensureStatementSpace(float $height): void
    {
        if ($this->GetY() + $height <= 260) {
            return;
        }

        $this->AddPage();
        $this->drawHeader('BASE DE DATOS DEL RESIDENTE', 'Continuacion del estado importado', 56);
        $this->drawResidentStatementHeader();
    }

    private function drawWrappedTableCell(float $width, float $height, string $text, string $align = 'L'): void
    {
        $x = $this->GetX();
        $y = $this->GetY();

        $this->Rect($x, $y, $width, $height);
        $this->SetXY($x + 1.5, $y + 1.5);
        $this->MultiCell($width - 3, 4.5, $this->encode($text), 0, $align);
        $this->SetXY($x + $width, $y);
    }

    private function ensureDetailSpace(float $height, bool $repeatTableHeader = true): void
    {
        if ($this->GetY() + $height <= 252) {
            return;
        }

        $this->AddPage();
        $this->drawHeader('DETALLE DE GASTOS REALIZADOS', 'Continuacion del resumen financiero', 56);

        if ($repeatTableHeader) {
            $this->Ln(5);
            $this->drawExpenseTableHeader();
        }
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
