<?php

namespace App\Services;

use DateTimeInterface;
use RuntimeException;

class TaskExportService
{
    /** Build a real .xlsx workbook without requiring a third-party package. */
    public static function excel(
        iterable $tasks,
        string $date,
        string $baseUrl,
        ?string $publicStoragePath = null
    ): string
    {
        $rows = self::rows($tasks, $baseUrl);
        $photos = self::photos($rows, $publicStoragePath);

        $files = [
            '[Content_Types].xml' => self::contentTypesXml($photos),
            '_rels/.rels' => self::rootRelationshipsXml(),
            'xl/workbook.xml' => self::workbookXml(),
            'xl/_rels/workbook.xml.rels' => self::workbookRelationshipsXml(),
            'xl/styles.xml' => self::stylesXml(),
            'xl/worksheets/sheet1.xml' => self::sheetXml($rows, $date, $photos),
        ];

        if ($photos !== []) {
            $files['xl/worksheets/_rels/sheet1.xml.rels'] = self::worksheetRelationshipsXml();
            $files['xl/drawings/drawing1.xml'] = self::drawingXml($photos);
            $files['xl/drawings/_rels/drawing1.xml.rels'] = self::drawingRelationshipsXml($photos);

            foreach ($photos as $index => $photo) {
                $files['xl/media/image'.($index + 1).'.'.$photo['extension']] = $photo['contents'];
            }
        }

        return self::zip($files);
    }

    /** Template .xlsx impor tugas harian. */
    public static function taskImportTemplate(): string
    {
        return TaskImportService::template();
    }

    /** Build the manager's weekly schedule workbook, including attendance times. */
    public static function scheduleExcel(array $rows, string $weekStart): string
    {
        return self::zip([
            '[Content_Types].xml' => self::contentTypesXml(),
            '_rels/.rels' => self::rootRelationshipsXml(),
            'xl/workbook.xml' => self::workbookXml('Jadwal'),
            'xl/_rels/workbook.xml.rels' => self::workbookRelationshipsXml(),
            'xl/styles.xml' => self::stylesXml(),
            'xl/worksheets/sheet1.xml' => self::scheduleSheetXml($rows, $weekStart),
        ]);
    }

    /** Build a compact, printable PDF report of the task list. */
    public static function pdf(iterable $tasks, string $date, string $baseUrl): string
    {
        $rows = self::rows($tasks, $baseUrl);
        $pages = array_chunk($rows, 30);
        if ($pages === []) {
            $pages = [[]];
        }

        $pageCount = count($pages);
        $regularFontId = 3 + ($pageCount * 2);
        $boldFontId = $regularFontId + 1;
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids ['.implode(' ', array_map(
                fn (int $index) => (3 + ($index * 2)).' 0 R',
                array_keys($pages)
            )).'] /Count '.$pageCount.' >>',
        ];

        foreach ($pages as $index => $pageRows) {
            $pageId = 3 + ($index * 2);
            $contentId = $pageId + 1;
            $content = self::pdfPage($pageRows, $date, $index + 1, $pageCount);

            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                .'/Resources << /Font << /F1 '.$regularFontId.' 0 R /F2 '.$boldFontId.' 0 R >> >> '
                .'/Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";
        }

        $objects[$regularFontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$boldFontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        return self::pdfDocument($objects);
    }

    private static function rows(iterable $tasks, string $baseUrl): array
    {
        $rows = [];
        $number = 1;

        foreach ($tasks as $task) {
            $user = self::value($task, 'user');
            $attendance = self::value($task, 'attendance');
            $photoUrl = (string) (self::value($task, 'photo_url') ?? '');
            if ($photoUrl !== '' && str_starts_with($photoUrl, '/')) {
                $photoUrl = rtrim($baseUrl, '/').$photoUrl;
            }

            $rows[] = [
                'no' => (string) $number++,
                'employee' => (string) (self::value($user, 'name') ?? '-'),
                'position' => (string) (self::value($user, 'position') ?? '-'),
                'title' => (string) (self::value($task, 'title') ?? '-'),
                'status' => self::value($task, 'status') === 'done' ? 'Selesai' : 'Belum selesai',
                'clock_in' => self::time(self::value($attendance, 'clock_in')),
                'clock_out' => self::time(self::value($attendance, 'clock_out')),
                'completed_date' => self::date(self::value($task, 'completed_at')),
                'completed_time' => self::time(self::value($task, 'completed_at')),
                'photo' => (string) (self::value($task, 'photo') ?? ''),
                'photo_url' => $photoUrl,
            ];
        }

        return $rows;
    }

    private static function value(mixed $source, string $key): mixed
    {
        if (is_array($source)) {
            return $source[$key] ?? null;
        }

        if (is_object($source)) {
            return $source->{$key} ?? null;
        }

        return null;
    }

    private static function dateTime(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        return $value ? (string) $value : '-';
    }

    private static function time(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        if (! $value) {
            return '-';
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? (string) $value : date('H:i', $timestamp);
    }

    private static function date(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! $value) {
            return '-';
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? (string) $value : date('Y-m-d', $timestamp);
    }

    /**
     * Return local image files that can be embedded in the workbook.
     *
     * Excel does not render an HTTP URL as an image, so the original file has
     * to be added to the .xlsx package and positioned over its cell.
     */
    private static function photos(array $rows, ?string $publicStoragePath): array
    {
        if (! $publicStoragePath) {
            return [];
        }

        $photos = [];
        foreach ($rows as $index => $row) {
            $relativePath = str_replace('\\', '/', ltrim($row['photo'], '/'));
            if ($relativePath === '' || str_contains($relativePath, '..')) {
                continue;
            }

            $path = rtrim($publicStoragePath, "\\/").DIRECTORY_SEPARATOR
                .str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $extension = $extension === 'jpeg' ? 'jpg' : $extension;

            if (! in_array($extension, ['jpg', 'png'], true) || ! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $photos[] = [
                'row' => $index + 3,
                'extension' => $extension,
                'contents' => $contents,
            ];
        }

        return $photos;
    }

    private static function sheetXml(array $rows, string $date, array $photos = []): string
    {
        $headings = ['No.', 'Karyawan', 'Posisi', 'Tugas', 'Status', 'Jam Masuk', 'Jam Pulang', 'Tanggal Selesai', 'Jam Selesai', 'Foto Bukti'];
        $photosByRow = [];
        foreach ($photos as $photo) {
            $photosByRow[$photo['row']] = true;
        }

        $lastRow = max(2, count($rows) + 2);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="A1:J'.$lastRow.'"/><sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>'
            .'<cols><col min="1" max="1" width="8" customWidth="1"/>'
            .'<col min="2" max="2" width="24" customWidth="1"/>'
            .'<col min="3" max="3" width="18" customWidth="1"/>'
            .'<col min="4" max="4" width="34" customWidth="1"/>'
            .'<col min="5" max="5" width="16" customWidth="1"/>'
            .'<col min="6" max="7" width="16" customWidth="1"/>'
            .'<col min="8" max="8" width="18" customWidth="1"/>'
            .'<col min="9" max="9" width="15" customWidth="1"/>'
            .'<col min="10" max="10" width="20" customWidth="1"/></cols><sheetData>';

        $xml .= self::sheetRow(1, ['Daftar Tugas - '.$date], 2, 26);
        $xml .= self::sheetRow(2, $headings, 1, 22);

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 3;
            $xml .= self::sheetRow($rowNumber, [
                $row['no'], $row['employee'], $row['position'], $row['title'],
                $row['status'], $row['clock_in'], $row['clock_out'], $row['completed_date'], $row['completed_time'],
                isset($photosByRow[$rowNumber]) ? 'Foto terlampir' : '-',
            ], 3, isset($photosByRow[$rowNumber]) ? 52 : null);
        }

        $xml .= '</sheetData><autoFilter ref="A2:J'.$lastRow.'"/><mergeCells count="1">'
            .'<mergeCell ref="A1:J1"/></mergeCells>';

        if ($photos !== []) {
            $xml .= '<drawing r:id="rId1"/>';
        }

        return $xml.'</worksheet>';
    }

    private static function sheetRow(int $rowNumber, array $values, int $style = 0, ?int $height = null): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $column = self::columnName($index + 1);
            $styleAttribute = $style ? ' s="'.$style.'"' : '';
            $cells .= '<c r="'.$column.$rowNumber.'" t="inlineStr"'.$styleAttribute.'><is><t>'
                .self::xml((string) $value).'</t></is></c>';
        }

        $heightAttribute = $height ? ' ht="'.$height.'" customHeight="1"' : '';

        return '<row r="'.$rowNumber.'"'.$heightAttribute.'>'.$cells.'</row>';
    }

    private static function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypesXml(array $photos = []): string
    {
        $imageContentTypes = '';
        if ($photos !== []) {
            $extensions = array_unique(array_column($photos, 'extension'));
            foreach ($extensions as $extension) {
                $imageContentTypes .= '<Default Extension="'.$extension.'" ContentType="image/'
                    .($extension === 'jpg' ? 'jpeg' : 'png').'"/>';
            }
            $imageContentTypes .= '<Override PartName="/xl/drawings/drawing1.xml" '
                .'ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .$imageContentTypes
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function scheduleSheetXml(array $rows, string $weekStart): string
    {
        $headings = ['No.', 'Karyawan', 'Posisi', 'Hari', 'Tanggal', 'Shift', 'Status Kehadiran', 'Jam Masuk', 'Jam Pulang'];
        $lastRow = max(2, count($rows) + 2);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:I'.$lastRow.'"/><sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>'
            .'<cols><col min="1" max="1" width="8" customWidth="1"/>'
            .'<col min="2" max="2" width="24" customWidth="1"/>'
            .'<col min="3" max="3" width="18" customWidth="1"/>'
            .'<col min="4" max="4" width="13" customWidth="1"/>'
            .'<col min="5" max="5" width="14" customWidth="1"/>'
            .'<col min="6" max="6" width="16" customWidth="1"/>'
            .'<col min="7" max="7" width="20" customWidth="1"/>'
            .'<col min="8" max="9" width="15" customWidth="1"/></cols><sheetData>';

        // Baris 1: Kolom A-B (space kosong untuk logo perusahaan), Kolom C-I (Judul "Absensi Turima Fram" di tengah)
        $xml .= self::sheetRow(1, ['', '', 'Absensi Turima Fram'], 2, 48);
        $xml .= self::sheetRow(2, $headings, 1, 22);

        foreach ($rows as $index => $row) {
            $xml .= self::sheetRow($index + 3, [
                $row['no'], $row['employee'], $row['position'], $row['day'], $row['date'],
                $row['shift'], $row['attendance_status'], $row['clock_in'], $row['clock_out'],
            ], 3);
        }

        return $xml.'</sheetData><autoFilter ref="A2:I'.$lastRow.'"/><mergeCells count="2">'
            .'<mergeCell ref="A1:B1"/>'
            .'<mergeCell ref="C1:I1"/>'
            .'</mergeCells></worksheet>';
    }


    private static function workbookXml(string $sheetName = 'Daftar Tugas'): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::xml($sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private static function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="10"/><name val="Arial"/><color rgb="FFFFFFFF"/></font>'
            .'<font><b/><sz val="15"/><name val="Arial"/><color rgb="FF125B3A"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF125B3A"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD5E8DD"/></left><right style="thin"><color rgb="FFD5E8DD"/></right>'
            .'<top style="thin"><color rgb="FFD5E8DD"/></top><bottom style="thin"><color rgb="FFD5E8DD"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'
            .'<alignment vertical="center" wrapText="1"/></xf></cellXfs>'
            .'</styleSheet>';
    }

    private static function worksheetRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" '
            .'Target="../drawings/drawing1.xml"/></Relationships>';
    }

    private static function drawingRelationshipsXml(array $photos): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach ($photos as $index => $photo) {
            $xml .= '<Relationship Id="rId'.($index + 1).'" '
                .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '
                .'Target="../media/image'.($index + 1).'.'.$photo['extension'].'"/>';
        }

        return $xml.'</Relationships>';
    }

    private static function drawingXml(array $photos): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
            .'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

        foreach ($photos as $index => $photo) {
            $id = $index + 1;
            $rowIndex = $photo['row'] - 1;
            $xml .= '<xdr:oneCellAnchor><xdr:from><xdr:col>9</xdr:col><xdr:colOff>47625</xdr:colOff>'
                .'<xdr:row>'.$rowIndex.'</xdr:row><xdr:rowOff>47625</xdr:rowOff></xdr:from>'
                .'<xdr:ext cx="476250" cy="476250"/><xdr:pic><xdr:nvPicPr>'
                .'<xdr:cNvPr id="'.$id.'" name="Foto Bukti '.$id.'"/><xdr:cNvPicPr/>'
                .'</xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId'.$id.'"/>'
                .'<a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr>'
                .'<a:xfrm><a:off x="0" y="0"/><a:ext cx="476250" cy="476250"/></a:xfrm>'
                .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:ln><a:noFill/></a:ln>'
                .'</xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
        }

        return $xml.'</xdr:wsDr>';
    }

    private static function zip(array $files): string
    {
        $data = '';
        $centralDirectory = '';
        $offset = 0;
        $entries = 0;
        $now = getdate();
        $dosTime = ($now['hours'] << 11) | ($now['minutes'] << 5) | intdiv($now['seconds'], 2);
        $dosDate = (($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];

        foreach ($files as $name => $contents) {
            $compressed = gzdeflate($contents);
            if ($compressed === false) {
                throw new RuntimeException('Gagal membuat file Excel.');
            }

            $crc = crc32($contents);
            $compressedLength = strlen($compressed);
            $uncompressedLength = strlen($contents);
            $nameLength = strlen($name);

            $localFile = pack(
                'VvvvvvVVVvv',
                0x04034b50, 20, 0, 8, $dosTime, $dosDate, $crc,
                $compressedLength, $uncompressedLength, $nameLength, 0
            ).$name.$compressed;
            $data .= $localFile;

            $centralDirectory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, 20, 20, 0, 8, $dosTime, $dosDate, $crc,
                $compressedLength, $uncompressedLength, $nameLength, 0, 0, 0, 0, 0, $offset
            ).$name;

            $offset += strlen($localFile);
            $entries++;
        }

        return $data.$centralDirectory.pack(
            'VvvvvVVv', 0x06054b50, 0, 0, $entries, $entries,
            strlen($centralDirectory), $offset, 0
        );
    }

    private static function pdfPage(array $rows, string $date, int $page, int $pageCount): string
    {
        $content = "0.06 0.25 0.15 rg 36 750 523 22 re f\n1 1 1 rg\n";
        $content .= self::pdfText('F2', 12, 46, 758, 'Daftar Tugas - '.$date);
        $content .= "0.84 0.91 0.87 rg 36 724 523 17 re f\n0.05 0.13 0.10 rg\n";
        $content .= self::pdfText('F2', 8, 41, 730, 'No.');
        $content .= self::pdfText('F2', 8, 68, 730, 'Karyawan');
        $content .= self::pdfText('F2', 8, 188, 730, 'Tugas');
        $content .= self::pdfText('F2', 8, 386, 730, 'Status');
        $content .= self::pdfText('F2', 8, 458, 730, 'Waktu Selesai');

        $y = 708;
        if ($rows === []) {
            $content .= self::pdfText('F1', 10, 42, $y, 'Belum ada tugas untuk tanggal ini.');
        }

        foreach ($rows as $row) {
            $content .= "0.82 0.88 0.85 RG 36 ".($y - 7)." m 559 ".($y - 7)." l S\n0.05 0.13 0.10 rg\n";
            $content .= self::pdfText('F1', 8, 41, $y, $row['no']);
            $content .= self::pdfText('F1', 8, 68, $y, self::trim($row['employee'], 20));
            $content .= self::pdfText('F1', 8, 188, $y, self::trim($row['title'], 35));
            $content .= self::pdfText('F1', 8, 386, $y, $row['status']);
            $content .= self::pdfText('F1', 8, 458, $y, self::trim($row['completed_at'], 20));
            $y -= 21;
        }

        $content .= "0.32 0.40 0.36 rg\n";
        $content .= self::pdfText('F1', 8, 36, 28, 'TURIMA FRAM - Laporan Daftar Tugas');
        $content .= self::pdfText('F1', 8, 500, 28, 'Halaman '.$page.' dari '.$pageCount);

        return $content;
    }

    private static function pdfText(string $font, int $size, int $x, float $y, string $text): string
    {
        return "BT /{$font} {$size} Tf {$x} ".number_format($y, 2, '.', '').' Td ('
            .self::pdfEscape($text).") Tj ET\n";
    }

    private static function pdfEscape(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value) ?: $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private static function trim(string $value, int $width): string
    {
        return mb_strimwidth($value, 0, $width, '...');
    }

    private static function pdfDocument(array $objects): string
    {
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($objects as $id => $_) {
            $pdf .= str_pad((string) $offsets[$id], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer' . "\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n"
            .$xrefOffset."\n%%EOF";
    }
}
