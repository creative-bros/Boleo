from __future__ import annotations

import html
import re
import zipfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DOCS_DIR = ROOT / "docs"
OUTPUT_DIR = DOCS_DIR / "docx"


MANUALS = [
    "01-acceso-y-primer-ingreso.md",
    "02-gestion-de-usuarios-y-unidades.md",
    "03-cobranza-y-reportes.md",
    "04-amenidades-y-mantenimiento.md",
    "05-pruebas-y-retroalimentacion.md",
    "06-solicitud-de-conversion-a-docx.md",
    "07-mensaje-modelo-para-envio.md",
]


CONTENT_TYPES = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
"""


RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
"""


DOC_RELS = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>
"""


STYLES = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:qFormat/>
    <w:rPr>
      <w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/>
      <w:sz w:val="22"/>
      <w:lang w:val="es-MX"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Title">
    <w:name w:val="Title"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:rPr>
      <w:b/>
      <w:color w:val="1F4AA8"/>
      <w:sz w:val="34"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:rPr>
      <w:b/>
      <w:color w:val="1F4AA8"/>
      <w:sz w:val="28"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:rPr>
      <w:b/>
      <w:color w:val="24406E"/>
      <w:sz w:val="24"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="ListParagraph">
    <w:name w:val="List Paragraph"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:pPr>
      <w:ind w:left="720" w:hanging="360"/>
    </w:pPr>
  </w:style>
</w:styles>
"""


APP_XML = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"
            xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Codex</Application>
</Properties>
"""


def core_xml(title: str) -> str:
    safe_title = html.escape(title)
    return f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
                   xmlns:dc="http://purl.org/dc/elements/1.1/"
                   xmlns:dcterms="http://purl.org/dc/terms/"
                   xmlns:dcmitype="http://purl.org/dc/dcmitype/"
                   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>{safe_title}</dc:title>
  <dc:creator>Codex</dc:creator>
</cp:coreProperties>
"""


def paragraph_xml(text: str, style: str = "Normal") -> str:
    escaped = html.escape(text)
    return (
        f'<w:p><w:pPr><w:pStyle w:val="{style}"/></w:pPr>'
        f'<w:r><w:t xml:space="preserve">{escaped}</w:t></w:r></w:p>'
    )


def normalize_line(line: str) -> str:
    return re.sub(r"\s+", " ", line.strip())


def markdown_to_paragraphs(content: str) -> list[tuple[str, str]]:
    paragraphs: list[tuple[str, str]] = []
    for raw_line in content.splitlines():
        line = normalize_line(raw_line)
        if not line:
            paragraphs.append(("", "Normal"))
            continue

        if line.startswith("# "):
            paragraphs.append((line[2:].strip(), "Title"))
            continue

        if line.startswith("## "):
            paragraphs.append((line[3:].strip(), "Heading1"))
            continue

        if line.startswith("### "):
            paragraphs.append((line[4:].strip(), "Heading2"))
            continue

        numbered = re.match(r"^(\d+)\.\s+(.*)$", line)
        if numbered:
            paragraphs.append((f"{numbered.group(1)}. {numbered.group(2).strip()}", "ListParagraph"))
            continue

        if line.startswith("- "):
            paragraphs.append((f"• {line[2:].strip()}", "ListParagraph"))
            continue

        paragraphs.append((line, "Normal"))

    return paragraphs


def build_document_xml(paragraphs: list[tuple[str, str]]) -> str:
    body = []
    for text, style in paragraphs:
        if text == "":
            body.append(paragraph_xml("", "Normal"))
        else:
            body.append(paragraph_xml(text, style))

    body_xml = "".join(body)
    section = (
        "<w:sectPr>"
        '<w:pgSz w:w="12240" w:h="15840"/>'
        '<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" '
        'w:header="708" w:footer="708" w:gutter="0"/>'
        "</w:sectPr>"
    )
    return f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
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
            xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup"
            xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk"
            xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml"
            xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"
            mc:Ignorable="w14 wp14">
  <w:body>
    {body_xml}
    {section}
  </w:body>
</w:document>
"""


def write_docx(markdown_path: Path, docx_path: Path) -> None:
    content = markdown_path.read_text(encoding="utf-8")
    paragraphs = markdown_to_paragraphs(content)
    title = paragraphs[0][0] if paragraphs else markdown_path.stem
    document_xml = build_document_xml(paragraphs)

    with zipfile.ZipFile(docx_path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("[Content_Types].xml", CONTENT_TYPES)
        zf.writestr("_rels/.rels", RELS)
        zf.writestr("docProps/core.xml", core_xml(title))
        zf.writestr("docProps/app.xml", APP_XML)
        zf.writestr("word/document.xml", document_xml)
        zf.writestr("word/styles.xml", STYLES)
        zf.writestr("word/_rels/document.xml.rels", DOC_RELS)


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    for manual in MANUALS:
        markdown_path = DOCS_DIR / manual
        docx_path = OUTPUT_DIR / f"{markdown_path.stem}.docx"
        write_docx(markdown_path, docx_path)
        print(f"Generated {docx_path.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
