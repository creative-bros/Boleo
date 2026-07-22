<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

trait ReportSignaturePdf
{
    protected ?string $reportSignaturePath = null;

    protected function setReportSignaturePath(?string $path): void
    {
        $this->reportSignaturePath = $path;
    }

    protected function drawCenteredReportSignature(float $y, float $width = 42.0): float
    {
        $height = $this->reportSignatureHeight($width);

        if ($height <= 0) {
            return 0.0;
        }

        $x = ($this->GetPageWidth() - $width) / 2;

        return $this->drawReportSignature($x, $y, $width);
    }

    protected function drawReportSignature(float $x, float $y, float $width = 42.0): float
    {
        $path = $this->reportSignaturePath();

        if ($path === null) {
            return 0.0;
        }

        $height = $this->reportSignatureHeight($width);

        try {
            $this->Image($path, $x, $y, $width, $height, 'PNG');
        } catch (Throwable) {
            return 0.0;
        }

        return $height;
    }

    protected function drawInlineReportSignature(float $width = 42.0, float $bottomY = 270.0, string $alignment = 'center'): float
    {
        $height = $this->reportSignatureHeight($width);

        if ($height <= 0) {
            $this->Ln(12);

            return 12.0;
        }

        if ($this->GetY() + $height + 14 > $bottomY) {
            $this->AddPage();
            $this->SetY(42);
        }

        $y = $this->GetY() + 1.5;
        $drawnHeight = $alignment === 'left'
            ? $this->drawReportSignature($this->GetX(), $y, $width)
            : $this->drawCenteredReportSignature($y, $width);
        $usedHeight = $drawnHeight > 0 ? $drawnHeight + 3 : 12.0;
        $this->SetY($y + $usedHeight);

        return $usedHeight;
    }

    protected function reportSignatureHeight(float $width): float
    {
        $path = $this->reportSignaturePath();

        if ($path === null) {
            return 0.0;
        }

        $size = getimagesize($path);

        if (! is_array($size) || empty($size[0]) || empty($size[1])) {
            return 0.0;
        }

        return $width * ((float) $size[1] / (float) $size[0]);
    }

    private function reportSignaturePath(): ?string
    {
        if ($this->reportSignaturePath) {
            if (! str_starts_with($this->reportSignaturePath, DIRECTORY_SEPARATOR) && Storage::disk('public')->exists($this->reportSignaturePath)) {
                return Storage::disk('public')->path($this->reportSignaturePath);
            }

            if (is_file($this->reportSignaturePath)) {
                return $this->reportSignaturePath;
            }
        }

        $path = base_path('resources/img/firma-reportes.png');

        return is_file($path) ? $path : null;
    }
}
