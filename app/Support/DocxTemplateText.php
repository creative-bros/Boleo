<?php

namespace App\Support;

use App\Models\CondominiumProfile;
use App\Models\ImportedResidentAccount;
use Illuminate\Support\Carbon;
use RuntimeException;
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

        if ($values['residente'] !== '') {
            $text = preg_replace('/Estimado\s+C?ondómin@/iu', 'Estimado/a '.$values['residente'], $text) ?: $text;
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

        if ($status === 'adeudo' && ! str_contains($text, $values['saldo'])) {
            $text .= "\n\nSaldo registrado en Boleo: ".$values['saldo'].'.';
        }

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
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('No fue posible abrir la plantilla DOCX.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('La plantilla DOCX no contiene document.xml.');
        }

        $document = new SimpleXMLElement($xml);
        $document->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = [];

        foreach ($document->xpath('//w:body/w:p') ?: [] as $paragraph) {
            $parts = [];

            foreach ($paragraph->xpath('.//w:t | .//w:tab') ?: [] as $node) {
                $parts[] = $node->getName() === 'tab' ? "\t" : (string) $node;
            }

            $paragraphs[] = trim(implode('', $parts));
        }

        return trim(implode("\n\n", array_filter($paragraphs, fn (string $line): bool => $line !== '')));
    }

    private static function values(CondominiumProfile $profile, ImportedResidentAccount $account): array
    {
        $date = Carbon::now()->locale('es_MX');
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
}
