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
        $customText = trim((string) $account->custom_letter_text);

        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            $textNodes = $xpath->query('.//w:t', $paragraph);

            if (! $textNodes || $textNodes->length === 0) {
                continue;
            }

            $originalText = '';

            foreach ($textNodes as $node) {
                $originalText .= $node->nodeValue;
            }

            $isCustomBodyParagraph = $customText !== ''
                && (str_contains($originalText, 'Hago constar que el departamento')
                    || str_contains($originalText, 'Por medio de la presente, le informo'));

            $filledText = $isCustomBodyParagraph ? $customText : self::fillText($originalText, $values);

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
        $tableWidth->setAttributeNS($namespace, 'w:w', '9100');
        $tableWidth->setAttributeNS($namespace, 'w:type', 'dxa');
        $tableProperties->appendChild($tableWidth);
        $tableJustification = self::w($document, 'jc');
        $tableJustification->setAttributeNS($namespace, 'w:val', 'center');
        $tableProperties->appendChild($tableJustification);

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

        $table->appendChild(self::tableRow($document, ['Año', 'Adeudo'], true));
        $table->appendChild(self::tableRow($document, [
            'DEPT',
            trim((string) $account->unit_number) !== '' ? trim((string) $account->unit_number) : 'Sin dato',
        ], true));
        $table->appendChild(self::tableRow($document, [
            'NOMBRE',
            trim((string) $account->owner_name) !== '' ? trim((string) $account->owner_name) : 'Sin dato',
        ], true));

        $rows = self::debtRows($account);
        $subtotal = array_sum(array_column($rows, 'amount'));
        $currentTotal = (float) $account->total_debt;

        if ($currentTotal <= 0) {
            $rows = [[
                'concept' => 'Sin adeudo actualizado en sistema',
                'amount' => 0.0,
                'amount_label' => 'Sin adeudo',
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
                $row['amount_label'] ?? self::money($row['amount']),
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
        return collect(self::annualDebtRows($account))
            ->map(fn (array $row): array => [
                'concept' => $row['concept'],
                'amount' => $row['amount'],
                'amount_label' => $row['amount_label'] ?? self::money($row['amount']),
            ])
            ->values()
            ->all();
    }

    private static function annualDebtRows(ImportedResidentAccount $account): array
    {
        $years = [];
        $annualAmounts = [];
        $otherRows = [];

        foreach (ResidentAccountStatement::rows($account) as $row) {
            $amount = max((float) ($row['debt_raw'] ?? 0), 0);
            $year = self::statementRowYear($row);

            if ($year !== null) {
                $years[$year] ??= [];
                $years[$year][] = $amount;

                continue;
            }

            if ($amount > 0 && ! self::isTotalDebtRow((string) ($row['name'] ?? ''))) {
                $otherRows[] = [
                    'concept' => (string) ($row['name'] ?? 'Saldo actualizado en sistema'),
                    'amount' => $amount,
                    'amount_label' => self::money($amount),
                    'sort_key' => 999999,
                ];
            }
        }

        foreach (self::annualPayloadValues($account) as $year => $value) {
            $annualAmounts[(int) $year] = max(self::moneyValue($value), 0);
            $years[(int) $year] ??= [];
        }

        if ($years === []) {
            return $otherRows;
        }

        $yearKeys = array_keys($years);
        $firstYear = min($yearKeys);
        $lastYear = max($yearKeys);

        for ($year = $firstYear; $year <= $lastYear; $year++) {
            $years[$year] ??= [];
        }

        ksort($years);

        $annualRows = [];

        foreach (array_keys($years) as $year) {
            $amount = array_key_exists($year, $annualAmounts)
                ? $annualAmounts[$year]
                : array_sum($years[$year]);

            $annualRows[] = [
                'concept' => $year === 2017 ? 'ADEUDO AL 2017' : 'TOTAL '.$year,
                'amount' => max($amount, 0),
                'amount_label' => $amount > 0 ? self::money($amount) : 'Sin adeudo',
                'sort_key' => $year * 100,
            ];
        }

        return array_merge($annualRows, $otherRows);
    }

    private static function statementRowYear(array $row): ?int
    {
        if (isset($row['period_year']) && $row['period_year'] !== null && $row['period_year'] !== '') {
            return (int) $row['period_year'];
        }

        if (preg_match('/(20\d{2})/', (string) ($row['name'] ?? ''), $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private static function isTotalDebtRow(string $name): bool
    {
        $normalized = mb_strtoupper(trim($name), 'UTF-8');

        return str_contains($normalized, 'TOTAL')
            && (str_contains($normalized, 'ADEUDO') || str_contains($normalized, 'SALDO'));
    }

    private static function annualPayloadValues(ImportedResidentAccount $account): array
    {
        $values = [];

        foreach (($account->year_statuses ?? []) as $year => $value) {
            if (preg_match('/^20\d{2}$/', (string) $year) === 1 && filled($value)) {
                $values[(int) $year] = $value;
            }
        }

        foreach (($account->raw_payload ?? []) as $header => $value) {
            if (preg_match('/^20\d{2}$/', (string) $header) === 1 && filled($value)) {
                $values[(int) $header] = $value;
            }
        }

        return $values;
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
        $filled = preg_replace(
            '/(enviados por Whatssap hasta )el\s+\d{1,2}\s+de\s+[A-ZÁÉÍÓÚÑa-záéíóúñ]+\s+(?:del?\s+)?\d{4}/iu',
            '$1la fecha de la elaboración es esta carta.',
            $filled
        ) ?: $filled;
        $filled = str_replace('Real de Boleo II', $values['condominio'], $filled);

        if ($values['direccion_configurada']) {
            $filled = preg_replace('/ubicado en .*?Ciudad de M[ée]xico\./u', 'ubicado en '.$values['direccion'].'.', $filled) ?: $filled;
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

        return $filled;
    }

    private static function values(CondominiumProfile $profile, ImportedResidentAccount $account, string $letterStatus): array
    {
        $date = Carbon::now('America/Mexico_City')->locale('es_MX');
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
