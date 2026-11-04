<?php

declare(strict_types=1);

use App\Models\Party;
use App\Models\User;
use App\Quotations\QuotationCalculator;
use App\Quotations\QuotationRuleException;
use App\Quotations\QuotationWriter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Proves MySQL 8.4 stores a maximum fitting quotation total exactly and
 * rejects a total above DECIMAL(18,2). Point DB_DATABASE at an empty
 * asl_quotation_* database. This script refuses asl_erp.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$database = getenv('DB_DATABASE');

if (! is_string($database) || $database === 'asl_erp' || preg_match('/^asl_quotation_[a-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "DB_DATABASE must be a disposable asl_quotation_* database. Refusing asl_erp.\n");
    exit(1);
}

quotationTotalSetEnv('DB_CONNECTION', 'mysql');
quotationTotalSetEnv('DB_DATABASE', $database);
putenv('DB_URL');
unset($_ENV['DB_URL'], $_SERVER['DB_URL']);

require_once dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$configured = config('database.connections.mysql.database');

if (config('database.default') !== 'mysql' || $configured !== $database) {
    fwrite(STDERR, "Refusing to continue because the application database is not the disposable {$database} database.\n");
    exit(1);
}

$migrated = Artisan::call('migrate:fresh', ['--force' => true]);

if ($migrated !== 0) {
    fwrite(STDERR, Artisan::output());
    exit(1);
}

DB::statement("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
$mode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode');
$modeValue = is_object($mode) ? (string) $mode->mode : '';

if (! str_contains($modeValue, 'STRICT_TRANS_TABLES')) {
    fwrite(STDERR, "STRICT_TRANS_TABLES is not enabled.\n");
    exit(1);
}

$user = User::query()->create([
    'name' => 'Quotation totals',
    'email' => 'totals@example.com',
    'password' => 'local-disposable-password',
    'is_active' => true,
]);
$party = Party::query()->create([
    'legal_name' => 'Totals Customer Ltd.',
    'party_kind' => 'company',
    'status' => 'active',
    'currency_code' => 'BDT',
    'email' => 'totals-customer@example.com',
    'phone' => '01711000021',
]);
$party->roles()->create(['role' => 'customer']);

$line = [
    'description' => 'Maximum line',
    'unit' => 'job',
    'quantity' => '9999999.9999',
    'unit_price' => '99999999.99',
];
$writer = app(QuotationWriter::class);
$quotation = $writer->create($user, quotationTotalInput((int) $party->getKey(), array_fill(0, 10, $line)));
$revisionId = (int) $quotation->current_revision_id;
$fitting = '9999999998900000.00';

if (quotationTotalAmount($revisionId, 'subtotal') !== $fitting || quotationTotalAmount($revisionId, 'total') !== $fitting) {
    fwrite(STDERR, 'Expected the stored total to be '.$fitting.' and read '.quotationTotalAmount($revisionId, 'total').".\n");
    exit(1);
}

try {
    $writer->create($user, quotationTotalInput((int) $party->getKey(), array_fill(0, 11, $line)));
    fwrite(STDERR, "Eleven maximum lines were stored.\n");
    exit(1);
} catch (QuotationRuleException $exception) {
    if ($exception->field !== 'lines' || ! str_contains($exception->getMessage(), '10999999998790000.00')) {
        fwrite(STDERR, $exception->getMessage()."\n");
        exit(1);
    }
}

if ((int) DB::table('quotations')->count() !== 1) {
    fwrite(STDERR, "The rejected total changed the stored quotations.\n");
    exit(1);
}

DB::statement('UPDATE quotation_revisions SET total = 9999999999999999.99 WHERE id = '.$revisionId);
$maximum = quotationTotalAmount($revisionId, 'total');

if ($maximum !== QuotationCalculator::TOTAL_MAX) {
    fwrite(STDERR, 'Expected the column maximum to round-trip as '.QuotationCalculator::TOTAL_MAX.", got {$maximum}.\n");
    exit(1);
}

try {
    DB::statement('UPDATE quotation_revisions SET total = 10000000000000000.00 WHERE id = '.$revisionId);
    fwrite(STDERR, "MySQL stored a total above DECIMAL(18,2).\n");
    exit(1);
} catch (QueryException $exception) {
    $message = $exception->getMessage();

    if (! str_contains($message, '1264') && ! str_contains($message, '22003') && ! str_contains($message, 'Out of range')) {
        fwrite(STDERR, $message."\n");
        exit(1);
    }
}

$unchanged = quotationTotalAmount($revisionId, 'total');

if ($unchanged !== QuotationCalculator::TOTAL_MAX || quotationTotalAmount($revisionId, 'subtotal') !== $fitting) {
    fwrite(STDERR, "The rejected update changed the stored total to {$unchanged}.\n");
    exit(1);
}

fwrite(STDOUT, "MySQL stored {$fitting} exactly, accepted ".QuotationCalculator::TOTAL_MAX.", and rejected 10000000000000000.00.\n");
exit(0);

/**
 * @param  list<array{description: string, unit: string, quantity: string, unit_price: string}>  $lines
 * @return array<string, mixed>
 */
function quotationTotalInput(int $partyId, array $lines): array
{
    return [
        'party_id' => $partyId,
        'quotation_date' => '2026-11-04',
        'valid_until' => '2026-12-04',
        'lines' => $lines,
    ];
}

function quotationTotalAmount(int $revisionId, string $column): string
{
    if (! in_array($column, ['subtotal', 'total'], true)) {
        fwrite(STDERR, "Unexpected money column.\n");
        exit(1);
    }

    $row = DB::selectOne('SELECT CAST('.$column.' AS CHAR) AS amount FROM quotation_revisions WHERE id = ?', [$revisionId]);
    $amount = is_object($row) ? trim((string) $row->amount) : '';

    return $amount;
}

function quotationTotalSetEnv(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
