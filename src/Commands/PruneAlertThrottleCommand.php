<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert\Commands;

use ErvinsVilumsons\LaravelAlert\Support\Filesystem;
use Illuminate\Console\Command;
use Throwable;

final class PruneAlertThrottleCommand extends Command
{
    protected $signature = 'alert:prune-throttle {--force : Skip confirmation prompt}';

    protected $description = 'Remove all lock files from the alert-throttle cache directory.';

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {

        $files = glob($this->filesystem->directory.'/*.lock') ?: [];

        if ($files === []) {
            $this->info('Throttle directory is already empty.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            sprintf('Delete %d throttle lock file(s)?', count($files))
        )) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $deleted = 0;
        $failed = 0;

        foreach ($files as $file) {
            try {
                if (@unlink($file)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $failed++;
                $this->warn("Failed to delete {$file}: {$e->getMessage()}");
            }
        }

        $this->info("Deleted {$deleted} file(s).");

        if ($failed > 0) {
            $this->warn("{$failed} file(s) could not be deleted.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
