<?php
/**
 * export_helpers.php
 * Pembuat XLSX dan PDF murni PHP (tanpa Composer, tanpa ZipArchive, tanpa CDN).
 * Hanya butuh ekstensi zlib (gzdeflate) yang hampir selalu aktif.
 */

/* =========================================================
 *  XLSX
 * ========================================================= */

/** Peta nama gaya -> indeks cellXfs di styles.xml */
const XLSX_STYLES = [
    'normal'  => 0,   // teks biasa
    'bold'    => 1,   // teks tebal
    'num'     => 2,   // angka ribuan
    'title'   => 3,   // judul besar
    'head'    => 4,   // header tabel (hijau, teks putih)
    'cell'    => 5,   // sel tabel bergaris
    'int'     => 6,   // angka bulat bergaris, rata kiri
    'rp'      => 7,   // format "Rp "#,##0 bergaris, rata kiri
    'label'   => 8,   // label kartu ringkasan (abu-abu)
    'bigrp'   => 9,   // nilai besar format Rp
    'bigtext' => 10,  // nilai besar teks
    'section' => 11,  // judul seksi
    'muted'   => 12,  // teks kecil abu-abu
];

/** Sel dengan gaya: xcell('Teks', 'head') */
function xcell($value, string $style = 'normal'): array
{
    return ['v' => $value, 's' => $style];
}

/** Nomor kolom (0 = A) -> huruf kolom (A, B, ... Z, AA, ...) */
function xlsx_col(int $i): string
{
    $s = '';
    $i++;
    while ($i > 0) {
        $m = ($i - 1) % 26;
        $s = chr(65 + $m) . $s;
        $i = intdiv($i - 1, 26);
    }
    return $s;
}

/**
 * @param array  $rows       array of array. Sel boleh scalar (int = angka, selain itu = teks)
 *                           atau xcell(nilai, 'gaya')
 * @param array  $boldRows   indeks baris (mulai 0) yang dicetak tebal (hanya untuk sel scalar)
 * @param string $sheetName  nama sheet
 * @param array  $colWidths  lebar tiap kolom, mis. [8, 22, 30]; kosong = semua 22
 * @param array  $merges     daftar rentang gabung sel, mis. ['A1:F1', 'A4:C4']
 * @param bool   $showGrid   false = sembunyikan garis grid Excel
 * @return string isi file .xlsx (biner)
 */
function buildXlsx(
    array $rows,
    array $boldRows = [0],
    string $sheetName = 'Laporan',
    array $colWidths = [],
    array $merges = [],
    bool $showGrid = true
): string {
    $NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $NS_REL  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $NS_PKG  = 'http://schemas.openxmlformats.org/package/2006/relationships';
    $NS_CT   = 'http://schemas.openxmlformats.org/package/2006/content-types';
    $XML     = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    $colCount = 0;
    foreach ($rows as $r) {
        $colCount = max($colCount, count($r));
    }

    // ---- sheet1.xml ----
    $sheet = $XML . '<worksheet xmlns="' . $NS_MAIN . '">';
    $sheet .= '<sheetViews><sheetView workbookViewId="0"' . ($showGrid ? '' : ' showGridLines="0"') . '/></sheetViews>';
    $sheet .= '<cols>';
    if ($colWidths) {
        foreach (array_values($colWidths) as $i => $w) {
            $n = $i + 1;
            $sheet .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>';
        }
    } else {
        $sheet .= '<col min="1" max="' . max(1, $colCount) . '" width="22" customWidth="1"/>';
    }
    $sheet .= '</cols><sheetData>';

    foreach ($rows as $ri => $row) {
        $rNum = $ri + 1;
        $isBold = in_array($ri, $boldRows, true);
        $sheet .= '<row r="' . $rNum . '">';
        foreach (array_values($row) as $ci => $cell) {
            $ref = xlsx_col($ci) . $rNum;

            if (is_array($cell)) {
                $val = $cell['v'] ?? '';
                $style = XLSX_STYLES[$cell['s'] ?? 'normal'] ?? 0;
            } else {
                $val = $cell;
                $style = null;
            }

            if (is_int($val) || is_float($val)) {
                if ($style === null) {
                    $style = $isBold ? 1 : 2;
                }
                $sheet .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $val . '</v></c>';
            } elseif ($val === '' || $val === null) {
                $sheet .= '<c r="' . $ref . '" s="' . ($style ?? 0) . '"/>';
            } else {
                if ($style === null) {
                    $style = $isBold ? 1 : 0;
                }
                $txt = htmlspecialchars((string)$val, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $txt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $txt);
                $sheet .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $txt . '</t></is></c>';
            }
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData>';
    if ($merges) {
        $sheet .= '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $m) {
            $sheet .= '<mergeCell ref="' . $m . '"/>';
        }
        $sheet .= '</mergeCells>';
    }
    $sheet .= '</worksheet>';

    // ---- styles.xml ----
    $bd = '<left style="thin"><color rgb="FFBBBBBB"/></left><right style="thin"><color rgb="FFBBBBBB"/></right>'
        . '<top style="thin"><color rgb="FFBBBBBB"/></top><bottom style="thin"><color rgb="FFBBBBBB"/></bottom><diagonal/>';

    $styles = $XML . '<styleSheet xmlns="' . $NS_MAIN . '">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;Rp &quot;#,##0"/></numFmts>'
        . '<fonts count="7">'
        .   '<font><sz val="11"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="16"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        .   '<font><sz val="10"/><color rgb="FF555555"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="14"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="12"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        .   '<fill><patternFill patternType="none"/></fill>'
        .   '<fill><patternFill patternType="gray125"/></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF24B47E"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FFF4F7F6"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        .   '<border><left/><right/><top/><bottom/><diagonal/></border>'
        .   '<border>' . $bd . '</border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="13">'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                   // 0 normal
        .   '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                     // 1 bold
        .   '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                             // 2 num
        .   '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                     // 3 title
        .   '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' // 4 head
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'           // 5 cell
        .   '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top"/></xf>'  // 6 int
        .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="top"/></xf>' // 7 rp
        .   '<xf numFmtId="0" fontId="4" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'                       // 8 label
        .   '<xf numFmtId="164" fontId="5" fillId="3" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left"/></xf>' // 9 bigrp
        .   '<xf numFmtId="0" fontId="5" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'                       // 10 bigtext
        .   '<xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                     // 11 section
        .   '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                     // 12 muted
        . '</cellXfs></styleSheet>';

    $safeName = htmlspecialchars(substr($sheetName, 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $files = [
        '[Content_Types].xml' => $XML . '<Types xmlns="' . $NS_CT . '">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',

        '_rels/.rels' => $XML . '<Relationships xmlns="' . $NS_PKG . '">'
            . '<Relationship Id="rId1" Type="' . $NS_REL . '/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',

        'xl/workbook.xml' => $XML . '<workbook xmlns="' . $NS_MAIN . '" xmlns:r="' . $NS_REL . '">'
            . '<sheets><sheet name="' . $safeName . '" sheetId="1" r:id="rId1"/></sheets></workbook>',

        'xl/_rels/workbook.xml.rels' => $XML . '<Relationships xmlns="' . $NS_PKG . '">'
            . '<Relationship Id="rId1" Type="' . $NS_REL . '/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="' . $NS_REL . '/styles" Target="styles.xml"/>'
            . '</Relationships>',

        'xl/styles.xml'            => $styles,
        'xl/worksheets/sheet1.xml' => $sheet,
    ];

    return zip_store($files);
}

/** Membuat file ZIP (metode deflate) dari array [nama => isi]. */
function zip_store(array $files): string
{
    $data = '';
    $central = '';
    $offset = 0;
    $count = 0;
    $dosTime = 0;
    $dosDate = 0x21; // 1980-01-01

    foreach ($files as $name => $content) {
        $crc = crc32($content);
        $uncomp = strlen($content);
        $comp = gzdeflate($content);
        $compLen = strlen($comp);
        $nameLen = strlen($name);

        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, $dosTime, $dosDate, $crc, $compLen, $uncomp, $nameLen, 0) . $name;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50, 20, 20, 0, 8, $dosTime, $dosDate,
            $crc, $compLen, $uncomp, $nameLen, 0, 0, 0, 0, 0, $offset
        ) . $name;

        $data .= $local . $comp;
        $offset = strlen($data);
        $count++;
    }

    $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($data), 0);
    return $data . $central . $eocd;
}

/* =========================================================
 *  PDF
 * ========================================================= */

function pdf_latin1(string $s): string
{
    $r = function_exists('iconv') ? @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s) : false;
    if ($r === false || $r === null) {
        $r = preg_replace('/[^\x20-\x7E]/', '?', $s);
    }
    return str_replace(["\r", "\n", "\t"], ' ', $r);
}

function pdf_esc(string $s): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

/**
 * Membuat PDF tabel (A4 landscape, banyak halaman otomatis).
 *
 * @param string     $title     judul di halaman pertama
 * @param array      $headers   daftar judul kolom
 * @param array      $rows      array of array (isi sel)
 * @param array      $widths    lebar tiap kolom (pt); total sebaiknya 782
 * @param array|null $totalRow  baris total (opsional, dicetak tebal)
 * @param array      $summary   kartu ringkasan di atas tabel: [[label, nilai], ...] (maks 3)
 * @param string     $sectionTitle judul di atas tabel, mis. "5 Transaksi Terbaru"
 * @return string isi file .pdf
 */
function buildPdf(
    string $title,
    array $headers,
    array $rows,
    array $widths,
    ?array $totalRow = null,
    array $summary = [],
    string $sectionTitle = ''
): string
{
    $pw = 842; $ph = 595; $m = 30; $fs = 8; $lh = 10; $pad = 4;
    $pages = [];
    $ops = '';
    $y = 0.0;

    $prepare = function (array $cells) use ($widths, $fs, $lh, $pad) {
        $wrapped = [];
        $maxLines = 1;
        foreach (array_values($cells) as $i => $c) {
            $w = $widths[$i] ?? 60;
            $max = max(1, (int) floor(($w - $pad * 2) / ($fs * 0.54)));
            $text = trim(pdf_latin1((string)$c));
            $lines = explode("\n", wordwrap($text, $max, "\n", true));
            $wrapped[$i] = $lines;
            $maxLines = max($maxLines, count($lines));
        }
        return [$wrapped, $maxLines * $lh + $pad * 2];
    };

    $draw = function (array $wrapped, float $h, string $mode) use (&$ops, &$y, $widths, $m, $fs, $lh, $pad) {
        $x = $m;
        foreach ($wrapped as $i => $lines) {
            $w = $widths[$i] ?? 60;
            $by = round($y - $h, 2);
            if ($mode === 'head') {
                $ops .= "0.14 0.71 0.49 rg $x $by $w " . round($h, 2) . " re f\n";
            } elseif ($mode === 'total') {
                $ops .= "0.92 0.92 0.92 rg $x $by $w " . round($h, 2) . " re f\n";
            }
            $ops .= "0.6 G 0.5 w $x $by $w " . round($h, 2) . " re S\n";
            $ty = $y - $pad - $fs;
            foreach ($lines as $line) {
                $color = $mode === 'head' ? '1 g' : '0 g';
                $font  = $mode === 'body' ? 'F1' : 'F2';
                $ops .= "BT $color /$font $fs Tf " . ($x + $pad) . ' ' . round($ty, 2) . ' Td (' . pdf_esc($line) . ") Tj ET\n";
                $ty -= $lh;
            }
            $x += $w;
        }
        $y -= $h;
    };

    $startPage = function () use (&$ops, &$y, &$pages, $ph, $m, $headers, $prepare, $draw, $title, $summary, $sectionTitle) {
        if ($ops !== '') {
            $pages[] = $ops;
        }
        $first = count($pages) === 0;
        $ops = '';
        $y = $ph - $m;
        if ($first) {
            $ops .= "BT 0 g /F2 14 Tf $m " . round($y - 12, 2) . ' Td (' . pdf_esc(pdf_latin1($title)) . ") Tj ET\n";
            $ops .= "BT 0.3 g /F1 8 Tf $m " . round($y - 26, 2) . ' Td (' . pdf_esc('Dicetak: ' . date('d-m-Y H:i')) . ") Tj ET\n";
            $y -= 36;

            // kartu ringkasan (seperti dashboard)
            if ($summary) {
                $cw = 250; $ch = 50; $gap = 20; $cx = $m;
                foreach (array_slice($summary, 0, 3) as $card) {
                    $by = round($y - $ch, 2);
                    $ops .= "0.957 0.969 0.965 rg $cx $by $cw $ch re f\n";
                    $ops .= "0.14 0.71 0.49 rg $cx $by 4 $ch re f\n";
                    $ops .= "BT 0.33 g /F2 9 Tf " . ($cx + 14) . ' ' . round($y - 17, 2) . ' Td (' . pdf_esc(pdf_latin1((string)$card[0])) . ") Tj ET\n";
                    $ops .= "BT 0 g /F2 16 Tf " . ($cx + 14) . ' ' . round($y - 40, 2) . ' Td (' . pdf_esc(pdf_latin1((string)$card[1])) . ") Tj ET\n";
                    $cx += $cw + $gap;
                }
                $y -= $ch + 22;
            }
            if ($sectionTitle !== '') {
                $ops .= "BT 0 g /F2 12 Tf $m " . round($y - 10, 2) . ' Td (' . pdf_esc(pdf_latin1($sectionTitle)) . ") Tj ET\n";
                $y -= 22;
            }
        }
        [$w, $h] = $prepare($headers);
        $draw($w, $h, 'head');
    };

    $startPage();
    foreach ($rows as $r) {
        [$wr, $h] = $prepare($r);
        if ($y - $h < $m + 14) {
            $startPage();
        }
        $draw($wr, $h, 'body');
    }
    if ($totalRow !== null) {
        [$wr, $h] = $prepare($totalRow);
        if ($y - $h < $m + 14) {
            $startPage();
        }
        $draw($wr, $h, 'total');
    }
    $pages[] = $ops;

    // nomor halaman
    $n = count($pages);
    foreach ($pages as $i => $p) {
        $pages[$i] .= 'BT 0.3 g /F1 8 Tf ' . ($pw - $m - 80) . ' ' . ($m - 12) . ' Td ('
            . pdf_esc('Halaman ' . ($i + 1) . ' dari ' . $n) . ") Tj ET\n";
    }

    // rakit objek PDF
    $objs = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $kids = [];
    for ($i = 0; $i < $n; $i++) {
        $kids[] = (5 + $i * 2) . ' 0 R';
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
    $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    for ($i = 0; $i < $n; $i++) {
        $pageId = 5 + $i * 2;
        $contId = 6 + $i * 2;
        $objs[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pw $ph] "
            . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $contId 0 R >>";
        $objs[$contId] = '<< /Length ' . strlen($pages[$i]) . " >>\nstream\n" . $pages[$i] . "endstream";
    }
    ksort($objs);

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objs as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "$id 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $max = max(array_keys($objs));
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= $max; $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
    }
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $pdf;
}

/* =========================================================
 *  Kirim file ke browser
 * ========================================================= */
function send_download(string $content, string $mime, string $filename): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-store');
    echo $content;
    exit;
}
