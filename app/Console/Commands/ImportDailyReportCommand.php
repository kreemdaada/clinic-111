<?php

namespace App\Console\Commands;

use App\Services\Import\DailyReportImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Throwable;

class ImportDailyReportCommand extends Command
{
    protected $signature = 'daily-report:import
                            {path : Absolute or relative path to the Excel file (.xlsx or .xlsm)}';

    protected $description = 'Import a daily Excel report from disk (month is read from the file name)';

    public function handle(DailyReportImportService $importService): int
    {
        $filePath = $this->argument('path');

        if (! is_string($filePath) || ! File::exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $absolutePath = realpath($filePath);

        if ($absolutePath === false) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $originalName = basename($absolutePath);

        $this->info("Importing: {$absolutePath}");

        try {
            $uploadedFile = $this->createUploadedFile($absolutePath, $originalName);

            $dailyReport = $importService->import($uploadedFile);

            $rowCount = $dailyReport->dailyWorkRows->count();

            $this->newLine();
            $this->info('Import successful.');
            $this->line("Report ID: {$dailyReport->id}");
            $this->line('Report month: '.$dailyReport->report_date->format('F Y'));
            $this->line("Status: {$dailyReport->status->value}");
            $this->line("Rows imported: {$rowCount}");

            $extractionPath = storage_path('app/private/import-extractions/report-'.$dailyReport->id.'.json');

            if (is_file($extractionPath)) {
                $this->line("Extraction log: {$extractionPath}");
                $this->line('View in browser: /logs/extraction/'.$dailyReport->id);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Import failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function createUploadedFile(string $absolutePath, string $originalName): UploadedFile
    {
        $symfonyFile = new SymfonyFile($absolutePath);

        return new UploadedFile(
            path: $symfonyFile->getPathname(),
            originalName: $originalName,
            mimeType: $symfonyFile->getMimeType(),
            error: UPLOAD_ERR_OK,
            test: true,
        );
    }
}
