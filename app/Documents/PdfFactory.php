<?php

namespace App\Documents;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders one HTML view to a PDF in memory.
 * Remote fetching and PHP inside the HTML stay off. The file is not written to public/.
 */
final class PdfFactory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data): string
    {
        $html = view($view, $data)->render();
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot(base_path());
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    public static function fileUrl(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        if (preg_match('#^[A-Za-z]:/#', $normalized) === 1) {
            return 'file:///'.$normalized;
        }

        return 'file://'.$normalized;
    }
}
