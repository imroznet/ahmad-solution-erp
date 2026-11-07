<?php

namespace App\Tax;

/**
 * Statutory issue stays closed until the source register's open questions
 * are answered and this class is changed deliberately. An administrator
 * cannot lift the gate from a screen.
 */
final class IssuanceGate
{
    /**
     * @return list<string>
     */
    public static function blockers(): array
    {
        return [
            'Mushak 6.3 cannot be issued. The form linked by the Dhaka North commissionerate on 6 November 2026 is a 29 June 2019 PDF (SHA-256 c09e853829e19a6265d82b53b0f4408c9db95ca8793fa1666cec39c6870cf5a2, server date 23 June 2025). SRO 125-Ain/2026/330-Mushak takes effect on 1 July 2026 and amends rule 40 without a replacement Mushak 6.3 specimen.',
            'No output VAT, VDS, or AIT rate is seeded. A supply classification for Ahmad Solution Ltd. has not been verified, and the trade licence is not a rate.',
            'A VDS certificate cannot be issued. SRO 140-Ain/2026/345-Mushak amends the 2025 deduction rules from 1 July 2026 and does not set out a certificate. Mushak 6.6 is not part of this release.',
            'An AIT certificate cannot be issued. SRO 210-Ain/Income Tax-1/2026 and SRO 273-Ain/Income Tax-5/2026 both name withholding rules effective 1 July 2026. Which instrument governs, and which certificate form applies, is not confirmed.',
        ];
    }

    public static function mushakIssueAllowed(): bool
    {
        return false;
    }

    public static function certificateIssueAllowed(): bool
    {
        return false;
    }
}
