<?php

declare(strict_types=1);

/**
 * Proves two MySQL connections cannot take the same quotation sequence.
 *
 * The worker holds the year row with SELECT ... FOR UPDATE, sets last_number
 * to 1, and keeps the transaction open. The parent then locks the same row.
 * It must wait, read 1, and store 2. Point DB_DATABASE at an empty database.
 * This script refuses asl_erp so it cannot touch the application database.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD');
$database = getenv('DB_DATABASE') ?: '';

if ($user === '' || ! is_string($password) || $database === '' || $database === 'asl_erp') {
    fwrite(STDERR, "Set DB_USERNAME, DB_PASSWORD, and a disposable DB_DATABASE. Refusing asl_erp.\n");
    exit(1);
}

$pdo = quotationLockConnection($host, $port, $database, $user, $password);

if (in_array('--worker', $argv, true)) {
    quotationLockWorker($pdo);
    exit(0);
}

$pdo->exec('DROP TABLE IF EXISTS quotation_number_sequences');
$pdo->exec(<<<'SQL'
CREATE TABLE quotation_number_sequences (
  year smallint unsigned NOT NULL,
  last_number int unsigned NOT NULL DEFAULT 0,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
$pdo->exec('INSERT INTO quotation_number_sequences (year, last_number) VALUES (2026, 0)');

$command = [PHP_BINARY, __FILE__, '--worker'];
$process = proc_open($command, [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
], $pipes, null, quotationLockEnvironment($host, $port, $database, $user, $password));

if (! is_resource($process)) {
    fwrite(STDERR, "Could not start the lock worker.\n");
    exit(1);
}

fclose($pipes[0]);
stream_set_blocking($pipes[1], false);
$ready = '';
$deadline = microtime(true) + 10;

while (microtime(true) < $deadline && ! str_contains($ready, "locked\n")) {
    $ready .= (string) stream_get_contents($pipes[1]);
    usleep(50000);
}

if (! str_contains($ready, "locked\n")) {
    $error = stream_get_contents($pipes[2]);
    fwrite(STDERR, "Worker did not lock the sequence.\n{$ready}\n{$error}\n");
    proc_terminate($process);
    exit(1);
}

$started = microtime(true);
$pdo->beginTransaction();
$row = $pdo->query('SELECT last_number FROM quotation_number_sequences WHERE year = 2026 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
$elapsed = microtime(true) - $started;
$lastNumber = is_array($row) ? (int) $row['last_number'] : -1;

if ($lastNumber !== 1) {
    $pdo->rollBack();
    fwrite(STDERR, "Expected last_number 1 after the waiting lock, got {$lastNumber}.\n");
    proc_terminate($process);
    exit(1);
}

$pdo->exec('UPDATE quotation_number_sequences SET last_number = 2 WHERE year = 2026');
$pdo->commit();

stream_set_blocking($pipes[1], true);
stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
$code = proc_close($process);

if ($code !== 0) {
    fwrite(STDERR, "Worker exited {$code}.\n{$error}\n");
    exit(1);
}

if ($elapsed < 2.0) {
    fwrite(STDERR, "The second connection did not wait for the row lock. Elapsed {$elapsed} seconds.\n");
    exit(1);
}

$final = (int) $pdo->query('SELECT last_number FROM quotation_number_sequences WHERE year = 2026')->fetchColumn();

if ($final !== 2) {
    fwrite(STDERR, "Expected last_number 2, got {$final}.\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Quotation number lock held for %.2f seconds. Sequence ended at 2.\n", $elapsed));

function quotationLockWorker(PDO $pdo): void
{
    $pdo->beginTransaction();
    $pdo->query('SELECT last_number FROM quotation_number_sequences WHERE year = 2026 FOR UPDATE')->fetch();
    $pdo->exec('UPDATE quotation_number_sequences SET last_number = 1 WHERE year = 2026');
    fwrite(STDOUT, "locked\n");
    fflush(STDOUT);
    sleep(3);
    $pdo->commit();
    fwrite(STDOUT, "released\n");
    fflush(STDOUT);
}

function quotationLockConnection(string $host, string $port, string $database, string $user, string $password): PDO
{
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 15,
        ],
    );
}

/**
 * @return array<string, string>
 */
function quotationLockEnvironment(string $host, string $port, string $database, string $user, string $password): array
{
    $environment = [];

    foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $environment[$key] = $value;
        }
    }

    $environment['DB_HOST'] = $host;
    $environment['DB_PORT'] = $port;
    $environment['DB_DATABASE'] = $database;
    $environment['DB_USERNAME'] = $user;
    $environment['DB_PASSWORD'] = $password;

    return $environment;
}
