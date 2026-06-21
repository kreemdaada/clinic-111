<?php

namespace App\Console\Commands;

use App\Services\Import\DailyReportImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Throwable;

/**
 * CLI import for large daily Excel files (bypasses HTTP upload limits).
 *
 * Signature: `php artisan daily-report:import "/path/to/daily report January 2026.xlsm"`
 * Report month is read from the file name, not from today's date.
 */
class ImportDailyReportCommand extends Command
{
    /** @var string Artisan command name and path argument. */
    protected $signature = 'daily-report:import
                            {path : Absolute or relative path to the Excel file (.xlsx or .xlsm)}';

    /** @var string Short description shown in `php artisan list`. */
    protected $description = 'Import a daily Excel report from disk (month is read from the file name)';

    /**
     * Run the full import pipeline and print report summary to the terminal.
     *
     * @param  DailyReportImportService  $importService  Same pipeline as web/API upload.
     * @return int Command::SUCCESS or Command::FAILURE exit code.
     */
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

    /**
     * Wrap a disk file as an UploadedFile so {@see DailyReportImportService} can reuse the HTTP path.
     *
     * @param  string  $absolutePath  Resolved absolute path to the Excel file.
     * @param  string  $originalName  Basename used for month detection and storage.
     * @return UploadedFile Test-mode upload instance (`test: true`).
     */
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
