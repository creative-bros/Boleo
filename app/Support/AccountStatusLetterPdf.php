<?php

namespace App\Support;

use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Throwable;

class AccountStatusLetterPdf extends Fpdi
{
    public function __construct(
        private readonly CondominiumProfile $profile,
        private readonly ImportedResidentAccount $account,
        private readonly ?string $templatePath = null,
        private readonly ?string $letterStatus = null,
    ) {
        parent::__construct('P', 'mm', 'A4');

        $this->SetTitle($this->encode('Carta de '.$this->statusLabel()));
        $this->SetAuthor($this->encode('Boleo'));
        $this->SetAutoPageBreak(true, 24);
        $this->SetMargins(24, 34, 24);
    }

    public function render(): string
    {
        $this->AddPage();
        $docxText = $this->templateTextFromDocx();

        if ($docxText !== null) {
            $this->SetY(34);
            $this->drawDocxTemplateText($docxText);
            $this->drawDebtBreakdownTableIfNeeded();

            return $this->Output('S');
        }

        $this->drawBackground();
        $this->SetY(42);

        $this->SetFont('Arial', '', 11);
        $this->SetTextColor(31, 41, 55);
        $this->Cell(0, 7, $this->encode('Ciudad de México a '.Carbon::now()->locale('es_MX')->translatedFormat('d \d\e F \d\e Y').'.'), 0, 1, 'R');
        $this->Ln(12);

        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(20, 56, 118);
        $this->MultiCell(0, 8, $this->encode('CARTA DE '.mb_strtoupper($this->statusLabel(), 'UTF-8')));
        $this->Ln(8);

        $this->SetFont('Arial', '', 11);
        $this->SetTextColor(31, 41, 55);
        $this->MultiCell(0, 6.8, $this->encode($this->bodyText()));
        $this->Ln(8);
        $this->drawDebtBreakdownTableIfNeeded();

        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 7, $this->encode('Datos de la cuenta'), 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->drawLine('Condominio', $this->profile->commercial_name ?: 'Sin configurar');
        $this->drawLine('Unidad', trim(($this->account->tower ?: '').' '.$this->account->unit_number));
        $this->drawLine('Residente', $this->account->owner_name);
        $this->drawLine('Saldo detectado', '$'.number_format((float) $this->account->total_debt, 2));
        $this->drawLine('Estatus', $this->statusLabel());

        if (filled($this->account->observations)) {
            $this->Ln(5);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 6, $this->encode('Observaciones'), 0, 1);
            $this->SetFont('Arial', '', 10);
            $this->MultiCell(0, 6, $this->encode((string) $this->account->observations));
        }

        $this->Ln(18);
        $this->Cell(0, 6, $this->encode('Atentamente,'), 0, 1, 'C');
        $this->Ln(12);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, $this->encode($this->profile->admin_name ?: 'Administrador Boleo'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $this->encode('Administración del condominio'), 0, 1, 'C');

        return $this->Output('S');
    }

    private function bodyText(): string
    {
        if ($this->statusKey() === 'adeudo') {
            return 'Por medio de la presente se hace constar que, de acuerdo con la base de cobranza cargada en el sistema, la unidad '
                .trim(($this->account->tower ?: '').' '.$this->account->unit_number)
                .' a nombre de '.$this->account->owner_name
                .' presenta un saldo pendiente de $'.number_format((float) $this->account->total_debt, 2)
                .' por concepto de cuotas, adeudos o movimientos registrados por el condominio.';
        }

        return 'Por medio de la presente se hace constar que, de acuerdo con la base de cobranza cargada en el sistema, la unidad '
            .trim(($this->account->tower ?: '').' '.$this->account->unit_number)
            .' a nombre de '.$this->account->owner_name
            .' no presenta adeudo registrado a la fecha de emisión de esta carta.';
    }

    private function drawLine(string $label, string $value): void
    {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(42, 6, $this->encode($label.':'), 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 6, $this->encode($value));
    }

    private function drawBackground(): void
    {
        $path = $this->templatePath && Storage::disk('public')->exists($this->templatePath)
            ? Storage::disk('public')->path($this->templatePath)
            : base_path('resources/pdf/hoja-membretada.pdf');

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            $path = base_path('resources/pdf/hoja-membretada.pdf');
        }

        if (! is_file($path)) {
            return;
        }

        try {
            $this->setSourceFile($path);
            $template = $this->importPage(1);
            $this->useTemplate($template, 0, 0, $this->GetPageWidth(), $this->GetPageHeight(), true);
        } catch (Throwable) {
            // La carta debe generarse aunque la plantilla no pueda leerse.
        }
    }

    private function drawDebtBreakdownTableIfNeeded(): void
    {
        if ($this->statusKey() !== 'adeudo') {
            return;
        }

        $rows = AccountStatusLetterDocx::debtRows($this->account);
        $subtotal = array_sum(array_column($rows, 'amount'));
        $currentTotal = (float) $this->account->total_debt;

        if ($currentTotal <= 0) {
            $rows = [[
                'concept' => 'Sin adeudo actualizado en sistema',
                'amount' => 0.0,
            ]];
            $subtotal = 0.0;
        } elseif ($rows === []) {
            $rows = [[
                'concept' => 'Saldo actualizado en sistema',
                'amount' => $currentTotal,
            ]];
            $subtotal = $currentTotal;
        }

        if ($this->GetY() > 230) {
            $this->AddPage();
            $this->SetY(34);
        }

        $this->Ln(4);
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(217, 234, 247);
        $this->SetDrawColor(143, 170, 220);
        $this->Cell(110, 8, $this->encode('Concepto / periodo'), 1, 0, 'L', true);
        $this->Cell(36, 8, $this->encode('Importe'), 1, 1, 'R', true);
        $this->SetFont('Arial', '', 9.5);

        foreach ($rows as $row) {
            if ($this->GetY() > 258) {
                $this->AddPage();
                $this->SetY(34);
            }

            $this->Cell(110, 7, $this->encode((string) $row['concept']), 1, 0, 'L');
            $this->Cell(36, 7, $this->encode(AccountStatusLetterDocx::money((float) $row['amount'])), 1, 1, 'R');
        }

        $adjustment = $currentTotal - $subtotal;

        if ($currentTotal > 0 && abs($adjustment) >= 0.01) {
            $this->Cell(110, 7, $this->encode('Ajuste por pagos o movimientos registrados en sistema'), 1, 0, 'L');
            $this->Cell(36, 7, $this->encode(AccountStatusLetterDocx::money($adjustment)), 1, 1, 'R');
        }

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(110, 8, $this->encode('TOTAL ADEUDO ACTUAL'), 1, 0, 'L', true);
        $this->Cell(36, 8, $this->encode(AccountStatusLetterDocx::money($currentTotal)), 1, 1, 'R', true);
        $this->Ln(5);
    }

    private function templateTextFromDocx(): ?string
    {
        if (! $this->templatePath || ! Storage::disk('public')->exists($this->templatePath)) {
            return null;
        }

        $path = Storage::disk('public')->path($this->templatePath);

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'docx') {
            return null;
        }

        try {
            return DocxTemplateText::render($path, $this->profile, $this->account, $this->statusKey());
        } catch (Throwable) {
            return null;
        }
    }

    private function drawDocxTemplateText(string $text): void
    {
        $paragraphs = preg_split("/\n{2,}/", $text) ?: [$text];

        foreach ($paragraphs as $paragraph) {
            $line = trim($paragraph);

            if ($line === '') {
                continue;
            }

            $isTitle = str_contains(mb_strtoupper($line, 'UTF-8'), 'CARTA');
            $isClosing = str_contains(mb_strtoupper($line, 'UTF-8'), 'ATENTAMENTE')
                || str_contains(mb_strtoupper($line, 'UTF-8'), 'ADMINISTRADOR');

            $this->SetFont('Arial', $isTitle || $isClosing ? 'B' : '', $isTitle ? 13 : 10.5);
            $this->SetTextColor($isTitle ? 20 : 31, $isTitle ? 56 : 41, $isTitle ? 118 : 55);
            $this->MultiCell(0, $isTitle ? 7.2 : 6.4, $this->encode($line), 0, $isTitle || $isClosing ? 'C' : 'J');
            $this->Ln($isTitle ? 4 : 2.5);
        }
    }

    private function statusLabel(): string
    {
        return $this->statusKey() === 'adeudo' ? 'adeudo' : 'no adeudo';
    }

    private function statusKey(): string
    {
        return $this->letterStatus === 'adeudo' ? 'adeudo' : 'no_adeudo';
    }

    private function encode(string $text): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $text) ?: $text;
    }
}
