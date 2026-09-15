<?php

declare(strict_types=1);

use ErvinsVilumsons\LaravelAlert\Support\Filesystem;
use ErvinsVilumsons\LaravelAlert\Support\Throttle;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Throttle::reset();
    $this->clearThrottleDir();
});

afterEach(function (): void {
    $this->clearThrottleDir();
    Mockery::close();
});

it('returns true without touching cache when ttl is non-positive', function (int $ttl): void {
    Cache::shouldReceive('add')->never();
    Cache::shouldReceive('forget')->never();

    expect(Throttle::acquire('alert:noop-'.$ttl, $ttl))->toBeTrue();

    Throttle::release('alert:noop-'.$ttl, $ttl);
})->with([0, -1]);

it('returns true when cache add succeeds', function (): void {
    Cache::shouldReceive('add')
        ->once()
        ->andReturn(true);

    expect(Throttle::acquire('alert:cache-hit', 60))->toBeTrue();
});

it('returns false when cache add reports the key already exists', function (): void {
    Cache::shouldReceive('add')
        ->once()
        ->andReturn(false);

    expect(Throttle::acquire('alert:cache-exists', 60))->toBeFalse();
});

it('falls back to the filesystem when cache throws', function (): void {
    Cache::shouldReceive('add')
        ->once()
        ->andThrow(new RuntimeException('cache down'));

    $key = 'alert:fs-'.uniqid('', true);

    expect(Throttle::acquire($key, 60))->toBeTrue();
    expect(Throttle::acquire($key, 60))->toBeFalse();
});

it('continues to use the filesystem while the cache breaker is open', function (): void {
    Cache::shouldReceive('add')
        ->once()
        ->andThrow(new RuntimeException('cache down'));

    $a = 'alert:breaker-a-'.uniqid('', true);
    $b = 'alert:breaker-b-'.uniqid('', true);

    expect(Throttle::acquire($a, 60))->toBeTrue();
    expect(Throttle::acquire($b, 60))->toBeTrue();
});

it('releases the filesystem lock so a subsequent acquire succeeds', function (): void {
    Cache::shouldReceive('add')
        ->once()
        ->andThrow(new RuntimeException('cache down'));

    Cache::shouldReceive('forget')
        ->never();

    $key = 'alert:fs-release-'.uniqid('', true);

    expect(Throttle::acquire($key, 60))->toBeTrue();
    expect(Throttle::acquire($key, 60))->toBeFalse();

    Throttle::release($key, 60);

    expect(Throttle::acquire($key, 60))->toBeTrue();
});

it('falls back to in-process throttle when the filesystem path cannot be created', function (): void {
    $dir = $this->throttleDir();

    @mkdir(dirname($dir), 0775, true);
    file_put_contents($dir, '');

    Cache::shouldReceive('add')
        ->once()
        ->andThrow(new RuntimeException('cache down'));

    $key = 'alert:local-'.uniqid('', true);

    try {
        expect(Throttle::acquire($key, 60))->toBeTrue();
        expect(Throttle::acquire($key, 60))->toBeFalse();
    } finally {
        @unlink($dir);
    }
});

it('clears the local entry on release', function (): void {
    $dir = $this->throttleDir();

    @mkdir(dirname($dir), 0775, true);
    file_put_contents($dir, '');

    Cache::shouldReceive('add')
        ->once()
        ->andThrow(new RuntimeException('cache down'));

    Cache::shouldReceive('forget')
        ->never();

    $key = 'alert:local-release-'.uniqid('', true);

    try {
        expect(Throttle::acquire($key, 60))->toBeTrue();

        Throttle::release($key, 60);

        expect(Throttle::acquire($key, 60))->toBeTrue();
    } finally {
        @unlink($dir);
    }
});

it('falls back to in-process throttle when opening the lock file fails', function (): void {
    $dir = $this->throttleDir();

    @mkdir($dir, 0775, true);

    $key = 'alert:fopen-fail-'.uniqid('', true);
    $lockPath = $dir.'/'.md5($key).'.lock';

    @mkdir($lockPath, 0775);

    Cache::shouldReceive('add')
        ->once()
        ->andThrow(new RuntimeException('cache down'));

    try {
        expect(Throttle::acquire($key, 60))->toBeTrue();
        expect(Throttle::acquire($key, 60))->toBeFalse();
    } finally {
        @rmdir($lockPath);
    }
});

it('forgets the cache key on release when the cache is healthy', function (): void {
    Cache::shouldReceive('add')
        ->once()
        ->andReturn(true);

    Cache::shouldReceive('forget')
        ->once()
        ->with('alert:healthy-release')
        ->andReturn(true);

    expect(Throttle::acquire('alert:healthy-release', 60))->toBeTrue();

    Throttle::release('alert:healthy-release', 60);
});

it('does not propagate cache forget failures', function (): void {
    Cache::shouldReceive('add')
        ->once()
        ->andReturn(true);

    Cache::shouldReceive('forget')
        ->once()
        ->andThrow(new RuntimeException('cache down'));

    expect(Throttle::acquire('alert:forget-throws', 60))->toBeTrue();

    Throttle::release('alert:forget-throws', 60);
});

it('skips filesystem release when the throttle directory is unavailable', function (): void {
    $dir = $this->throttleDir();

    @mkdir(dirname($dir), 0775, true);
    file_put_contents($dir, '');

    Cache::shouldReceive('forget')
        ->once()
        ->andReturn(true);

    try {
        Throttle::release('alert:no-dir-release', 60);
    } finally {
        @unlink($dir);
    }
});

it('falls back to in-process throttle when filesystem is not writable', function (): void {
    $filesystem = Mockery::mock(Filesystem::class);

    $filesystem->shouldReceive('ensureDirectoryExists')
        ->once()
        ->andReturnTrue();

    $filesystem->shouldReceive('isWritable')
        ->once()
        ->andReturnFalse();

    app()->instance(Filesystem::class, $filesystem);

    Cache::shouldReceive('add')
        ->once()
        ->andThrow(new RuntimeException('cache down'));

    $key = 'alert:not-writable-'.uniqid('', true);

    expect(Throttle::acquire($key, 60))->toBeTrue();
    expect(Throttle::acquire($key, 60))->toBeFalse();
});

it('falls back to in-process throttle when filesystem locking fails', function (): void {
    $dir = $this->throttleDir();

    mkdir($dir, 0775, true);

    $filesystem = Mockery::mock(Filesystem::class, [$this->throttleDir()])->makePartial();

    $filesystem->shouldReceive('lockExclusive')
        ->once()
        ->andReturnFalse();

    app()->instance(
        Filesystem::class,
        $filesystem
    );

    Cache::shouldReceive('add')
        ->once()
        ->andThrow(new RuntimeException('cache down'));

    $key = 'alert:lock-fail-'.uniqid('', true);

    try {
        expect(Throttle::acquire($key, 60))->toBeTrue();
        expect(Throttle::acquire($key, 60))->toBeFalse();
    } finally {
        app()->forgetInstance(
            Filesystem::class
        );
    }
});
