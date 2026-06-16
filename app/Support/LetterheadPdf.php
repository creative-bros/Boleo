<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;
use Throwable;

class LetterheadPdf extends Fpdi
{
    public function AddPage($orientation = '', $size = '', $rotation = 0)
    {
        parent::AddPage($orientation, $size, $rotation);
        $this->drawLetterheadBackground();
    }

    protected function encodeText(string $text): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $text) ?: $text;
    }

    private function drawLetterheadBackground(): void
    {
        $path = base_path('resources/pdf/hoja-membretada.pdf');

        if (! is_file($path)) {
            return;
        }

        try {
            $this->setSourceFile($path);
            $template = $this->importPage(1);
            $this->useTemplate($template, 0, 0, $this->GetPageWidth(), $this->GetPageHeight(), true);
        } catch (Throwable) {
            // If the stationery cannot be read, keep the report generation available.
        }
    }
}
