<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = dirname(__DIR__).'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
    if (is_file($file)) require $file;
});

$tests = [];
function test(string $name, callable $callback): void { global $tests; $tests[$name] = $callback; }
function assertSame(mixed $expected, mixed $actual): void {
    if ($expected !== $actual) throw new RuntimeException('Esperado '.var_export($expected, true).', recebido '.var_export($actual, true));
}
function assertTrue(bool $actual, string $message = 'A condição deveria ser verdadeira.'): void { if (!$actual) throw new RuntimeException($message); }
function assertThrows(callable $callback, int $status, ?string $detail = null): void {
    try { $callback(); } catch (App\Domain\ApiException $error) {
        assertSame($status, $error->status);
        if ($detail !== null) assertTrue(str_contains($error->getMessage(), $detail), 'Mensagem de erro inesperada: '.$error->getMessage());
        return;
    }
    throw new RuntimeException('Era esperada uma ApiException.');
}

foreach (glob(__DIR__.'/Unit/*Test.php') as $file) require $file;
$failed = 0;
foreach ($tests as $name => $callback) {
    try { $callback(); echo "PASS {$name}\n"; }
    catch (Throwable $error) { $failed++; fwrite(STDERR, "FAIL {$name}: {$error->getMessage()}\n"); }
}
echo sprintf("%d test(s), %d failure(s)\n", count($tests), $failed);
exit($failed === 0 ? 0 : 1);
