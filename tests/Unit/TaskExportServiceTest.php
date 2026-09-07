<?php

namespace Tests\Unit;

use App\Services\TaskExportService;
use PHPUnit\Framework\TestCase;
use stdClass;

class TaskExportServiceTest extends TestCase
{
    private function sampleTask(): stdClass
    {
        $user = (object) ['name' => 'Andi Pratama', 'position' => 'Supervisor'];

        return (object) [
            'user' => $user,
            'title' => 'Bersih-bersih gudang',
            'status' => 'done',
            'completed_at' => '2026-09-01 08:30',
            'photo_url' => '/storage/tasks/bukti.jpg',
        ];
    }

    public function test_it_creates_an_xlsx_file(): void
    {
        $xlsx = TaskExportService::excel([$this->sampleTask()], '2026-09-01', 'http://localhost:8000');

        self::assertSame("PK", substr($xlsx, 0, 2));
        self::assertGreaterThan(500, strlen($xlsx));
    }

    public function test_it_creates_a_pdf_file(): void
    {
        $pdf = TaskExportService::pdf([$this->sampleTask()], '2026-09-01', 'http://localhost:8000');

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringContainsString('Daftar Tugas - 2026-09-01', $pdf);
    }

    public function test_it_creates_and_reads_task_import_template_with_urutan(): void
    {
        $xlsx = TaskExportService::taskImportTemplate();
        self::assertSame("PK", substr($xlsx, 0, 2));

        $tempFile = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($tempFile, $xlsx);

        try {
            $rows = \App\Services\TaskImportService::read($tempFile);
            self::assertCount(8, $rows);
            self::assertSame('andi', $rows[0]['username']);
            self::assertSame('1', $rows[0]['order']);
            self::assertSame('Membuka toko', $rows[0]['title']);
            self::assertSame('cici', $rows[7]['username']);
            self::assertSame('2', $rows[7]['order']);
            self::assertSame('Melayani pelanggan', $rows[7]['title']);
        } finally {
            @unlink($tempFile);
        }
    }
}
