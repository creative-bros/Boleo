<?php

namespace App\Support;

use App\Models\CondominiumProfile;
class BankingWordExporter
{
    public function __construct(
        private readonly CondominiumProfile $profile
    ) {
    }

    public function render(): string
    {
        return $this->buildZip([
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->relsXml(),
            'word/document.xml' => $this->documentXml(),
        ]);
    }

    private function contentTypesXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;
    }

    private function relsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
    }

    private function documentXml(): string
    {
        $rows = [
            ['Condominio', $this->profile->commercial_name ?: 'Sin configurar'],
            ['Institución bancaria', $this->profile->bank ?: 'Sin configurar'],
            ['Titular de la cuenta', $this->profile->account_holder ?: 'Sin configurar'],
            ['Tipo de cuenta', $this->profile->bank_account_type ?: 'Sin configurar'],
            ['Número de cuenta', $this->profile->account_number ?: 'Sin configurar'],
            ['CLABE', $this->profile->clabe ?: 'Sin configurar'],
            ['Convenio', $this->profile->bank_agreement ?: 'Sin configurar'],
            ['Referencia', $this->profile->bank_reference ?: 'Sin configurar'],
            ['Sucursal', $this->profile->bank_branch ?: 'Sin configurar'],
            ['Correo bancario o contacto', $this->profile->bank_contact_email ?: 'Sin configurar'],
        ];

        $body = '';

        foreach ($rows as [$label, $value]) {
            $body .= $this->paragraph($label.': '.$value);
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas"
    xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"
    xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing"
    xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
    xmlns:w10="urn:schemas-microsoft-com:office:word"
    xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
    xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml"
    xmlns:w15="http://schemas.microsoft.com/office/word/2012/wordml"
    xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup"
    xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk"
    xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml"
    xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"
    mc:Ignorable="w14 w15 wp14">
    <w:body>
        {$this->paragraph('Datos bancarios del condominio', true)}
        {$this->paragraph('Formato generado automáticamente desde Boleo.')}
        {$body}
        <w:sectPr>
            <w:pgSz w:w="11906" w:h="16838"/>
            <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/>
        </w:sectPr>
    </w:body>
</w:document>
XML;
    }

    private function paragraph(string $text, bool $bold = false): string
    {
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $boldTag = $bold ? '<w:rPr><w:b/></w:rPr>' : '';

        return <<<XML
<w:p>
    <w:r>
        {$boldTag}
        <w:t xml:space="preserve">{$escaped}</w:t>
    </w:r>
</w:p>
XML;
    }

    private function buildZip(array $files): string
    {
        $offset = 0;
        $localFileData = '';
        $centralDirectory = '';
        $fileCount = 0;

        foreach ($files as $name => $content) {
            $name = str_replace('\\', '/', $name);
            $crc = crc32($content);
            $size = strlen($content);
            $nameLength = strlen($name);

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                $nameLength,
                0
            );

            $localFileData .= $localHeader.$name.$content;

            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                $nameLength,
                0,
                0,
                0,
                0,
                32,
                $offset
            );

            $centralDirectory .= $centralHeader.$name;
            $offset += strlen($localHeader) + $nameLength + $size;
            $fileCount++;
        }

        $centralSize = strlen($centralDirectory);
        $endRecord = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $fileCount,
            $fileCount,
            $centralSize,
            $offset,
            0
        );

        return $localFileData.$centralDirectory.$endRecord;
    }
}
