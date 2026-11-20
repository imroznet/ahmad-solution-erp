<?php

namespace App\PublicSite;

use App\Models\PublicSiteControl;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * The public website is a separate application. It reads one flag file.
 * This ERP stays available either way. A missing row means the site is visible.
 */
final class PublicSiteMaintenance
{
    public function enabled(): bool
    {
        $row = $this->row();

        return $row instanceof PublicSiteControl && $row->maintenance_enabled;
    }

    public function path(): string
    {
        $configured = config('asl.public_site.maintenance_file');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return storage_path('app/public-site-maintenance');
    }

    public function sharedWithPublicSite(): bool
    {
        $configured = config('asl.public_site.maintenance_file');

        return is_string($configured) && $configured !== '';
    }

    /**
     * @return array{enabled: bool, written: bool}
     */
    public function save(bool $enabled, User $actor): array
    {
        $row = $this->row() ?? new PublicSiteControl;
        $row->maintenance_enabled = $enabled;
        $row->updated_by = $actor->getKey();
        $row->save();

        return [
            'enabled' => $enabled,
            'written' => $this->writeFlag($enabled),
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

    private function writeFlag(bool $enabled): bool
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return false;
        }

        return file_put_contents($path, $enabled ? "1\n" : "0\n", LOCK_EX) !== false;
    }
}
