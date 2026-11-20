<?php

namespace App\Documents;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Renders one HTML view to a PDF in memory with mPDF.
 * Remote fetching stays off. The file is not written to public/.
 * Latin text uses DejaVu Sans. Bengali text uses FreeSerif, which is the
 * font mPDF's language map already selects for Bengali and which has
 * useOTL enabled. Noto Sans Bengali's current GSUB tables are not read
 * by this mPDF release: registering that font makes TTFontFile throw
 * while parsing coverage, so it is not registered.
 */
final class MpdfFactory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data): string
    {
        $html = $this->filesystemUrls(view($view, $data)->render());
        $tempDir = storage_path('framework/cache/mpdf');

        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new \RuntimeException('The mPDF temporary directory could not be created.');
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'whitelistStreamWrappers' => ['file'],
        ]);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * The shared blades address local files with a file:// URL for Dompdf.
     * mPDF reads a filesystem path.
     */
    private function filesystemUrls(string $html): string
    {
        $rewritten = preg_replace_callback(
            '#file://([^"\')\s]+)#',
            function (array $match): string {
                $path = rawurldecode($match[1]);

                if (preg_match('#^/[A-Za-z]:/#', $path) === 1) {
                    $path = substr($path, 1);
                }

                return $path;
            },
            $html,
        );

        return is_string($rewritten) ? $rewritten : $html;
    }
}
