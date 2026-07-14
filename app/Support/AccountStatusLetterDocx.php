<?php

namespace App\Support;

use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

class AccountStatusLetterDocx
{
    public static function render(
        string $templatePath,
        CondominiumProfile $profile,
        ImportedResidentAccount $account,
        string $letterStatus,
    ): string {
        $source = new ZipArchive();

        if ($source->open($templatePath) !== true) {
            throw new RuntimeException('No fue posible abrir la plantilla DOCX.');
        }

        $targetPath = tempnam(sys_get_temp_dir(), 'boleo-letter-');

        if ($targetPath === false) {
            $source->close();

            throw new RuntimeException('No fue posible preparar la carta DOCX.');
        }

        $target = new ZipArchive();

        if ($target->open($targetPath, ZipArchive::OVERWRITE) !== true) {
            $source->close();
            @unlink($targetPath);

            throw new RuntimeException('No fue posible generar la carta DOCX.');
        }

        for ($index = 0; $index < $source->numFiles; $index++) {
            $name = $source->getNameIndex($index);

            if (! $name) {
                continue;
            }

            $contents = $source->getFromIndex($index);

            if ($contents === false) {
                continue;
            }

            if ($name === 'word/document.xml') {
                $contents = self::fillDocumentXml($contents, $profile, $account, $letterStatus);
            }

            $target->addFromString($name, $contents);
        }

        $source->close();
        $target->close();

        $contents = file_get_contents($targetPath);
        @unlink($targetPath);

        if ($contents === false) {
            throw new RuntimeException('No fue posible leer la carta DOCX generada.');
        }

        return $contents;
    }

    public static function convertToPdf(string $docxContents): ?string
    {
        $binary = self::officeBinary();

        if ($binary === null) {
            return null;
        }

        $directory = sys_get_temp_dir().'/boleo-letter-'.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return null;
        }

        $docxPath = $directory.'/letter.docx';
        $pdfPath = $directory.'/letter.pdf';
        file_put_contents($docxPath, $docxContents);

        try {
            $process = new Process([
                $binary,
                '--headless',
                '--convert-to',
                'pdf',
                '--outdir',
                $directory,
                $docxPath,
            ], base_path(), null, null, 90);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($pdfPath)) {
                return null;
            }

            return file_get_contents($pdfPath) ?: null;
        } finally {
            @unlink($docxPath);
            @unlink($pdfPath);
            @rmdir($directory);
        }
    }

    private static function officeBinary(): ?string
    {
        $finder = new ExecutableFinder();
        $binary = $finder->find('soffice') ?? $finder->find('libreoffice');

        if ($binary) {
            return $binary;
        }

        $macBinary = '/Applications/LibreOffice.app/Contents/MacOS/soffice';

        return is_file($macBinary) ? $macBinary : null;
    }

    private static function fillDocumentXml(
        string $xml,
        CondominiumProfile $profile,
        ImportedResidentAccount $account,
        string $letterStatus,
    ): string {
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;

        if (! $document->loadXML($xml)) {
            return $xml;
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $values = self::values($profile, $account, $letterStatus);

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            $textNodes = $xpath->query('.//w:t', $paragraph);

            if (! $textNodes || $textNodes->length === 0) {
                continue;
            }

            $originalText = '';

            foreach ($textNodes as $node) {
                $originalText .= $node->nodeValue;
            }

            $filledText = self::fillText($originalText, $values);

            if ($filledText === $originalText) {
                continue;
            }

            foreach ($textNodes as $nodeIndex => $node) {
                $node->nodeValue = $nodeIndex === 0 ? $filledText : '';

                if ($nodeIndex === 0) {
                    $node->setAttribute('xml:space', 'preserve');
                }
            }
        }

        if ($values['estatus'] === 'adeudo') {
            self::replaceDebtTable($document, $xpath, $account);
        }

        return $document->saveXML() ?: $xml;
    }

    private static function replaceDebtTable(\DOMDocument $document, \DOMXPath $xpath, ImportedResidentAccount $account): void
    {
        $table = self::debtTable($document, $account);
        $drawingParagraph = $xpath->query('//w:p[.//w:drawing]')->item(0);

        if ($drawingParagraph instanceof \DOMNode && $drawingParagraph->parentNode) {
            $drawingParagraph->parentNode->replaceChild($table, $drawingParagraph);

            return;
        }

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            $text = '';

            foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $node) {
                $text .= $node->nodeValue;
            }

            if (str_contains($text, 'Por medio de la presente') && $paragraph->parentNode) {
                if ($paragraph->nextSibling) {
                    $paragraph->parentNode->insertBefore($table, $paragraph->nextSibling);
                } else {
                    $paragraph->parentNode->appendChild($table);
                }

                return;
            }
        }

        $body = $xpath->query('//w:body')->item(0);

        if ($body instanceof \DOMNode) {
            $body->appendChild($table);
        }
    }

    private static function debtTable(\DOMDocument $document, ImportedResidentAccount $account): \DOMElement
    {
        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $table = $document->createElementNS($namespace, 'w:tbl');
        $tableProperties = self::w($document, 'tblPr');
        $tableWidth = self::w($document, 'tblW');
        $tableWidth->setAttributeNS($namespace, 'w:w', '0');
        $tableWidth->setAttributeNS($namespace, 'w:type', 'auto');
        $tableProperties->appendChild($tableWidth);

        $borders = self::w($document, 'tblBorders');
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $borderName) {
            $border = self::w($document, $borderName);
            $border->setAttributeNS($namespace, 'w:val', 'single');
            $border->setAttributeNS($namespace, 'w:sz', '8');
            $border->setAttributeNS($namespace, 'w:space', '0');
            $border->setAttributeNS($namespace, 'w:color', '8FAADC');
            $borders->appendChild($border);
        }
        $tableProperties->appendChild($borders);
        $table->appendChild($tableProperties);

        $table->appendChild(self::tableRow($document, ['Concepto / periodo', 'Importe'], true));

        $rows = self::debtRows($account);
        $subtotal = array_sum(array_column($rows, 'amount'));
        $currentTotal = (float) $account->total_debt;

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

        foreach ($rows as $row) {
            $table->appendChild(self::tableRow($document, [
                $row['concept'],
                self::money($row['amount']),
            ]));
        }

        $adjustment = $currentTotal - $subtotal;

        if ($currentTotal > 0 && abs($adjustment) >= 0.01) {
            $table->appendChild(self::tableRow($document, [
                'Ajuste por pagos o movimientos registrados en sistema',
                self::money($adjustment),
            ]));
        }

        $table->appendChild(self::tableRow($document, [
            'TOTAL ADEUDO ACTUAL',
            self::money($currentTotal),
        ], true));

        return $table;
    }

    private static function tableRow(\DOMDocument $document, array $cells, bool $bold = false): \DOMElement
    {
        $row = self::w($document, 'tr');

        foreach ($cells as $index => $cellText) {
            $row->appendChild(self::tableCell($document, (string) $cellText, $index === 1 ? 'right' : 'left', $bold));
        }

        return $row;
    }

    private static function tableCell(\DOMDocument $document, string $text, string $alignment = 'left', bool $bold = false): \DOMElement
    {
        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $cell = self::w($document, 'tc');
        $cellProperties = self::w($document, 'tcPr');
        $cellWidth = self::w($document, 'tcW');
        $cellWidth->setAttributeNS($namespace, 'w:w', $alignment === 'right' ? '2200' : '6900');
        $cellWidth->setAttributeNS($namespace, 'w:type', 'dxa');
        $cellProperties->appendChild($cellWidth);

        if ($bold) {
            $shading = self::w($document, 'shd');
            $shading->setAttributeNS($namespace, 'w:fill', 'D9EAF7');
            $cellProperties->appendChild($shading);
        }

        $cell->appendChild($cellProperties);
        $paragraph = self::w($document, 'p');
        $paragraphProperties = self::w($document, 'pPr');
        $justification = self::w($document, 'jc');
        $justification->setAttributeNS($namespace, 'w:val', $alignment);
        $paragraphProperties->appendChild($justification);
        $paragraph->appendChild($paragraphProperties);
        $run = self::w($document, 'r');
        $runProperties = self::w($document, 'rPr');

        if ($bold) {
            $runProperties->appendChild(self::w($document, 'b'));
        }

        $fontSize = self::w($document, 'sz');
        $fontSize->setAttributeNS($namespace, 'w:val', '22');
        $runProperties->appendChild($fontSize);
        $run->appendChild($runProperties);
        $textNode = self::w($document, 't');
        $textNode->setAttribute('xml:space', 'preserve');
        $textNode->appendChild($document->createTextNode($text));
        $run->appendChild($textNode);
        $paragraph->appendChild($run);
        $cell->appendChild($paragraph);

        return $cell;
    }

    private static function w(\DOMDocument $document, string $name): \DOMElement
    {
        return $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:'.$name);
    }

    public static function debtRows(ImportedResidentAccount $account): array
    {
        $groups = [];

        foreach (($account->raw_payload ?? []) as $header => $value) {
            $amount = self::moneyValue($value);

            if (abs($amount) < 0.01) {
                continue;
            }

            $concept = self::debtConcept((string) $header);

            if ($concept === null) {
                continue;
            }

            $groups[$concept] = ($groups[$concept] ?? 0) + $amount;
        }

        return collect($groups)
            ->map(fn (float $amount, string $concept): array => [
                'concept' => $concept,
                'amount' => $amount,
            ])
            ->values()
            ->all();
    }

    private static function debtConcept(string $header): ?string
    {
        $normalized = mb_strtoupper(trim($header), 'UTF-8');

        if (
            $normalized === ''
            || str_contains($normalized, 'TOTAL')
            || str_contains($normalized, 'DEPT')
            || str_contains($normalized, 'DEPTO')
            || str_contains($normalized, 'TAG')
            || str_contains($normalized, 'NOMBRE')
            || str_contains($normalized, 'TORRE')
            || str_contains($normalized, 'ESTATUS')
            || str_contains($normalized, 'OBSERV')
            || str_contains($normalized, 'LUZ AREA')
            || str_starts_with($normalized, 'COLUMNA_')
            || preg_match('/^20\d{2}$/', $normalized) === 1
        ) {
            return null;
        }

        if (preg_match('/^(20\d{2})-\d{2}$/', $normalized, $matches) === 1) {
            return 'Cuotas '.$matches[1];
        }

        if (str_contains($normalized, 'ADEUDO AL')) {
            return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
        }

        if (str_contains($normalized, 'CUOTA EXTRA')) {
            return 'Cuota extra';
        }

        if (str_contains($normalized, 'ADEUDO') || str_contains($normalized, 'SALDO')) {
            return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
        }

        return null;
    }

    private static function moneyValue(mixed $value): float
    {
        return (float) str_replace([',', '$', ' '], '', (string) $value);
    }

    public static function money(float $amount): string
    {
        $prefix = $amount < 0 ? '-$' : '$';

        return $prefix.number_format(abs($amount), 2);
    }

    private static function fillText(string $text, array $values): string
    {
        $filled = str_replace([
            '{{fecha}}',
            '{{ fecha }}',
            '{{fecha_larga}}',
            '{{ fecha_larga }}',
            '{{departamento}}',
            '{{ departamento }}',
            '{{unidad}}',
            '{{ unidad }}',
            '{{residente}}',
            '{{ residente }}',
            '{{propietario}}',
            '{{ propietario }}',
            '{{condominio}}',
            '{{ condominio }}',
            '{{direccion}}',
            '{{ direccion }}',
            '{{saldo}}',
            '{{ saldo }}',
            '{{administrador}}',
            '{{ administrador }}',
            '{{telefono_administrador}}',
            '{{ telefono_administrador }}',
        ], [
            $values['fecha_larga'],
            $values['fecha_larga'],
            $values['fecha_larga'],
            $values['fecha_larga'],
            $values['departamento'],
            $values['departamento'],
            $values['unidad'],
            $values['unidad'],
            $values['residente'],
            $values['residente'],
            $values['residente'],
            $values['residente'],
            $values['condominio'],
            $values['condominio'],
            $values['direccion'],
            $values['direccion'],
            $values['saldo'],
            $values['saldo'],
            $values['administrador'],
            $values['administrador'],
            $values['telefono_administrador'],
            $values['telefono_administrador'],
        ], $text);

        $filled = preg_replace(
            '/Ciudad de M[ée]xico\s+\d{1,2}\s+de\s+[A-ZÁÉÍÓÚÑa-záéíóúñ]+\s+del?\s+\d{2,4}\.?/u',
            $values['fecha_larga'].'.',
            $filled
        ) ?: $filled;
        $filled = preg_replace('/Departamento:\s*\S+/u', 'Departamento: '.$values['departamento'], $filled) ?: $filled;
        $filled = preg_replace('/departamento\s+\S+/iu', 'departamento '.$values['departamento'], $filled, 1) ?: $filled;
        $filled = preg_replace('/hasta\s+[A-ZÁÉÍÓÚÑa-záéíóúñ]+\s+del?\s+\d{2,4}/u', 'hasta '.$values['mes_anio'], $filled) ?: $filled;
        $filled = str_replace('Real de Boleo II', $values['condominio'], $filled);

        if ($values['direccion_configurada']) {
            $filled = preg_replace('/ubicado en .*?Ciudad de M[ée]xico\./u', 'ubicado en '.$values['direccion'].'.', $filled) ?: $filled;
        }

        if ($values['residente'] !== '') {
            $filled = preg_replace('/Estimado\s+C?ondómin@/iu', 'Estimado/a '.$values['residente'], $filled) ?: $filled;
        }

        if ($values['administrador_configurado']) {
            $filled = str_replace([
                'Rodolfo Chiquillo Quevedo',
                'Lic. Rodolfo Chiquillo Quevedo.',
            ], [
                $values['administrador'],
                $values['administrador'].'.',
            ], $filled);
        }

        if ($values['telefono_administrador'] !== '') {
            $filled = preg_replace('/Celular:\s*[\d\s]+/u', 'Celular: '.$values['telefono_administrador'], $filled) ?: $filled;
        }

        if (
            $values['estatus'] === 'adeudo'
            && str_contains($filled, 'Por medio de la presente')
            && ! str_contains($filled, $values['saldo'])
        ) {
            $filled .= ' Saldo registrado en Boleo: '.$values['saldo'].'.';
        }

        if (
            $values['estatus'] === 'no_adeudo'
            && str_contains($filled, 'Hago constar')
            && $values['residente'] !== ''
            && ! str_contains($filled, $values['residente'])
        ) {
            $filled = preg_replace(
                '/(departamento\s+\S+)/iu',
                '$1 a nombre de '.$values['residente'],
                $filled,
                1
            ) ?: $filled;
        }

        return $filled;
    }

    private static function values(CondominiumProfile $profile, ImportedResidentAccount $account, string $letterStatus): array
    {
        $date = Carbon::now()->locale('es_MX');
        $month = mb_strtoupper($date->translatedFormat('F'), 'UTF-8');
        $department = trim((string) $account->unit_number);
        $unit = trim(collect([$account->tower, $account->unit_number])->filter()->implode(' '));
        $address = $profile->address ?: 'Boleo número 54 Colonia Felipe Pescador, Alcaldía Cuauhtémoc C.P. 06280 en la Ciudad de México';
        $admin = $profile->admin_name ?: 'Rodolfo Chiquillo Quevedo';

        return [
            'fecha_larga' => 'Ciudad de México '.$date->format('d').' de '.$month.' del '.$date->format('Y'),
            'mes_anio' => $month.' del '.$date->format('Y'),
            'departamento' => $department,
            'unidad' => $unit !== '' ? $unit : $department,
            'residente' => $account->owner_name,
            'condominio' => $profile->commercial_name ?: 'Real de Boleo II',
            'direccion' => $address,
            'direccion_configurada' => filled($profile->address),
            'saldo' => '$'.number_format((float) $account->total_debt, 2),
            'estatus' => $letterStatus === 'adeudo' ? 'adeudo' : 'no_adeudo',
            'administrador' => $admin,
            'administrador_configurado' => filled($profile->admin_name),
            'telefono_administrador' => $profile->admin_phone ?: '',
        ];
    }
}
