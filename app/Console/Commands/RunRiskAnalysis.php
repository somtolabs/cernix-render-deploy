<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class RunRiskAnalysis extends Command
{
    protected $signature = 'cernix:run-risk-analysis';

    protected $description = 'Export safe CERNIX scan data and run the optional Python risk analyzer';

    public function handle(): int
    {
        $exportPath = 'risk-analysis/scan_logs.json';
        $jsonPath = storage_path('app/risk-analysis/risk_report.json');
        $htmlPath = storage_path('app/risk-analysis/risk_report.html');
        $inputPath = storage_path('app/' . $exportPath);
        $scriptPath = base_path('python_services/risk_analyzer/analyze.py');

        if (! file_exists($scriptPath)) {
            $this->error('Python risk analyzer was not found at python_services/risk_analyzer/analyze.py.');

            return self::FAILURE;
        }

        $this->info('Exporting safe risk-analysis data...');
        $exportCode = Artisan::call('cernix:export-risk-data', ['--path' => $exportPath]);
        $this->output->write(Artisan::output());

        if ($exportCode !== self::SUCCESS) {
            $this->error('Risk data export failed.');

            return self::FAILURE;
        }

        foreach (['python', 'python3'] as $pythonBinary) {
            $process = new Process([
                $pythonBinary,
                $scriptPath,
                $inputPath,
                $jsonPath,
                '--html',
                $htmlPath,
            ], base_path());
            $process->setTimeout(120);
            $process->run();

            if ($process->isSuccessful()) {
                $this->line(trim($process->getOutput()));
                $this->info('Risk intelligence report written to storage/app/risk-analysis/risk_report.json');
                $this->info('HTML report written to storage/app/risk-analysis/risk_report.html');

                return self::SUCCESS;
            }

            $error = trim($process->getErrorOutput());
            if ($error !== '') {
                $this->line($pythonBinary . ' failed: ' . $error);
            }
        }

        $this->error('Python is not installed or the risk analyzer could not be executed.');
        $this->line('Install Python, then run:');
        $this->line('python python_services/risk_analyzer/analyze.py storage/app/risk-analysis/scan_logs.json storage/app/risk-analysis/risk_report.json --html storage/app/risk-analysis/risk_report.html');

        return self::FAILURE;
    }
}
