<?php

namespace App\Documents;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Retries a document write when two issuers take the same number,
 * and retries a deadlock or lock wait inside the database transaction.
 */
final class DocumentTransaction
{
    public function run(callable $callback, string $uniqueFragment): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                return DB::transaction($callback, 5);
            } catch (UniqueConstraintViolationException $exception) {
                $attempt++;

                if ($attempt >= 5 || ! str_contains($exception->getMessage(), $uniqueFragment)) {
                    throw $exception;
                }
            }
        }
    }
}
