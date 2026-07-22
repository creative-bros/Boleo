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
    use ReportSignaturePdf;

    public function __construct(
        private readonly CondominiumProfile $profile,
        private readonly ImportedResidentAccount $account,
        private readonly ?string $templatePath = null,
        private readonly ?string $letterStatus = null,
        private readonly string $paymentFrequency = 'mensual',
    ) {
        parent::__construct('P', 'mm', 'A4');
        $this->setReportSignaturePath($this->profile->report_signature_path);

        $this->SetTitle($this->encode('Carta de '.$this->statusLabel()));
        $this->SetAuthor($this->encode('Boleo'));
        $this->SetAutoPageBreak(true, 24);
        $this->SetMargins(24, 34, 24);
    }

    public function render(): string
    {
        $this->AddPage();
        $docxLayout = $this->templateLayoutFromDocx();

        if ($docxLayout !== null) {
            $this->renderDocxLayout($docxLayout);

            return $this->Output('S');
        }

        $this->drawBackground();
        $this->SetY(42);

        $this->SetFont('Arial', '', 11);
        $this->SetTextColor(31, 41, 55);
        $this->Cell(0, 7, $this->encode('Ciudad de México a '.Carbon::now('America/Mexico_City')->locale('es_MX')->translatedFormat('d \d\e F \d\e Y').'.'), 0, 1, 'R');
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

        if ($this->statusKey() === 'adeudo') {
            $this->Ln(6);
            $this->SetFont('Arial', 'I', 8.5);
            $this->MultiCell(0, 5, $this->encode(
                'Nota: Para la elaboración del presente documento se consideraron los estados de cuenta, '
                .'los pagos entregados físicamente y/o enviados por Whatssap hasta la fecha de la elaboración es esta carta.'
            ));
        }

        $this->Ln(18);
        $this->Cell(70, 6, $this->encode('Atentamente,'), 0, 1, 'L');
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(70, 6, $this->encode($this->profile->admin_name ?: 'Administrador Boleo'), 0, 1, 'L');
        $this->drawInlineReportSignatureLeft(42);
        $this->SetFont('Arial', '', 10);
        $this->Cell(70, 6, $this->encode('Administración del condominio'), 0, 1, 'L');

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

        if ($this->paymentFrequency === 'anual') {
            return 'Por medio de la presente se hace constar que, de acuerdo con la base de cobranza cargada en el sistema, la unidad '
                .trim(($this->account->tower ?: '').' '.$this->account->unit_number)
                .' a nombre de '.$this->account->owner_name
                .' no presenta adeudo registrado, toda vez que su cuota de mantenimiento fue cubierta de forma anual, '
                .'quedando saldada hasta el 31 de diciembre de '.Carbon::now('America/Mexico_City')->year.'.';
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
        $path = $this->templateFullPath() ?? base_path('resources/pdf/hoja-membretada.pdf');

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

        if ($this->GetY() > 236) {
            $this->AddPage();
            $this->SetY(34);
        }

        $conceptWidth = 110;
        $amountWidth = 36;
        $tableWidth = $conceptWidth + $amountWidth;
        $tableX = ($this->GetPageWidth() - $tableWidth) / 2;
        $headerHeight = 6.0;
        $rowHeight = 5.2;
        $totalHeight = 6.2;

        $this->Ln(3);
        $this->SetFont('Arial', 'B', 8.4);
        $this->SetFillColor(217, 234, 247);
        $this->SetDrawColor(143, 170, 220);
        $this->SetX($tableX);
        $this->Cell($conceptWidth, $headerHeight, $this->encode('Concepto / periodo'), 1, 0, 'L', true);
        $this->Cell($amountWidth, $headerHeight, $this->encode('Importe'), 1, 1, 'R', true);
        $this->SetFont('Arial', '', 8.2);

        foreach ($rows as $row) {
            if ($this->GetY() > 274) {
                $this->AddPage();
                $this->SetY(34);
            }

            $this->SetX($tableX);
            $this->Cell($conceptWidth, $rowHeight, $this->encode((string) $row['concept']), 1, 0, 'L');
            $this->Cell($amountWidth, $rowHeight, $this->encode(AccountStatusLetterDocx::money((float) $row['amount'])), 1, 1, 'R');
        }

        $adjustment = $currentTotal - $subtotal;

        if ($currentTotal > 0 && abs($adjustment) >= 0.01) {
            $this->SetX($tableX);
            $this->Cell($conceptWidth, $rowHeight, $this->encode('Ajuste por pagos o movimientos registrados en sistema'), 1, 0, 'L');
            $this->Cell($amountWidth, $rowHeight, $this->encode(AccountStatusLetterDocx::money($adjustment)), 1, 1, 'R');
        }

        $this->SetFont('Arial', 'B', 8.5);
        $this->SetX($tableX);
        $this->Cell($conceptWidth, $totalHeight, $this->encode('TOTAL ADEUDO ACTUAL'), 1, 0, 'L', true);
        $this->Cell($amountWidth, $totalHeight, $this->encode(AccountStatusLetterDocx::money($currentTotal)), 1, 1, 'R', true);
        $this->Ln(4);
    }

    /**
     * @return array{
     *     margins: array{top: float, right: float, bottom: float, left: float},
     *     paragraphs: array<int, array{text: string, alignment: string, drawing_height_mm: float, is_blank: bool}>
     * }|null
     */
    private function templateLayoutFromDocx(): ?array
    {
        $path = $this->templateFullPath();

        if (! $path || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'docx') {
            return null;
        }

        try {
            return DocxTemplateText::layoutFromContents(
                AccountStatusLetterDocx::render($path, $this->profile, $this->account, $this->statusKey())
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array{
     *     margins: array{top: float, right: float, bottom: float, left: float},
     *     paragraphs: array<int, array{text: string, alignment: string, drawing_height_mm: float, is_blank: bool}>
     * }  $layout
     */
    private function renderDocxLayout(array $layout): void
    {
        $margins = $layout['margins'];
        $isDebtLetter = $this->statusKey() === 'adeudo';

        $this->SetMargins($margins['left'], $margins['top'], $margins['right']);
        $this->SetAutoPageBreak(true, $isDebtLetter ? 8 : $margins['bottom']);
        $this->SetY($margins['top']);
        $this->SetTextColor(31, 41, 55);

        $tableDrawn = false;
        $signatureDrawn = false;
        $drawSignatureAfterNextLine = false;

        foreach ($layout['paragraphs'] as $paragraph) {
            $text = trim((string) $paragraph['text']);
            $alignment = match ($paragraph['alignment']) {
                'right' => 'R',
                'center' => 'C',
                'both', 'justify' => 'J',
                default => 'L',
            };

            if ($text === '') {
                $this->Ln($paragraph['drawing_height_mm'] > 0
                    ? max($paragraph['drawing_height_mm'], 4.5)
                    : 4.2);
                continue;
            }

            if (! $tableDrawn && $this->isDebtTableAnchor($text)) {
                $this->drawDebtBreakdownTableIfNeeded();
                $tableDrawn = true;
            }

            $isTitle = str_contains(mb_strtoupper($text, 'UTF-8'), 'CARTA');
            $fontSize = $isTitle ? ($isDebtLetter ? 11.5 : 13) : ($isDebtLetter ? 9.5 : 12);
            $lineHeight = $isTitle ? ($isDebtLetter ? 5.8 : 7.0) : ($isDebtLetter ? 4.7 : 5.8);

            $this->SetFont('Arial', $isTitle ? 'B' : '', $fontSize);
            $this->MultiCell(0, $lineHeight, $this->encode($text), 0, $alignment);

            if (! $signatureDrawn && $this->isAtentamenteLine($text)) {
                $drawSignatureAfterNextLine = true;
                continue;
            }

            if (! $signatureDrawn && $drawSignatureAfterNextLine) {
                $this->drawInlineReportSignatureLeft($isDebtLetter ? 26 : 42, $isDebtLetter ? 286 : 270);
                $signatureDrawn = true;
                $drawSignatureAfterNextLine = false;
            }
        }

        if (! $tableDrawn) {
            $this->drawDebtBreakdownTableIfNeeded();
        }

        if (! $signatureDrawn) {
            $this->Ln($isDebtLetter ? 2 : 4);
            $this->drawInlineReportSignatureLeft($isDebtLetter ? 26 : 42, $isDebtLetter ? 286 : 270);
        }
    }

    private function isDebtTableAnchor(string $text): bool
    {
        return $this->statusKey() === 'adeudo'
            && str_starts_with(mb_strtoupper($text, 'UTF-8'), 'EN CASO');
    }

    private function isAtentamenteLine(string $text): bool
    {
        $normalized = trim(mb_strtoupper($text, 'UTF-8'), " \t\n\r\0\x0B.,");

        return $normalized === 'ATENTAMENTE';
    }

    private function templateFullPath(): ?string
    {
        if (! $this->templatePath) {
            return null;
        }

        if (! str_starts_with($this->templatePath, DIRECTORY_SEPARATOR) && Storage::disk('public')->exists($this->templatePath)) {
            return Storage::disk('public')->path($this->templatePath);
        }

        return is_file($this->templatePath) ? $this->templatePath : null;
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
            $isClosingLine = $this->isTemplateClosingLine($line);
            $this->SetFont('Arial', $isTitle ? 'B' : '', $isTitle ? 13 : 10.5);
            $this->SetTextColor($isTitle ? 20 : 31, $isTitle ? 56 : 41, $isTitle ? 118 : 55);
            $this->MultiCell(0, $isTitle ? 7.2 : 6.4, $this->encode($line), 0, $isTitle || $isClosingLine ? 'C' : 'J');
            $this->Ln($isTitle ? 4 : 2.5);
        }
    }

    private function isTemplateClosingLine(string $text): bool
    {
        $normalized = trim(mb_strtoupper($text, 'UTF-8'), " \t\n\r\0\x0B.,");

        return in_array($normalized, [
            'ATENTAMENTE',
            'ADMINISTRADOR PROFESIONAL',
        ], true);
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
