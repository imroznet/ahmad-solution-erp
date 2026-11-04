<?php

namespace App\Company;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Writes a random filename on the private local disk.
 * The original filename is kept only as display text.
 */
final class PrivateFileStore
{
    /**
     * @var list<string>
     */
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'exe', 'js', 'html', 'htm', 'svg', 'sh', 'bat', 'cmd',
    ];

    public function store(UploadedFile $file, string $directory, bool $logo = false): StoredUpload
    {
        if (! preg_match('/^(company-logo|company-documents\/[1-9][0-9]*)$/', $directory)) {
            throw ValidationException::withMessages([
                'files' => 'The upload directory is not allowed.',
            ]);
        }

        $original = $this->displayName($file->getClientOriginalName());
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowedExtensions = $logo
            ? $this->stringList('asl.documents.logo_extensions')
            : $this->stringList('asl.documents.extensions');
        $allowedMimes = $logo
            ? $this->stringList('asl.documents.logo_mimes')
            : $this->stringList('asl.documents.mimes');
        $maxBytes = ($logo
            ? (int) config('asl.documents.logo_max_kilobytes')
            : (int) config('asl.documents.max_kilobytes')) * 1024;

        $parts = array_values(array_filter(explode('.', strtolower($original))));

        foreach ($parts as $part) {
            if (in_array($part, self::BLOCKED_EXTENSIONS, true)) {
                throw ValidationException::withMessages([
                    'files' => 'This file type is not allowed.',
                ]);
            }
        }

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'files' => 'This file type is not allowed.',
            ]);
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'files' => 'This file type is not allowed.',
            ]);
        }

        $size = $file->getSize();

        if ($size === false || $size < 1 || $size > $maxBytes) {
            throw ValidationException::withMessages([
                'files' => 'The file is empty or larger than the configured limit.',
            ]);
        }

        $filename = Str::uuid()->toString().'.'.$extension;

        if (! preg_match('/^[0-9a-f-]{36}\.[a-z0-9]+$/', $filename)) {
            throw ValidationException::withMessages([
                'files' => 'The file could not be stored.',
            ]);
        }

        $path = $file->storeAs($directory, $filename, ['disk' => 'local']);

        if (! is_string($path) || str_contains($path, '..') || ! str_starts_with($path, $directory.'/')) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }

            throw ValidationException::withMessages([
                'files' => 'The file could not be stored.',
            ]);
        }

        return new StoredUpload($path, $original, $mime, $extension, $size);
    }

    public function delete(string $path): void
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            return;
        }

        Storage::disk('local')->delete($path);
    }

    /**
     * @return list<string>
     */
    private function stringList(string $key): array
    {
        $value = config($key);

        if (! is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $list[] = $item;
            }
        }

        return $list;
    }

    private function displayName(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $cleaned = preg_replace('/[^A-Za-z0-9._ -]/', '_', $base) ?? 'file';
        $cleaned = trim($cleaned, ". \t");

        if ($cleaned === '') {
            $cleaned = 'file';
        }

        return mb_substr($cleaned, 0, 180);
    }
}
