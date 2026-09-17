<?php

namespace SaasFoundation\Console\Commands;

use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SaasInstallCommand extends Command
{
    protected $signature = 'saas:install
                            {--with-plans : Also create the default plans and features}';

    protected $description = 'Install and configure the multi-tenancy foundation';

    public function handle(): int
    {
        $this->components->info('Installing the multi-tenancy foundation.');

        if ($this->publishConfig() === self::FAILURE) {
            return self::FAILURE;
        }

        if ($this->runMigrations() === self::FAILURE) {
            return self::FAILURE;
        }

        $this->seedDefaults();

        if ($this->option('with-plans')) {
            $this->seedPlans();
        }

        $this->components->info('Installation complete. Run `php artisan tenancy:status` to verify.');

        return self::SUCCESS;
    }

    protected function publishConfig(): int
    {
        $target = config_path('tenancy.php');

        if (file_exists($target)) {
            $this->components->twoColumnDetail('Publishing config', 'already published');

            return self::SUCCESS;
        }

        Artisan::call('vendor:publish', ['--tag' => 'saas-config', '--force' => true]);

        $this->components->twoColumnDetail('Publishing config', 'done');

        return self::SUCCESS;
    }

    protected function runMigrations(): int
    {
        $this->components->twoColumnDetail('Running migrations', '');

        Artisan::call('migrate', ['--force' => true]);

        foreach (array_filter(explode("\n", Artisan::output())) as $line) {
            $this->line("  {$line}");
        }

        return self::SUCCESS;
    }

    protected function seedDefaults(): void
    {
        $this->components->twoColumnDetail('Seeding permissions', '');
        Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
        $this->components->twoColumnDetail('Permissions', 'done');

        $this->components->twoColumnDetail('Seeding roles', '');
        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        $this->components->twoColumnDetail('Roles', 'done');
    }

    protected function seedPlans(): void
    {
        $this->components->twoColumnDetail('Seeding features', '');
        Artisan::call('db:seed', ['--class' => FeatureSeeder::class, '--force' => true]);
        $this->components->twoColumnDetail('Features', 'done');

        $this->components->twoColumnDetail('Seeding plans', '');
        Artisan::call('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);
        $this->components->twoColumnDetail('Plans', 'done');
    }
}
