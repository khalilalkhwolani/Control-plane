<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LicenseRevision;
use Illuminate\Console\Command;
use RuntimeException;

class ExportLicenseCommand extends Command
{
    protected $signature = 'control-center:license:export {license : License revision ID} {output : Destination JSON file}';

    protected $description = 'Export an already-issued signed license document without changing it.';

    public function handle(): int
    {
        $revision = LicenseRevision::query()->findOrFail((int) $this->argument('license'));
        $output = (string) $this->argument('output');

        if (is_file($output)) {
            $this->error('Refusing to overwrite an existing file.');

            return self::FAILURE;
        }

        $directory = dirname($output);
        if (! is_dir($directory)) {
            throw new RuntimeException('The destination directory does not exist.');
        }

        if (file_put_contents($output, (string) $revision->getRawOriginal('signed_document'), LOCK_EX) === false) {
            throw new RuntimeException('Could not export the signed license document.');
        }

        $this->info("License revision {$revision->sequence} exported to {$output}");

        return self::SUCCESS;
    }
}
