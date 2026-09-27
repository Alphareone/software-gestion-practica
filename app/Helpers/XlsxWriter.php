<?php

class XlsxWriter {
    private $rows = [];
    private $headers = [];

    public function setHeaders(array $headers) {
        $this->headers = $headers;
    }

    public function addRow(array $row) {
        $this->rows[] = $row;
    }

    public function output($filename) {
        $sharedStrings = ['ID'];
        $sharedIndex = function ($v) use (&$sharedStrings) {
            $idx = array_search($v, $sharedStrings, true);
            if ($idx === false) {
                $idx = count($sharedStrings);
                $sharedStrings[] = $v;
            }
            return $idx;
        };

        $colLetters = [];
        $colCount = count($this->headers);
        for ($i = 0; $i < $colCount; $i++) {
            $colLetters[] = chr(65 + $i);
        }

        // Reset shared strings with headers
        $sharedStrings = $this->headers;
        foreach ($this->rows as $row) {
            foreach ($row as $cell) {
                $sharedIndex((string) $cell);
            }
        }

        $xmlSheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>' . "\n";
        $r = 1;
        // Header row
        $xmlSheet .= '    <row r="' . $r . '">';
        foreach ($this->headers as $i => $h) {
            $idx = $sharedIndex($h);
            $xmlSheet .= '<c r="' . $colLetters[$i] . $r . '" t="s"><v>' . $idx . '</v></c>';
        }
        $xmlSheet .= '</row>' . "\n";
        $r++;

        foreach ($this->rows as $row) {
            $xmlSheet .= '    <row r="' . $r . '">';
            foreach ($row as $i => $cell) {
                $val = (string) $cell;
                if (is_numeric($val) && $val !== '') {
                    $xmlSheet .= '<c r="' . $colLetters[$i] . $r . '" t="n"><v>' . $val . '</v></c>';
                } else {
                    $idx = $sharedIndex($val);
                    $xmlSheet .= '<c r="' . $colLetters[$i] . $r . '" t="s"><v>' . $idx . '</v></c>';
                }
            }
            $xmlSheet .= '</row>' . "\n";
            $r++;
        }
        $xmlSheet .= '  </sheetData>
</worksheet>';

        // Shared strings XML
        $xmlShared = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
        foreach ($sharedStrings as $s) {
            $xmlShared .= '<si><t>' . htmlspecialchars($s, ENT_XML1) . '</t></si>';
        }
        $xmlShared .= '</sst>';

        $zip = new ZipArchive();
        $res = $zip->open($filename, ZipArchive::CREATE);
        if ($res !== true) {
            throw new RuntimeException('No se pudo crear el archivo XLSX (error ' . $res . ').');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Productos" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        $zip->addFromString('xl/worksheets/sheet1.xml', $xmlSheet);
        $zip->addFromString('xl/sharedStrings.xml', $xmlShared);

        // Minimal styles
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
  </cellXfs>
</styleSheet>');

        $zip->close();
    }
}
