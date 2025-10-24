<?php

namespace Hwkdo\IntranetAppBase\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateAppFromTemplate extends Command
{
    public $signature = 'intranet-app:generate {identifier} {--name=} {--description=} {--force}';

    public $description = 'Generates a new intranet app from the template package.';

    public function handle(): int
    {
        $identifier = $this->argument('identifier');
        $appName = $this->option('name') ?: Str::title(str_replace('-', ' ', $identifier));
        $description = $this->option('description') ?: "Generated app: {$appName}";
        $force = $this->option('force');

        // Validate identifier
        if (!preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/', $identifier)) {
            $this->error('Identifier must be lowercase, start with a letter, and contain only letters, numbers, and hyphens.');
            return self::FAILURE;
        }

        $templatePath = base_path('packages/intranet-app-template');
        $newAppPath = base_path("packages/intranet-app-{$identifier}");

        // Check if template exists
        if (!File::exists($templatePath)) {
            $this->error('Template package not found at: ' . $templatePath);
            return self::FAILURE;
        }

        // Check if target already exists
        if (File::exists($newAppPath) && !$force) {
            $this->error("App package already exists at: {$newAppPath}");
            $this->info('Use --force to overwrite existing files.');
            return self::FAILURE;
        }

        $this->info("Generating new app: {$appName} ({$identifier})");
        $this->info("Source: {$templatePath}");
        $this->info("Target: {$newAppPath}");

        // Create target directory
        if (!File::exists($newAppPath)) {
            File::makeDirectory($newAppPath, 0755, true);
        }

        // Copy template files
        $this->copyTemplateFiles($templatePath, $newAppPath, $identifier, $appName, $description);

        // Update composer.json
        $this->updateComposerJson($newAppPath, $identifier, $appName, $description);

        // Generate migrations
        $this->generateMigrations($newAppPath, $identifier);

        $this->info('✅ App generated successfully!');
        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Add the new package to your main composer.json:');
        $this->line("   \"hwkdo/intranet-app-{$identifier}\": \"dev-main\",");
        $this->newLine();
        $this->line('2. Run: composer update hwkdo/intranet-app-{$identifier}');
        $this->newLine();
        $this->line('3. Run: php artisan migrate');
        $this->newLine();
        $this->line('4. Run: php artisan intranet-app:sync-settings');

        return self::SUCCESS;
    }

    private function copyTemplateFiles(string $templatePath, string $newAppPath, string $identifier, string $appName, string $description): void
    {
        $this->info('📁 Copying template files...');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($templatePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = str_replace($templatePath . DIRECTORY_SEPARATOR, '', $item->getPathname());
            $newRelativePath = $this->transformPath($relativePath, $identifier);
            $newPath = $newAppPath . DIRECTORY_SEPARATOR . $newRelativePath;

            if ($item->isDir()) {
                File::makeDirectory($newPath, 0755, true);
            } else {
                $content = File::get($item->getPathname());
                $transformedContent = $this->transformContent($content, $identifier, $appName, $description);
                File::put($newPath, $transformedContent);
            }
        }
    }

    private function transformPath(string $path, string $identifier): string
    {
        $transformations = [
            'intranet-app-template' => "intranet-app-{$identifier}",
            'IntranetAppTemplate' => Str::studly("IntranetApp{$identifier}"),
            'IntranetAppTemplate' => Str::studly("IntranetApp{$identifier}"),
            'template' => $identifier,
            'Template' => Str::studly($identifier),
        ];

        $newPath = $path;
        foreach ($transformations as $search => $replace) {
            $newPath = str_replace($search, $replace, $newPath);
        }

        return $newPath;
    }

    private function transformContent(string $content, string $identifier, string $appName, string $description): string
    {
        $transformations = [
            // Namespace transformations
            'Hwkdo\\IntranetAppTemplate\\' => "Hwkdo\\IntranetApp" . Str::studly($identifier) . "\\",
            'IntranetAppTemplate' => Str::studly("IntranetApp{$identifier}"),
            
            // Class name transformations
            'IntranetAppTemplate' => Str::studly("IntranetApp{$identifier}"),
            'IntranetAppTemplate' => Str::studly("IntranetApp{$identifier}"),
            
            // Route transformations
            'apps.template.' => "apps.{$identifier}.",
            'apps/template' => "apps/{$identifier}",
            'apps.template' => "apps.{$identifier}",
            
            // Component transformations
            'intranet-app-template::' => "intranet-app-{$identifier}::",
            'x-intranet-app-template::' => "x-intranet-app-{$identifier}::",
            
            // Identifier transformations
            'template' => $identifier,
            'Template' => Str::studly($identifier),
            'TEMPLATE' => strtoupper($identifier),
            
            // App name and description
            'Template App' => $appName,
            'Template Übersicht' => "{$appName} Übersicht",
            'Template Beispielseite' => "{$appName} Beispielseite",
            'Template Admin' => "{$appName} Admin",
            'Template - Übersicht' => "{$appName} - Übersicht",
            'Template - Admin' => "{$appName} - Admin",
            'Template - Meine Einstellungen' => "{$appName} - Meine Einstellungen",
            'TestApp' => $appName,
            
            // Descriptions
            'Beispiel-App für neue Intranet-Apps' => $description,
            'Dies ist eine Beispiel-App, die als Template für neue Intranet-Apps dient.' => $description,
            
            // Permission transformations
            'see-app-template' => "see-app-{$identifier}",
            'manage-app-template' => "manage-app-{$identifier}",
            
            // Database table transformations
            'intranet_app_template_settings' => "intranet_app_{$identifier}_settings",
            'IntranetAppTemplateSettings' => "IntranetApp" . Str::studly($identifier) . "Settings",
        ];

        $transformedContent = $content;
        foreach ($transformations as $search => $replace) {
            $transformedContent = str_replace($search, $replace, $transformedContent);
        }

        return $transformedContent;
    }

    private function updateComposerJson(string $newAppPath, string $identifier, string $appName, string $description): void
    {
        $this->info('📦 Updating composer.json...');

        $composerPath = $newAppPath . '/composer.json';
        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);
            
            $composer['name'] = "hwkdo/intranet-app-{$identifier}";
            $composer['description'] = $description;
            
            if (isset($composer['autoload']['psr-4'])) {
                $newNamespace = "Hwkdo\\IntranetApp" . Str::studly($identifier) . "\\";
                $composer['autoload']['psr-4'] = [$newNamespace => 'src/'];
            }
            
            File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    private function generateMigrations(string $newAppPath, string $identifier): void
    {
        $this->info('🗄️ Generating migrations...');

        $migrationPath = $newAppPath . '/database/migrations';
        if (File::exists($migrationPath)) {
            $files = File::allFiles($migrationPath);
            foreach ($files as $file) {
                $content = File::get($file->getPathname());
                $transformedContent = $this->transformContent($content, $identifier, '', '');
                File::put($file->getPathname(), $transformedContent);
            }
        }
    }
}
