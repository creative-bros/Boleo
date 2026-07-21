<?php

namespace App\Support;

use Illuminate\Support\Str;

class SimpleLetterheadPdf extends LetterheadPdf
{
    private const FIRST_PAGE_SIGNATURE_Y = 222.0;
    private const FIRST_PAGE_SIGNATURE_WIDTH = 34.0;

    public function __construct(
        private readonly array $lines,
        private readonly string $title = 'Reporte Boleo',
        private readonly bool $includeReportSignature = false,
        ?string $reportSignaturePath = null,
    )
    {
        parent::__construct('P', 'mm', 'A4');
        $this->setReportSignaturePath($reportSignaturePath);

        $this->SetTitle($this->encodeText($this->title));
        $this->SetAuthor($this->encodeText('Boleo'));
        $this->SetAutoPageBreak(true, 22);
        $this->SetMargins(24, 34, 24);
    }

    public function render(): string
    {
        $this->AddPage();

        if ($this->includeReportSignature) {
            $this->drawCenteredReportSignature(self::FIRST_PAGE_SIGNATURE_Y, self::FIRST_PAGE_SIGNATURE_WIDTH);
        }

        $this->SetY(42);
        $this->SetFont('Arial', '', 11);
        $this->SetTextColor(31, 41, 55);

        foreach ($this->lines as $index => $line) {
            if ($this->GetY() + 8 > $this->currentPageContentLimit()) {
                $this->AddPage();
                $this->SetY(42);
            }

            $text = trim((string) $line);

            if ($index === 0) {
                $this->SetFont('Arial', 'B', 15);
                $this->SetTextColor(20, 56, 118);
                $this->MultiCell(0, 7, $this->encodeText(Str::limit($text, 105, '')));
                $this->Ln(4);
                $this->SetFont('Arial', '', 11);
                $this->SetTextColor(31, 41, 55);
                continue;
            }

            if ($text === '') {
                $this->Ln(4);
                continue;
            }

            $this->MultiCell(0, 6.4, $this->encodeText(Str::limit($text, 130, '')));
            $this->Ln(1);
        }

        return $this->Output('S');
    }

    private function currentPageContentLimit(): float
    {
        if ($this->includeReportSignature && $this->PageNo() === 1) {
            return self::FIRST_PAGE_SIGNATURE_Y - 16;
        }

        return 258.0;
    }
}
