<?php
/**
 * export_helpers.php
 * Pembuat XLSX dan PDF murni PHP (tanpa Composer, tanpa ZipArchive, tanpa CDN).
 * Hanya butuh ekstensi zlib (gzdeflate) yang hampir selalu aktif.
 */

/* =========================================================
 *  XLSX
 * ========================================================= */

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
 * @param array  $rows      array of array; int = angka, selain itu = teks
 * @param array  $boldRows  indeks baris (mulai 0) yang dicetak tebal
 * @param string $sheetName nama sheet
 * @return string isi file .xlsx (biner)
 */
function buildXlsx(array $rows, array $boldRows = [0], string $sheetName = 'Laporan'): string
{
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
    $sheet .= '<cols><col min="1" max="' . max(1, $colCount) . '" width="22" customWidth="1"/></cols>';
    $sheet .= '<sheetData>';
    foreach ($rows as $ri => $row) {
        $rNum = $ri + 1;
        $isBold = in_array($ri, $boldRows, true);
        $sheet .= '<row r="' . $rNum . '">';
        foreach (array_values($row) as $ci => $val) {
            $ref = xlsx_col($ci) . $rNum;
            if (is_int($val) || is_float($val)) {
                $style = $isBold ? 1 : 2; // 2 = format ribuan
                $sheet .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $val . '</v></c>';
            } else {
                $style = $isBold ? 1 : 0;
                $txt = htmlspecialchars((string)$val, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                // buang karakter kontrol yang tidak valid di XML
                $txt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $txt);
                $sheet .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $txt . '</t></is></c>';
            }
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';

    // ---- styles.xml (0 = normal, 1 = tebal, 2 = angka ribuan) ----
    $styles = $XML . '<styleSheet xmlns="' . $NS_MAIN . '">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="3">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
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
 * @return string isi file .pdf
 */
function buildPdf(string $title, array $headers, array $rows, array $widths, ?array $totalRow = null): string
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

    $startPage = function () use (&$ops, &$y, &$pages, $ph, $m, $headers, $prepare, $draw, $title) {
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
