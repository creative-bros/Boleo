<?php

namespace App\Support;

use App\Models\CondominiumProfile;
use App\Models\MaintenanceExpense;
use App\Models\Payment;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        $this->addLetterPage();
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
        $body = 'Por este medio presento al H. Comite de Vigilancia del condominio '.$this->profile->commercial_name
            .', ubicado en '.($this->profile->address ?: 'domicilio pendiente de configurar')
            .', el reporte mensual correspondiente a la unidad '.trim($this->unit->tower.' '.$this->unit->unit_number)
            .' del residente '.$this->unit->owner_name.', del periodo que comprende del '
            .$this->period->copy()->startOfMonth()->format('d').' al '.$this->period->copy()->endOfMonth()->format('d')
            .' '.$this->periodLabel().'.';
        $this->MultiCell(0, 6.5, $this->encode($body));
        $this->Ln(5);

        $this->drawSectionTitle('Resumen del residente');
        $this->drawBullet('Residente: '.$this->unit->owner_name);
        $this->drawBullet('Correo vinculado: '.($this->unit->owner_email ?: 'Sin correo vinculado'));
        $this->drawBullet('Cuota total del mes: '.$this->money($this->summary['fee_amount']));
        $this->drawBullet('Pagado en el periodo: '.$this->money($this->summary['paid_amount']));
        $this->drawBullet('Saldo pendiente: '.$this->money($this->summary['pending_amount']));
        $this->drawBullet('Estatus: '.$this->summary['status_label']);
        $this->drawBullet('Cuenta para deposito: '.$this->bankReference());

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
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, $this->encode($adminName), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $this->encode('Administrador profesional'), 0, 1, 'C');
    }

    private function addExpenseDetailPage(): void
    {
        $this->AddPage();
        $this->drawHeader('DETALLE DE GASTOS REALIZADOS', 'Resumen financiero del mes');
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
        $this->Ln(3);

        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(213, 234, 181);
        $this->Cell(12, 8, '#', 1, 0, 'C', true);
        $this->Cell(24, 8, $this->encode('Fecha'), 1, 0, 'C', true);
        $this->Cell(66, 8, $this->encode('Motivo'), 1, 0, 'C', true);
        $this->Cell(20, 8, $this->encode('Tipo'), 1, 0, 'C', true);
        $this->Cell(28, 8, $this->encode('Cantidad'), 1, 0, 'C', true);
        $this->Cell(40, 8, $this->encode('Observaciones'), 1, 1, 'C', true);

        $rows = $this->expenses->isEmpty()
            ? collect([[
                'date' => '--',
                'concept' => 'Sin gastos registrados para este mes.',
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
        foreach ($rows as $index => $row) {
            $y = $this->GetY();
            $conceptHeight = max(10, $this->lineHeight($row['concept'], 66, 4.5));
            $notesHeight = max(10, $this->lineHeight($row['notes'], 40, 4.5));
            $rowHeight = max($conceptHeight, $notesHeight, 10);

            $this->Cell(12, $rowHeight, (string) ($index + 1), 1, 0, 'C');
            $this->Cell(24, $rowHeight, $this->encode($row['date']), 1, 0, 'C');

            $x = $this->GetX();
            $this->MultiCell(66, 4.5, $this->encode($row['concept']), 1);
            $this->SetXY($x + 66, $y);

            $this->Cell(20, $rowHeight, $this->encode($row['group']), 1, 0, 'C');

            $this->Cell(28, $rowHeight, $this->encode($row['amount']), 1, 0, 'R');

            $x = $this->GetX();
            $this->MultiCell(40, 4.5, $this->encode($row['notes']), 1);
            $this->SetXY($x + 40, $y + $rowHeight);
        }

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(122, 9, $this->encode('TOTAL'), 1, 0, 'R');
        $this->Cell(28, 9, $this->encode($this->money((float) $this->expenses->sum('amount'))), 1, 0, 'R');
        $this->Cell(40, 9, $this->encode('Reporte mensual del residente'), 1, 1, 'C');

        $this->Ln(5);
        $this->SetFont('Arial', '', 9.5);
        $this->Cell(0, 6, $this->encode('Sumatoria de gastos fijos: '.$this->money($fixedTotal)), 0, 1);
        $this->Cell(0, 6, $this->encode('Sumatoria de gastos no fijos: '.$this->money($variableTotal)), 0, 1);

        $this->Ln(12);
        $this->SetFont('Arial', '', 10.5);
        $this->MultiCell(
            0,
            6,
            $this->encode(
                'Este reporte resume el mantenimiento, los gastos y el monto correspondiente al residente '.$this->unit->owner_name
                .' para el periodo '.$this->periodLabel().'.'
            )
        );
    }

    private function drawHeader(string $title, string $subtitle): void
    {
        $this->SetFillColor(20, 56, 118);
        $this->Rect(0, 0, 210, 24, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 17);
        $this->SetXY(18, 9);
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
