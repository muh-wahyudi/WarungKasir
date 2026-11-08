<?php
// Penulis berkas .xlsx sederhana tanpa pustaka tambahan (ZIP tanpa kompresi), agar tidak perlu ekstensi zip di PHP.
//
// Cara pakai: xlsx_build([['name' => 'Transaksi', 'rows' => $rows, 'widths' => [16, 22], 'freeze' => true]])
// Sel: angka (int/float) tampil dengan pemisah ribuan; teks apa adanya.
// Sel khusus: ['v' => nilai, 'bold' => true] untuk tebal, ['v' => 91.4, 'pct' => true] untuk persen.

function xlsx_col(int $i): string {
    $s = '';
    for ($n = $i + 1; $n > 0; $n = intdiv($n - 1, 26)) $s = chr(65 + ($n - 1) % 26) . $s;
    return $s;
}

function xlsx_esc(string $s): string {
    return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsx_cell(int $c, int $r, $v): string {
    $bold = false;
    $pct = false;
    if (is_array($v)) { $bold = !empty($v['bold']); $pct = !empty($v['pct']); $v = $v['v'] ?? ''; }
    $ref = xlsx_col($c) . $r;
    if (is_int($v) || is_float($v)) {
        $style = $pct ? 4 : ($bold ? 3 : 2);
        return '<c r="' . $ref . '" s="' . $style . '"><v>' . $v . '</v></c>';
    }
    if ($v === null || $v === '') return '';
    return '<c r="' . $ref . '" s="' . ($bold ? 1 : 0) . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_esc((string) $v) . '</t></is></c>';
}

function xlsx_sheet_xml(array $rows, array $widths, bool $freeze): string {
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    if ($freeze) {
        $x .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    }
    if ($widths) {
        $x .= '<cols>';
        foreach (array_values($widths) as $i => $w) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        $x .= '</cols>';
    }
    $x .= '<sheetData>';
    foreach (array_values($rows) as $ri => $row) {
        $cells = '';
        foreach (array_values($row) as $ci => $v) $cells .= xlsx_cell($ci, $ri + 1, $v);
        $x .= '<row r="' . ($ri + 1) . '">' . $cells . '</row>';
    }
    return $x . '</sheetData></worksheet>';
}

function xlsx_styles_xml(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0&quot;%&quot;"/></numFmts>'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="5">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="3" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

// ZIP tanpa kompresi (metode "stored")
function xlsx_zip(array $files): string {
    $out = '';
    $cd = '';
    $n = 0;
    $time = ((int) date('G') << 11) | ((int) date('i') << 5) | intdiv((int) date('s'), 2);
    $date = (((int) date('Y') - 1980) << 9) | ((int) date('n') << 5) | (int) date('j');
    foreach ($files as $name => $data) {
        $crc = crc32($data);
        $len = strlen($data);
        $off = strlen($out);
        $out .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0) . $name . $data;
        $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $off) . $name;
        $n++;
    }
    return $out . $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($cd), strlen($out), 0);
}

function xlsx_build(array $sheets): string {
    $ns = 'http://schemas.openxmlformats.org/';
    $files = [];
    $overrides = '';
    $sheetTags = '';
    $rels = '';
    foreach (array_values($sheets) as $i => $s) {
        $id = $i + 1;
        $name = mb_substr(trim(preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', $s['name'] ?? ('Sheet' . $id))), 0, 31) ?: 'Sheet' . $id;
        $files["xl/worksheets/sheet$id.xml"] = xlsx_sheet_xml($s['rows'] ?? [], $s['widths'] ?? [], !empty($s['freeze']));
        $overrides .= '<Override PartName="/xl/worksheets/sheet' . $id . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $sheetTags .= '<sheet name="' . xlsx_esc($name) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
        $rels .= '<Relationship Id="rId' . $id . '" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
    }
    $styleId = count($sheets) + 1;
    $rels .= '<Relationship Id="rId' . $styleId . '" Type="' . $ns . 'officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $all = [
        '[Content_Types].xml' => $head . '<Types xmlns="' . $ns . 'package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $overrides
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => $head . '<Relationships xmlns="' . $ns . 'package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => $head . '<workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships">'
            . '<sheets>' . $sheetTags . '</sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => $head . '<Relationships xmlns="' . $ns . 'package/2006/relationships">' . $rels . '</Relationships>',
        'xl/styles.xml' => xlsx_styles_xml(),
    ];
    return xlsx_zip($all + $files);
}