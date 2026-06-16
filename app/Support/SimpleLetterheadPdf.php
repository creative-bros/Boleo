<?php

namespace App\Support;

use Illuminate\Support\Str;

class SimpleLetterheadPdf extends LetterheadPdf
{
    public function __construct(private readonly array $lines, private readonly string $title = 'Reporte Boleo')
    {
        parent::__construct('P', 'mm', 'A4');

        $this->SetTitle($this->encodeText($this->title));
        $this->SetAuthor($this->encodeText('Boleo'));
        $this->SetAutoPageBreak(true, 22);
        $this->SetMargins(24, 34, 24);
    }

    public function render(): string
    {
        $this->AddPage();
        $this->SetY(42);
        $this->SetFont('Arial', '', 11);
        $this->SetTextColor(31, 41, 55);

        foreach ($this->lines as $index => $line) {
            if ($this->GetY() > 258) {
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
}
