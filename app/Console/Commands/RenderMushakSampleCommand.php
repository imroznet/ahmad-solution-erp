<?php

namespace App\Console\Commands;

use App\Documents\MpdfFactory;
use App\Tax\MushakSample;
use Illuminate\Console\Command;

class RenderMushakSampleCommand extends Command
{
    protected $signature = 'asl:render-mushak-sample {path? : Where to write the PDF. Defaults to storage/app/mushak-mpdf-sample.pdf}';

    protected $description = 'Render a draft Mushak 6.3 PDF with mPDF, including the Bengali strings used to check shaping';

    public function handle(MpdfFactory $pdf, MushakSample $sample): int
    {
        $path = $this->argument('path');
        $path = is_string($path) && $path !== '' ? $path : storage_path('app/mushak-mpdf-sample.pdf');
        $binary = $pdf->render('tax.mushak.pdf', $sample->viewData());

        if (! str_starts_with($binary, '%PDF-') || file_put_contents($path, $binary) === false) {
            $this->error('The sample PDF was not written.');

            return self::FAILURE;
        }

        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}
