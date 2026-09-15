<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert\Commands;

use Throwable;

/*
 * The command calls unlink() without a leading backslash.
 *
 * This namespaced function lets us exercise the Throwable branch while
 * delegating all normal unlink calls to PHP's real unlink().
 */
function unlink(string $filename): bool
{
    if (str_ends_with($filename, '/throw.lock')) {
        throw new \RuntimeException('Test unlink failure.');
    }

    return \unlink($filename);
}

beforeEach(function () {
    $this->clearThrottleDir();
});

afterEach(function () {
    $this->clearThrottleDir();
});

it('succeeds when the directory is empty', function () {
    mkdir($this->throttleDir(), 0775, true);

    $this->artisanTest('alert:prune-throttle', ['--force' => true])
        ->expectsOutput('Throttle directory is already empty.')
        ->assertSuccessful();
});

it('deletes all lock files with --force', function () {
    $paths = $this->seedLockFiles(3);

    $this->artisanTest('alert:prune-throttle', ['--force' => true])
        ->expectsOutput('Deleted 3 file(s).')
        ->assertSuccessful();

    foreach ($paths as $path) {
        expect($path)->not->toBeFile();
    }
});

it('asks for confirmation without --force', function (): void {
    $paths = $this->seedLockFiles(2);

    $this->artisanTest('alert:prune-throttle')
        ->expectsConfirmation('Delete 2 throttle lock file(s)?', 'yes')
        ->expectsOutput('Deleted 2 file(s).')
        ->assertSuccessful();

    foreach ($paths as $path) {
        expect($path)->not->toBeFile();
    }
});

it('aborts when confirmation is declined', function (): void {
    $paths = $this->seedLockFiles(2);

    $this->artisanTest('alert:prune-throttle')
        ->expectsConfirmation('Delete 2 throttle lock file(s)?', 'no')
        ->expectsOutput('Aborted.')
        ->assertFailed();

    foreach ($paths as $path) {
        expect($path)->toBeFile();
    }
});

it('leaves non-lock files untouched', function () {
    $this->seedLockFiles(1);

    $otherFile = $this->throttleDir().'/README.txt';
    file_put_contents($otherFile, 'keep me');

    $this->artisanTest('alert:prune-throttle', ['--force' => true])
        ->expectsOutput('Deleted 1 file(s).')
        ->assertSuccessful();

    expect($otherFile)->toBeFile();
});

it('handles a mix of lock and non-lock files', function () {
    $this->seedLockFiles(4);

    file_put_contents($this->throttleDir().'/.gitignore', "*\n!.gitignore");
    file_put_contents($this->throttleDir().'/stray.log', 'nope');

    $this->artisanTest('alert:prune-throttle', ['--force' => true])
        ->expectsOutput('Deleted 4 file(s).')
        ->assertSuccessful();

    $remaining = array_values(array_diff(
        scandir($this->throttleDir()) ?: [],
        ['.', '..']
    ));

    sort($remaining);

    expect($remaining)->toBe(['.gitignore', 'stray.log']);
});

it('reports failure when a lock path cannot be unlinked', function () {
    $failedPath = $this->throttleDir().'/cannot-delete.lock';

    /*
     * glob() includes directories matching *.lock, but unlink() cannot
     * remove a directory and returns false.
     */
    mkdir($failedPath, 0775, true);

    $this->artisanTest('alert:prune-throttle', ['--force' => true])
        ->expectsOutput('Deleted 0 file(s).')
        ->expectsOutput('1 file(s) could not be deleted.')
        ->assertFailed();

    expect($failedPath)->toBeDirectory();
});

it('reports a warning when unlink throws an exception', function () {
    $dir = $this->throttleDir();

    mkdir($dir, 0775, true);

    $throwingPath = $dir.'/throw.lock';

    file_put_contents($throwingPath, 'test');

    $this->artisanTest('alert:prune-throttle', ['--force' => true])
        ->expectsOutput('Failed to delete '.$throwingPath.': Test unlink failure.')
        ->expectsOutput('Deleted 0 file(s).')
        ->expectsOutput('1 file(s) could not be deleted.')
        ->assertFailed();

    expect($throwingPath)->toBeFile();
});
