<?php

namespace App\Support;

/**
 * Currency text for Dompdf.
 *
 * DejaVu Sans has no U+09F3, so a taka sign in the default font is a missing
 * glyph. resources/fonts/TakaSign.ttf is Noto Sans Bengali (SIL OFL) reduced
 * to that one code point. A rendered PDF showed the glyph. Bengali words are
 * not drawn with it: Dompdf does not shape Bengali text.
 *
 * If that subset stops drawing the sign, replace the span with the text "Tk ".
 */
final class PdfMoney
{
    public static function html(Money $money): string
    {
        $grouped = $money->groupedAmount();
        $negative = str_starts_with($grouped, '-');
        $digits = htmlspecialchars(ltrim($grouped, '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sign = $negative ? '-' : '';

        if ($money->currency() !== Money::DEFAULT_CURRENCY) {
            return $sign.htmlspecialchars($money->currency(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').' '.$digits;
        }

        return $sign.'<span class="taka">&#x09F3;</span>'.$digits;
    }
}
