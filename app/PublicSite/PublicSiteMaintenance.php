<?php

namespace App\PublicSite;

use App\Models\PublicSiteControl;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * The public website is a separate application. It reads flag files.
 * This ERP stays available either way. A missing row leaves the site visible
 * and the contact form on. reCAPTCHA stays off until an administrator turns it on.
 */
final class PublicSiteMaintenance
{
    public function enabled(): bool
    {
        $row = $this->row();

        return $row instanceof PublicSiteControl && $row->maintenance_enabled;
    }

    public function contactFormEnabled(): bool
    {
        $row = $this->row();

        return ! $row instanceof PublicSiteControl || $row->contact_form_enabled;
    }

    public function recaptchaEnabled(): bool
    {
        $row = $this->row();

        return $row instanceof PublicSiteControl && $row->recaptcha_enabled;
    }

    public function recaptchaKeysPresent(): bool
    {
        $site = config('asl.public_site.recaptcha_site_key');
        $secret = config('asl.public_site.recaptcha_secret_key');

        return is_string($site) && $site !== '' && is_string($secret) && $secret !== '';
    }

    public function path(): string
    {
        $configured = config('asl.public_site.maintenance_file');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return storage_path('app/public-site-maintenance');
    }

    public function contactFormPath(): string
    {
        return dirname($this->path()).'/public-site-contact-form';
    }

    public function recaptchaPath(): string
    {
        return dirname($this->path()).'/public-site-recaptcha';
    }

    public function recaptchaKeysPath(): string
    {
        return dirname($this->path()).'/public-site-recaptcha-keys';
    }

    public function sharedWithPublicSite(): bool
    {
        $configured = config('asl.public_site.maintenance_file');

        return is_string($configured) && $configured !== '';
    }

    /**
     * @return array{maintenance_enabled: bool, contact_form_enabled: bool, recaptcha_enabled: bool, written: bool, keys_written: bool, recaptcha_keys_present: bool}
     */
    public function save(bool $maintenance, bool $contactForm, bool $recaptcha, User $actor): array
    {
        $row = $this->row() ?? new PublicSiteControl;
        $row->maintenance_enabled = $maintenance;
        $row->contact_form_enabled = $contactForm;
        $row->recaptcha_enabled = $recaptcha;
        $row->updated_by = $actor->getKey();
        $row->save();

        $written = $this->writeFlag($this->path(), $maintenance)
            && $this->writeFlag($this->contactFormPath(), $contactForm)
            && $this->writeFlag($this->recaptchaPath(), $recaptcha);

        return [
            'maintenance_enabled' => $maintenance,
            'contact_form_enabled' => $contactForm,
            'recaptcha_enabled' => $recaptcha,
            'written' => $written,
            'keys_written' => $this->sharedWithPublicSite() ? $this->writeKeys() : true,
            'recaptcha_keys_present' => $this->recaptchaKeysPresent(),
        ];
    }

    private function row(): ?PublicSiteControl
    {
        if (! Schema::hasTable('public_site_controls')) {
            return null;
        }

        $row = PublicSiteControl::query()->orderBy('id')->first();

        return $row instanceof PublicSiteControl ? $row : null;
    }

    private function writeFlag(string $path, bool $enabled): bool
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return false;
        }

        return file_put_contents($path, $enabled ? "1\n" : "0\n", LOCK_EX) !== false;
    }

    private function writeKeys(): bool
    {
        $path = $this->recaptchaKeysPath();

        if (! $this->recaptchaKeysPresent()) {
            return ! is_file($path) || unlink($path);
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return false;
        }

        $payload = json_encode([
            'site' => (string) config('asl.public_site.recaptcha_site_key'),
            'secret' => (string) config('asl.public_site.recaptcha_secret_key'),
        ], JSON_UNESCAPED_SLASHES);

        if (! is_string($payload) || file_put_contents($path, $payload, LOCK_EX) === false) {
            return false;
        }

        return chmod($path, 0600);
    }
}
