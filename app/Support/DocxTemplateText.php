<?php

namespace App\Support;

use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;
use Illuminate\Support\Carbon;
use RuntimeException;
use DOMDocument;
use DOMXPath;
use SimpleXMLElement;
use ZipArchive;

class DocxTemplateText
{
    public static function render(string $path, CondominiumProfile $profile, ImportedResidentAccount $account, ?string $letterStatus = null): string
    {
        $text = self::extract($path);
        $values = self::values($profile, $account);

        foreach ($values as $key => $value) {
            $text = str_replace([
                '{{'.$key.'}}',
                '{{ '.$key.' }}',
                '['.$key.']',
            ], $value, $text);
        }

        $text = preg_replace(
            '/Ciudad de M[ée]xico\s+\d{1,2}\s+de\s+[A-ZÁÉÍÓÚÑa-záéíóúñ]+\s+del?\s+\d{4}\.?/u',
            $values['fecha_larga'].'.',
            $text
        ) ?: $text;
        $text = preg_replace('/Departamento:\s*\S+/u', 'Departamento: '.$values['departamento'], $text) ?: $text;
        $text = preg_replace('/departamento\s+\S+/iu', 'departamento '.$values['departamento'], $text, 1) ?: $text;
        $text = preg_replace('/hasta\s+[A-ZÁÉÍÓÚÑa-záéíóúñ]+\s+del?\s+\d{2,4}/u', 'hasta '.$values['mes_anio'], $text) ?: $text;
        $text = str_replace('Real de Boleo II', $values['condominio'], $text);

        if ($values['direccion_configurada']) {
            $text = preg_replace(
                '/ubicado en .*?Ciudad de M[ée]xico\./us',
                'ubicado en '.$values['direccion'].'.',
                $text
            ) ?: $text;
        }

        if ($values['administrador_configurado']) {
            $text = str_replace([
                'Rodolfo Chiquillo Quevedo',
                'Lic. Rodolfo Chiquillo Quevedo.',
            ], [
                $values['administrador'],
                $values['administrador'].'.',
            ], $text);
        }

        if ($values['telefono_administrador'] !== '') {
            $text = preg_replace('/Celular:\s*[\d\s]+/u', 'Celular: '.$values['telefono_administrador'], $text) ?: $text;
        }

        $status = $letterStatus === 'adeudo' ? 'adeudo' : ($letterStatus === 'no_adeudo' ? 'no_adeudo' : $account->status);

        if (
            $status === 'no_adeudo'
            && str_contains($text, 'Hago constar')
            && $values['residente'] !== ''
            && ! str_contains($text, $values['residente'])
        ) {
            $text = preg_replace(
                '/(departamento\s+\S+)/iu',
                '$1 a nombre de '.$values['residente'],
                $text,
                1
            ) ?: $text;
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?: $text);
    }

    public static function extract(string $path): string
    {
        $layout = self::layout($path);

        return trim(implode("\n\n", array_map(
            fn (array $paragraph): string => $paragraph['text'],
            array_values(array_filter($layout['paragraphs'], fn (array $paragraph): bool => trim($paragraph['text']) !== ''))
        )));
    }

    /**
     * @return array{
     *     margins: array{top: float, right: float, bottom: float, left: float},
     *     paragraphs: array<int, array{text: string, alignment: string, drawing_height_mm: float, is_blank: bool}>
     * }
     */
    public static function layout(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('No fue posible abrir la plantilla DOCX.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('La plantilla DOCX no contiene document.xml.');
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;

        if (! $document->loadXML($xml)) {
            throw new RuntimeException('La plantilla DOCX no pudo interpretarse.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');

        $margins = [
            'top' => 24.0,
            'right' => 24.0,
            'bottom' => 24.0,
            'left' => 24.0,
        ];

        $section = $xpath->query('//w:sectPr')->item(0);

        if ($section instanceof \DOMElement) {
            $margins['top'] = self::twipsToMm((int) $section->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pgMar')->item(0)?->attributes?->getNamedItemNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'top')?->nodeValue ?? 0);
            $margins['right'] = self::twipsToMm((int) $section->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pgMar')->item(0)?->attributes?->getNamedItemNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'right')?->nodeValue ?? 0);
            $margins['bottom'] = self::twipsToMm((int) $section->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pgMar')->item(0)?->attributes?->getNamedItemNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'bottom')?->nodeValue ?? 0);
            $margins['left'] = self::twipsToMm((int) $section->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pgMar')->item(0)?->attributes?->getNamedItemNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'left')?->nodeValue ?? 0);
        }

        $paragraphs = [];

        foreach ($xpath->query('//w:body/w:p') ?: [] as $paragraph) {
            $parts = [];

            foreach ($xpath->query('.//w:t | .//w:tab', $paragraph) ?: [] as $node) {
                $parts[] = $node->localName === 'tab' ? "\t" : (string) $node->nodeValue;
            }

            $text = trim(implode('', $parts));
            $alignment = 'left';
            $jcNode = $xpath->query('./w:pPr/w:jc', $paragraph)->item(0);

            if ($jcNode instanceof \DOMElement) {
                $alignment = (string) $jcNode->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val') ?: 'left';
            }

            $drawingHeightMm = 0.0;
            foreach ($xpath->query('.//w:drawing//wp:extent', $paragraph) ?: [] as $extent) {
                if (! $extent instanceof \DOMElement) {
                    continue;
                }

                $drawingHeightMm = max(
                    $drawingHeightMm,
                    self::emuToMm((int) $extent->getAttribute('cy'))
                );
            }

            $paragraphs[] = [
                'text' => $text,
                'alignment' => $alignment,
                'drawing_height_mm' => $drawingHeightMm,
                'is_blank' => $text === '' && $drawingHeightMm <= 0,
            ];
        }

        return [
            'margins' => $margins,
            'paragraphs' => $paragraphs,
        ];
    }

    /**
     * @param  string  $contents  Binary DOCX contents.
     * @return array{
     *     margins: array{top: float, right: float, bottom: float, left: float},
     *     paragraphs: array<int, array{text: string, alignment: string, drawing_height_mm: float, is_blank: bool}>
     * }
     */
    public static function layoutFromContents(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'boleo-docx-');

        if ($path === false) {
            throw new RuntimeException('No fue posible preparar la carta DOCX.');
        }

        file_put_contents($path, $contents);

        try {
            return self::layout($path);
        } finally {
            @unlink($path);
        }
    }

    private static function values(CondominiumProfile $profile, ImportedResidentAccount $account): array
    {
        $date = Carbon::now('America/Mexico_City')->locale('es_MX');
        $department = trim((string) $account->unit_number);
        $unit = trim(($account->tower ?: '').' '.$account->unit_number);
        $month = mb_strtoupper($date->translatedFormat('F'), 'UTF-8');

        return [
            'fecha_larga' => 'Ciudad de México '.$date->format('d').' de '.$month.' del '.$date->format('Y'),
            'fecha' => $date->translatedFormat('d \d\e F \d\e Y'),
            'mes_anio' => $month.' del '.$date->format('Y'),
            'condominio' => $profile->commercial_name ?: 'Condominio',
            'direccion' => $profile->address ?: 'dirección del condominio',
            'direccion_configurada' => filled($profile->address),
            'unidad' => $unit !== '' ? $unit : $department,
            'departamento' => $department,
            'residente' => $account->owner_name,
            'propietario' => $account->owner_name,
            'saldo' => '$'.number_format((float) $account->total_debt, 2),
            'administrador' => $profile->admin_name ?: 'Administrador Boleo',
            'administrador_configurado' => filled($profile->admin_name),
            'telefono_administrador' => $profile->admin_phone ?: '',
            'correo_administrador' => $profile->admin_email ?: '',
        ];
    }

    private static function twipsToMm(int $twips): float
    {
        return $twips * 25.4 / 1440.0;
    }

    private static function emuToMm(int $emu): float
    {
        return $emu / 36000.0;
    }
}
