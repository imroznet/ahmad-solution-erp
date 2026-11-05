<?php

declare(strict_types=1);

use App\Documents\ChallanWorkflow;
use App\Documents\ChallanWriter;
use App\Documents\DocumentNumberAllocator;
use App\Documents\InvoiceWorkflow;
use App\Documents\InvoiceWriter;
use App\Models\CommercialInvoice;
use App\Models\DeliveryChallan;
use App\Models\Party;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\User;
use App\Quotations\QuotationWorkflow;
use App\Quotations\QuotationWriter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Proves the first two challans and the first two commercial invoices of a
 * Dhaka year can be issued together.
 *
 * Each worker issues a document for a different accepted quotation, so the
 * quotation row lock does not hide the sequence race. Numbers are allocated
 * at issue. A failed allocation is rolled back and does not consume the next
 * number. Drafts are created by the parent and do not take a number.
 *
 * Point DB_DATABASE at an empty asl_document_* database. This script refuses
 * asl_erp so it cannot touch the application database.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$database = getenv('DB_DATABASE');

if (! is_string($database) || $database === 'asl_erp' || preg_match('/^asl_document_[a-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "DB_DATABASE must be a disposable asl_document_* database. Refusing asl_erp.\n");
    exit(1);
}

documentYearForceDatabase($database);
documentYearBootstrap($database);

if (in_array('--worker', $argv, true)) {
    documentYearWorker($argv);
    exit(0);
}

documentYearParent();
exit(0);

function documentYearParent(): void
{
    $migrated = Artisan::call('migrate:fresh', ['--force' => true]);

    if ($migrated !== 0) {
        fwrite(STDERR, Artisan::output());
        exit(1);
    }

    if ((int) DB::table('delivery_challan_number_sequences')->count() !== 0
        || (int) DB::table('commercial_invoice_number_sequences')->count() !== 0) {
        fwrite(STDERR, "A document sequence table was not empty before the first allocation.\n");
        exit(1);
    }

    $sales = User::query()->create([
        'name' => 'Document sales',
        'email' => 'document-sales@example.com',
        'password' => 'local-disposable-password',
        'is_active' => true,
    ]);
    $manager = User::query()->create([
        'name' => 'Document manager',
        'email' => 'document-manager@example.com',
        'password' => 'local-disposable-password',
        'is_active' => true,
    ]);
    $firstParty = documentYearParty('First Customer Ltd.', 'first-document@example.com', '01711000021');
    $secondParty = documentYearParty('Second Customer Ltd.', 'second-document@example.com', '01711000022');
    $firstQuotation = documentYearAccept($sales, $manager, $firstParty);
    $secondQuotation = documentYearAccept($sales, $manager, $secondParty);
    $firstChallan = documentYearChallan($sales, $firstQuotation);
    $secondChallan = documentYearChallan($sales, $secondQuotation);
    $directory = sys_get_temp_dir().'/asl-document-year-'.bin2hex(random_bytes(4));

    if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
        fwrite(STDERR, "Could not create {$directory}.\n");
        exit(1);
    }

    $year = (int) now()->timezone('Asia/Dhaka')->format('Y');
    $challanNumbers = documentYearRace($directory, 'challan', [
        'a' => (int) $firstChallan->getKey(),
        'b' => (int) $secondChallan->getKey(),
    ], (int) $sales->getKey());
    $expectedChallans = [sprintf('DC-%d-0001', $year), sprintf('DC-%d-0002', $year)];
    sort($challanNumbers);
    sort($expectedChallans);

    if ($challanNumbers !== $expectedChallans) {
        documentYearClean($directory);
        fwrite(STDERR, 'Expected '.implode(' and ', $expectedChallans).', got '.implode(' and ', $challanNumbers).".\n");
        exit(1);
    }

    if (DeliveryChallan::query()->whereNotNull('number')->count() !== 2
        || (int) DB::table('delivery_challan_number_sequences')->where('year', $year)->value('last_number') !== 2) {
        documentYearClean($directory);
        fwrite(STDERR, "The challan year sequence did not end at 2 after two issues.\n");
        exit(1);
    }

    $challanKept = documentYearRollback(DocumentNumberAllocator::CHALLAN, sprintf('DC-%d-0003', $year));

    if ((int) DB::table('delivery_challan_number_sequences')->where('year', $year)->value('last_number') !== 3
        || DeliveryChallan::query()->count() !== 2) {
        documentYearClean($directory);
        fwrite(STDERR, "The challan rollback check changed the stored challans.\n");
        exit(1);
    }

    $firstChallan->refresh();
    $secondChallan->refresh();
    $firstInvoice = documentYearInvoice($sales, $firstQuotation, $firstChallan);
    $secondInvoice = documentYearInvoice($sales, $secondQuotation, $secondChallan);
    $invoiceNumbers = documentYearRace($directory, 'invoice', [
        'c' => (int) $firstInvoice->getKey(),
        'd' => (int) $secondInvoice->getKey(),
    ], (int) $sales->getKey());
    $expectedInvoices = [sprintf('CI-%d-0001', $year), sprintf('CI-%d-0002', $year)];
    sort($invoiceNumbers);
    sort($expectedInvoices);

    if ($invoiceNumbers !== $expectedInvoices) {
        documentYearClean($directory);
        fwrite(STDERR, 'Expected '.implode(' and ', $expectedInvoices).', got '.implode(' and ', $invoiceNumbers).".\n");
        exit(1);
    }

    if (CommercialInvoice::query()->whereNotNull('number')->count() !== 2
        || (int) DB::table('commercial_invoice_number_sequences')->where('year', $year)->value('last_number') !== 2) {
        documentYearClean($directory);
        fwrite(STDERR, "The invoice year sequence did not end at 2 after two issues.\n");
        exit(1);
    }

    $invoiceKept = documentYearRollback(DocumentNumberAllocator::INVOICE, sprintf('CI-%d-0003', $year));

    if ((int) DB::table('commercial_invoice_number_sequences')->where('year', $year)->value('last_number') !== 3
        || CommercialInvoice::query()->count() !== 2) {
        documentYearClean($directory);
        fwrite(STDERR, "The invoice rollback check changed the stored invoices.\n");
        exit(1);
    }

    documentYearClean($directory);
    fwrite(STDOUT, "First {$year} allocations overlapped and returned {$expectedChallans[0]} and {$expectedChallans[1]}. Invoice allocations overlapped and returned {$expectedInvoices[0]} and {$expectedInvoices[1]}. A rolled-back allocation did not consume {$challanKept} or {$invoiceKept}.\n");
}

function documentYearAccept(User $sales, User $manager, Party $party): Quotation
{
    $today = now()->timezone('Asia/Dhaka');
    $quotation = app(QuotationWriter::class)->create($sales, [
        'party_id' => $party->getKey(),
        'quotation_date' => $today->toDateString(),
        'valid_until' => $today->copy()->addMonth()->toDateString(),
        'lines' => [[
            'description' => 'Concurrent delivery',
            'unit' => 'job',
            'quantity' => '1',
            'unit_price' => '10.00',
        ]],
    ]);
    $workflow = app(QuotationWorkflow::class);
    $workflow->submit($quotation, $sales);
    $workflow->approve($quotation, $manager);
    $workflow->send($quotation, $sales, 'Nadia Rahman', null);
    $workflow->respond($quotation, $sales, 'accepted', 'internal', 'Nadia Rahman', null, null, null, null, null);

    return $quotation->fresh() ?? $quotation;
}

function documentYearChallan(User $actor, Quotation $quotation): DeliveryChallan
{
    $quotation->load('currentRevision.lines');
    $line = $quotation->currentRevision?->lines->first();

    if (! $line instanceof QuotationLine) {
        fwrite(STDERR, "The accepted quotation has no line.\n");
        exit(1);
    }

    return app(ChallanWriter::class)->create($actor, [
        'quotation_id' => $quotation->getKey(),
        'challan_date' => now()->timezone('Asia/Dhaka')->toDateString(),
        'lines' => [[
            'quotation_line_id' => $line->getKey(),
            'quantity' => '1',
        ]],
    ]);
}

function documentYearInvoice(User $actor, Quotation $quotation, DeliveryChallan $challan): CommercialInvoice
{
    $challan->load('lines');
    $line = $challan->lines->first();

    if ($line === null) {
        fwrite(STDERR, "The issued challan has no line.\n");
        exit(1);
    }

    return app(InvoiceWriter::class)->create($actor, [
        'quotation_id' => $quotation->getKey(),
        'invoice_date' => now()->timezone('Asia/Dhaka')->toDateString(),
        'lines' => [[
            'delivery_challan_line_id' => $line->getKey(),
            'quantity' => '1',
        ]],
    ]);
}

/**
 * @param  array<string, int>  $documents
 * @return list<string>
 */
function documentYearRace(string $directory, string $series, array $documents, int $userId): array
{
    $go = $directory.'/go-'.$series;
    $workers = [];

    foreach ($documents as $name => $documentId) {
        $workers[] = documentYearSpawn($directory, $series, $name, $documentId, $userId, $go);
    }

    $readyDeadline = microtime(true) + 30;

    while (microtime(true) < $readyDeadline) {
        documentYearDrain($workers);
        $ready = true;

        foreach ($workers as $worker) {
            if (! is_file($worker['ready'])) {
                $ready = false;
            }
        }

        if ($ready) {
            break;
        }

        usleep(50000);
    }

    foreach ($workers as $worker) {
        if (! is_file($worker['ready'])) {
            documentYearStop($workers, 'A worker did not become ready.');
        }
    }

    file_put_contents($go, 'go');
    $finishedDeadline = microtime(true) + 30;
    $running = true;

    while ($running && microtime(true) < $finishedDeadline) {
        documentYearDrain($workers);
        $running = false;

        foreach ($workers as $worker) {
            $status = proc_get_status($worker['process']);

            if ($status['running']) {
                $running = true;
            }
        }

        if ($running) {
            usleep(50000);
        }
    }

    if ($running) {
        documentYearStop($workers, 'A worker did not finish.');
    }

    foreach ($workers as $index => $worker) {
        $workers[$index]['code'] = proc_close($worker['process']);
    }

    $numbers = [];

    foreach ($workers as $worker) {
        if ($worker['code'] !== 0) {
            documentYearClean($directory);
            fwrite(STDERR, "Worker {$worker['name']} exited {$worker['code']}.\n{$worker['output']}\n{$worker['error']}\n");
            exit(1);
        }

        $numbers[] = is_file($worker['result']) ? trim((string) file_get_contents($worker['result'])) : '';
        $start = (float) file_get_contents($worker['start']);
        $end = (float) file_get_contents($worker['end']);

        if ($start <= 0.0 || $end <= $start) {
            documentYearClean($directory);
            fwrite(STDERR, "Worker {$worker['name']} did not record an overlapping allocation.\n");
            exit(1);
        }
    }

    $firstStart = (float) file_get_contents($workers[0]['start']);
    $firstEnd = (float) file_get_contents($workers[0]['end']);
    $secondStart = (float) file_get_contents($workers[1]['start']);
    $secondEnd = (float) file_get_contents($workers[1]['end']);

    if (! ($firstStart < $secondEnd && $secondStart < $firstEnd)) {
        documentYearClean($directory);
        fwrite(STDERR, "The two {$series} issues did not overlap.\n");
        exit(1);
    }

    return $numbers;
}

function documentYearRollback(string $series, string $expected): string
{
    DB::beginTransaction();
    $rolledBack = app(DocumentNumberAllocator::class)->allocate($series);
    DB::rollBack();
    $kept = app(DocumentNumberAllocator::class)->allocate($series);

    if ($rolledBack !== $kept || $kept !== $expected) {
        fwrite(STDERR, "A rolled-back allocation consumed {$rolledBack}; the next allocation was {$kept}.\n");
        exit(1);
    }

    return $kept;
}

/**
 * @param  list<string>  $argv
 */
function documentYearWorker(array $argv): void
{
    $series = documentYearArgument($argv, 'series');
    $documentId = (int) documentYearArgument($argv, 'document');
    $userId = (int) documentYearArgument($argv, 'user');
    $ready = documentYearArgument($argv, 'ready');
    $go = documentYearArgument($argv, 'go');
    $start = documentYearArgument($argv, 'start');
    $end = documentYearArgument($argv, 'end');
    $result = documentYearArgument($argv, 'result');
    $user = User::query()->find($userId);

    if (! $user instanceof User) {
        fwrite(STDERR, "User {$userId} was not found.\n");
        exit(1);
    }

    file_put_contents($ready, 'ready');
    $deadline = microtime(true) + 30;

    while (! is_file($go)) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "Timed out waiting for the start signal.\n");
            exit(1);
        }

        usleep(20000);
    }

    try {
        file_put_contents($start, (string) microtime(true));

        if ($series === 'challan') {
            $challan = DeliveryChallan::query()->find($documentId);

            if (! $challan instanceof DeliveryChallan) {
                throw new RuntimeException("Challan {$documentId} was not found.");
            }

            $issued = app(ChallanWorkflow::class)->issue($challan, $user);
        } elseif ($series === 'invoice') {
            $invoice = CommercialInvoice::query()->find($documentId);

            if (! $invoice instanceof CommercialInvoice) {
                throw new RuntimeException("Invoice {$documentId} was not found.");
            }

            $issued = app(InvoiceWorkflow::class)->issue($invoice, $user);
        } else {
            throw new RuntimeException('Choose challan or invoice.');
        }

        file_put_contents($end, (string) microtime(true));
        file_put_contents($result, $issued->stringAttribute('number'));
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
        exit(1);
    }
}

function documentYearParty(string $name, string $email, string $phone): Party
{
    $party = Party::query()->create([
        'legal_name' => $name,
        'party_kind' => 'company',
        'status' => 'active',
        'currency_code' => 'BDT',
        'email' => $email,
        'phone' => $phone,
    ]);
    $party->roles()->create(['role' => 'customer']);

    return $party;
}

/**
 * @return array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}
 */
function documentYearSpawn(string $directory, string $series, string $name, int $documentId, int $userId, string $go): array
{
    $ready = $directory.'/'.$name.'.ready';
    $start = $directory.'/'.$name.'.start';
    $end = $directory.'/'.$name.'.end';
    $result = $directory.'/'.$name.'.result';
    $command = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        '--series='.$series,
        '--document='.$documentId,
        '--user='.$userId,
        '--ready='.$ready,
        '--go='.$go,
        '--start='.$start,
        '--end='.$end,
        '--result='.$result,
    ];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, documentYearChildEnvironment());

    if (! is_resource($process) || ! isset($pipes[1], $pipes[2]) || ! is_resource($pipes[1]) || ! is_resource($pipes[2])) {
        fwrite(STDERR, "Could not start worker {$name}.\n");
        exit(1);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    return [
        'name' => $name,
        'process' => $process,
        'stdout' => $pipes[1],
        'stderr' => $pipes[2],
        'ready' => $ready,
        'start' => $start,
        'end' => $end,
        'result' => $result,
        'code' => 1,
        'output' => '',
        'error' => '',
    ];
}

/**
 * @param  list<array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}>  $workers
 */
function documentYearDrain(array &$workers): void
{
    foreach ($workers as $index => $worker) {
        $stdout = stream_get_contents($worker['stdout']);
        $stderr = stream_get_contents($worker['stderr']);
        $workers[$index]['output'] .= is_string($stdout) ? $stdout : '';
        $workers[$index]['error'] .= is_string($stderr) ? $stderr : '';
    }
}

/**
 * @param  list<array{name: string, process: resource, stdout: resource, stderr: resource, ready: string, start: string, end: string, result: string, code: int, output: string, error: string}>  $workers
 */
function documentYearStop(array $workers, string $message): never
{
    documentYearDrain($workers);

    foreach ($workers as $worker) {
        proc_terminate($worker['process']);
        fwrite(STDERR, "Worker {$worker['name']} output:\n{$worker['output']}\n{$worker['error']}\n");
    }

    fwrite(STDERR, $message."\n");
    exit(1);
}

function documentYearClean(string $directory): void
{
    foreach (glob($directory.'/*') ?: [] as $file) {
        if (is_string($file) && is_file($file)) {
            unlink($file);
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
}

/**
 * @param  list<string>  $argv
 */
function documentYearArgument(array $argv, string $name): string
{
    $prefix = '--'.$name.'=';

    foreach ($argv as $argument) {
        if (str_starts_with($argument, $prefix)) {
            return substr($argument, strlen($prefix));
        }
    }

    fwrite(STDERR, "Missing --{$name}.\n");
    exit(1);
}

function documentYearForceDatabase(string $database): void
{
    documentYearSetEnv('DB_CONNECTION', 'mysql');
    documentYearSetEnv('DB_DATABASE', $database);
    putenv('DB_URL');
    unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
    putenv('ASL_DOCUMENT_ALLOCATION_DELAY');
    unset($_ENV['ASL_DOCUMENT_ALLOCATION_DELAY'], $_SERVER['ASL_DOCUMENT_ALLOCATION_DELAY']);
    putenv('ASL_QUOTATION_ALLOCATION_DELAY');
    unset($_ENV['ASL_QUOTATION_ALLOCATION_DELAY'], $_SERVER['ASL_QUOTATION_ALLOCATION_DELAY']);
}

function documentYearSetEnv(string $key, string $value): void
{
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function documentYearBootstrap(string $database): void
{
    require_once dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $configured = config('database.connections.mysql.database');

    if (config('database.default') !== 'mysql' || $configured !== $database) {
        fwrite(STDERR, "Refusing to continue because the application database is not the disposable {$database} database.\n");
        exit(1);
    }
}

/**
 * @return array<string, string>
 */
function documentYearChildEnvironment(): array
{
    $environment = [];
    $raw = file_get_contents('/proc/self/environ');

    if (is_string($raw)) {
        foreach (explode("\0", $raw) as $entry) {
            if ($entry === '' || ! str_contains($entry, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $entry, 2);
            $environment[$key] = $value;
        }
    }

    $database = getenv('DB_DATABASE');
    $environment['DB_CONNECTION'] = 'mysql';
    $environment['DB_DATABASE'] = is_string($database) ? $database : '';
    $environment['ASL_DOCUMENT_ALLOCATION_DELAY'] = '1';
    unset($environment['DB_URL'], $environment['ASL_QUOTATION_ALLOCATION_DELAY']);

    return $environment;
}
