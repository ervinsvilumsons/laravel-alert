<?php

declare(strict_types=1);

use ErvinsVilumsons\LaravelAlert\Support\Filesystem;

beforeEach(function (): void {
    $this->clearThrottleDir();
});

afterEach(function (): void {
    $this->clearThrottleDir();
});

it('creates the throttle directory when it does not exist', function (): void {
    $filesystem = new Filesystem;

    expect(is_dir($filesystem->directory))->toBeFalse();

    expect($filesystem->ensureDirectoryExists())->toBeTrue();
    expect($filesystem->directory)->toBeDirectory();
});

it('returns true when the throttle directory already exists', function (): void {
    $dir = $this->throttleDir();

    mkdir($dir, 0775, true);

    $filesystem = new Filesystem;

    expect($filesystem->ensureDirectoryExists())->toBeTrue();
    expect($filesystem->directory)->toBe($dir);
});

it('returns false when the throttle directory cannot be created', function (): void {
    $dir = $this->throttleDir();

    /*
     * Make the parent path a regular file. This means mkdir() cannot
     * create the configured throttle directory.
     */
    $parent = dirname($dir);

    @mkdir($parent, 0775, true);

    $blocker = $parent.'/blocker';
    file_put_contents($blocker, '');

    $originalDirectory = $dir;

    /*
     * Temporarily point the configured directory below the regular file.
     */
    config([
        'alert-manager.throttle.path' => $blocker.'/throttle',
    ]);

    try {
        $filesystem = new Filesystem;

        expect($filesystem->ensureDirectoryExists())->toBeFalse();
    } finally {
        config([
            'alert-manager.throttle.path' => $originalDirectory,
        ]);

        @unlink($blocker);
    }
});

it('opens a file', function (): void {
    $filesystem = new Filesystem;

    mkdir($filesystem->directory, 0775, true);

    $path = $filesystem->directory.'/test.lock';
    $handle = $filesystem->open($path, 'c+');

    if ($handle === false) {
        throw new RuntimeException('Failed to open test file.');
    }

    expect(is_resource($handle))->toBeTrue();

    $filesystem->close($handle);
});

it('returns false when opening an invalid path fails', function (): void {
    $filesystem = new Filesystem;

    $handle = $filesystem->open(
        $filesystem->directory.'/missing/test.lock',
        'c+'
    );

    expect($handle)->toBeFalse();
});

it('locks and unlocks a file exclusively', function (): void {
    $filesystem = new Filesystem;

    mkdir($filesystem->directory, 0775, true);

    $path = $filesystem->directory.'/lock-test.lock';
    $handle = $filesystem->open($path, 'c+');

    if ($handle === false) {
        throw new RuntimeException('Failed to open lock file.');
    }

    expect($filesystem->lockExclusive($handle))->toBeTrue();
    expect($filesystem->unlock($handle))->toBeTrue();

    $filesystem->close($handle);
});

it('truncates, writes, and flushes a file', function (): void {
    $filesystem = new Filesystem;

    mkdir($filesystem->directory, 0775, true);

    $path = $filesystem->directory.'/write-test.lock';
    $handle = $filesystem->open($path, 'c+');

    if ($handle === false) {
        throw new RuntimeException('Failed to open write file.');
    }

    expect($filesystem->write($handle, 'hello world'))->toBe(11);
    expect($filesystem->flush($handle))->toBeTrue();
    expect($filesystem->truncate($handle, 5))->toBeTrue();

    $filesystem->close($handle);

    expect(file_get_contents($path))->toBe('hello');
});

it('deletes a file', function (): void {
    $filesystem = new Filesystem;

    mkdir($filesystem->directory, 0775, true);

    $path = $filesystem->directory.'/delete-test.lock';
    file_put_contents($path, 'test');

    expect($path)->toBeFile();

    $filesystem->delete($path);

    expect($path)->not->toBeFile();
});

it('does nothing when deleting a path that is not a file', function (): void {
    $filesystem = new Filesystem;

    mkdir($filesystem->directory, 0775, true);

    $path = $filesystem->directory.'/missing.lock';

    $filesystem->delete($path);

    expect($path)->not->toBeFile();
});
