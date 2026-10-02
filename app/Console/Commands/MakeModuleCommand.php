<?php

namespace App\Console\Commands;

use App\Models\AccessManagement\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class MakeModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module
        {name? : The module/model name (e.g. Products/Product or Product)}
        {--folder= : The folder/module namespace (e.g. Products)}
        {--columns= : Comma-separated columns and types (e.g. name:string,price:decimal,is_active:boolean)}
        {--min-allowed= : Comma-separated columns for $minimumAllowedKey (defaults to id,created_at)}
        {--minimum-allowed-key= : Alias for --min-allowed}
        {--not-allowed= : Comma-separated columns for $notAllowedKey (defaults to id,created_at)}
        {--not-allowed-key= : Alias for --not-allowed}
        {--log-except= : Comma-separated columns for $logExcept (defaults to id,created_at)}
        {--force : Overwrite existing files if they exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new dashboard module (Migration, Model, FormRequest, Controller, Service, Routes, and Permissions)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $resolved = $this->resolveFolderAndModel();

        if ($resolved === null) {
            return self::FAILURE;
        }

        [$folderPath, $folderNamespace, $modelName] = $resolved;

        $tableName = Str::snake(Str::pluralStudly($modelName));
        $permissionPrefix = $tableName;
        $routeUri = Str::kebab(Str::pluralStudly($modelName));
        $routeFileName = Str::camel(str_replace(['/', '\\'], '_', $folderPath));

        $columns = $this->resolveColumns();
        $columnNames = array_keys($columns);

        $minimumAllowedKey = $this->resolveArrayOption(
            optionNames: ['min-allowed', 'minimum-allowed-key'],
            prompt: 'Enter $minimumAllowedKey columns (comma-separated, or "none" for empty)',
            default: 'id,created_at'
        );

        $notAllowedKey = $this->resolveArrayOption(
            optionNames: ['not-allowed', 'not-allowed-key'],
            prompt: 'Enter $notAllowedKey columns (comma-separated, or "none" for empty)',
            default: 'id,created_at'
        );

        $logExcept = $this->resolveArrayOption(
            optionNames: ['log-except'],
            prompt: 'Enter $logExcept columns (comma-separated, or "none" for empty)',
            default: 'id,created_at'
        );

        $this->newLine();
        $this->info("Creating module [{$folderPath}/{$modelName}]...");

        // 1. Create Migration
        $this->createMigration($tableName, $columns);

        // 2. Create Model
        $this->createModel($folderPath, $folderNamespace, $modelName, $columnNames, $minimumAllowedKey, $notAllowedKey, $logExcept);

        // 3. Create Request
        $this->createRequest($folderPath, $folderNamespace, $modelName, $columnNames);

        // 4. Create Controller
        $this->createController($folderPath, $folderNamespace, $modelName, $permissionPrefix);

        // 5. Create Service
        $this->createService($folderPath, $folderNamespace, $modelName, $tableName, $columnNames);

        // 6. Create/Update Routes & Register in bootstrap/app.php
        $this->createAndRegisterRoutes($folderNamespace, $modelName, $routeUri, $routeFileName);

        // 7. Register CRUD Permissions
        $this->registerPermissions($permissionPrefix, $modelName);

        $this->newLine();
        $this->info("Module [{$folderPath}/{$modelName}] created successfully!");

        return self::SUCCESS;
    }

    /**
     * Resolve folder path, namespace, and model class name from argument/option or interactive prompt.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    protected function resolveFolderAndModel(): ?array
    {
        $rawName = $this->argument('name');

        if (! $rawName && ! $this->option('no-interaction')) {
            $rawName = $this->ask('Enter the model name (e.g. Products/Product or Product)');
        }

        $rawName = trim((string) $rawName, " \t\n\r\0\x0B/\\");

        if ($rawName === '') {
            $this->error('A valid model name is required.');
            return null;
        }

        $rawFolder = $this->option('folder');

        if (str_contains($rawName, '/') || str_contains($rawName, '\\')) {
            $segments = preg_split('#[/\\\\]+#', $rawName) ?: [];
            $rawModel = (string) array_pop($segments);
            if (! $rawFolder) {
                $rawFolder = implode('/', $segments);
            }
        } else {
            $rawModel = $rawName;
        }

        $modelName = Str::studly($rawModel);

        if (! $rawFolder) {
            $defaultFolder = Str::pluralStudly($modelName);
            $rawFolder = $this->option('no-interaction')
                ? $defaultFolder
                : $this->ask('Enter the module folder name', $defaultFolder);
        }

        $folderSegments = array_values(array_filter(
            preg_split('#[/\\\\]+#', (string) $rawFolder) ?: [],
            fn ($segment) => trim($segment) !== ''
        ));

        $studlySegments = array_map(fn ($segment) => Str::studly($segment), $folderSegments);

        if (empty($studlySegments)) {
            $studlySegments = [Str::pluralStudly($modelName)];
        }

        $folderPath = implode('/', $studlySegments);
        $folderNamespace = implode('\\', $studlySegments);

        return [$folderPath, $folderNamespace, $modelName];
    }

    /**
     * Parse columns from option or interactive prompt.
     *
     * @return array<string, array{type: string, args: string, modifiers: array<int, string>}>
     */
    protected function resolveColumns(): array
    {
        $columnsOption = $this->option('columns');

        if ($columnsOption === null && ! $this->option('no-interaction')) {
            $columnsOption = $this->ask(
                'Enter migration columns (comma-separated name:type, e.g. name:string,price:decimal,is_active:boolean — leave empty for id & timestamps only)',
                ''
            );
        }

        $columnsOption = trim((string) $columnsOption);

        if ($columnsOption === '' || strtolower($columnsOption) === 'none') {
            return [];
        }

        $definitions = preg_split('/,(?![^()]*\))/', $columnsOption) ?: [];
        $columns = [];

        foreach ($definitions as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }

            $parts = explode(':', $definition);
            $colName = Str::snake(trim(array_shift($parts)));

            if ($colName === '' || in_array($colName, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $rawType = ! empty($parts) ? trim(array_shift($parts)) : 'string';
            if ($rawType === '') {
                $rawType = 'string';
            }

            $type = $rawType;
            $args = '';

            if (preg_match('/^([a-zA-Z]+)\((.+)\)$/', $rawType, $matches)) {
                $type = $matches[1];
                $args = $matches[2];
            }

            $modifiers = array_values(array_filter(array_map('trim', $parts), fn ($m) => $m !== ''));

            $columns[$colName] = [
                'type' => $type,
                'args' => $args,
                'modifiers' => $modifiers,
            ];
        }

        return $columns;
    }

    /**
     * Resolve an array option (like $minimumAllowedKey, $notAllowedKey, $logExcept).
     *
     * @param  array<int, string>  $optionNames
     * @return array<int, string>
     */
    protected function resolveArrayOption(array $optionNames, string $prompt, string $default): array
    {
        $raw = null;
        foreach ($optionNames as $optionName) {
            $val = $this->option($optionName);
            if ($val !== null) {
                $raw = $val;
                break;
            }
        }

        if ($raw === null) {
            $raw = $this->option('no-interaction')
                ? $default
                : $this->ask($prompt, $default);
        }

        $raw = trim((string) $raw);

        if (in_array(strtolower($raw), ['none', '[]', 'empty'], true)) {
            return [];
        }

        if ($raw === '') {
            $raw = $default;
        }

        $items = array_map('trim', explode(',', $raw));

        return array_values(array_filter($items, fn ($item) => $item !== ''));
    }

    /**
     * 1. Create the migration file.
     *
     * @param  array<string, array{type: string, args: string, modifiers: array<int, string>}>  $columns
     */
    protected function createMigration(string $tableName, array $columns): void
    {
        $migrationsDir = database_path('migrations');
        $existingFiles = File::glob("{$migrationsDir}/*_create_{$tableName}_table.php");

        if (! empty($existingFiles) && ! $this->option('force')) {
            $this->warn("Migration for table [{$tableName}] already exists: " . basename($existingFiles[0]));
            return;
        }

        $migrationPath = ! empty($existingFiles)
            ? $existingFiles[0]
            : $migrationsDir . '/' . date('Y_m_d_His') . "_create_{$tableName}_table.php";

        $columnLines = [];
        $columnLines[] = '            $table->id();';

        foreach ($columns as $name => $meta) {
            $type = $meta['type'];
            $args = $meta['args'] !== '' ? ", {$meta['args']}" : '';
            $line = "            \$table->{$type}('{$name}'{$args})";

            foreach ($meta['modifiers'] as $modifier) {
                if (str_contains($modifier, '(')) {
                    $line .= "->{$modifier}";
                } else {
                    $line .= "->{$modifier}()";
                }
            }

            $line .= ';';
            $columnLines[] = $line;
        }

        $columnLines[] = '            $table->timestamps();';
        $schemaBody = implode("\n", $columnLines);

        $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
{$schemaBody}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};

PHP;

        File::ensureDirectoryExists(dirname($migrationPath));
        File::put($migrationPath, $content);
        $this->line("  <info>✔</info> Migration created: <comment>database/migrations/" . basename($migrationPath) . "</comment>");
    }

    /**
     * 2. Create the Model file.
     *
     * @param  array<int, string>  $columnNames
     * @param  array<int, string>  $minimumAllowedKey
     * @param  array<int, string>  $notAllowedKey
     * @param  array<int, string>  $logExcept
     */
    protected function createModel(
        string $folderPath,
        string $folderNamespace,
        string $modelName,
        array $columnNames,
        array $minimumAllowedKey,
        array $notAllowedKey,
        array $logExcept
    ): void {
        $relativePath = "app/Models/{$folderPath}/{$modelName}.php";
        $fullPath = base_path($relativePath);

        if (File::exists($fullPath) && ! $this->option('force')) {
            $this->warn("Model already exists: {$relativePath}");
            return;
        }

        $fillableExport = $this->formatPhpArray($columnNames);
        $minAllowedExport = $this->formatPhpArray($minimumAllowedKey);
        $notAllowedExport = $this->formatPhpArray($notAllowedKey);
        $logExceptExport = $this->formatPhpArray($logExcept);

        $content = <<<PHP
<?php

namespace App\Models\\{$folderNamespace};

use App\Helpers\AppHelper;
use App\Traits\ActivityLogsTrait;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class {$modelName} extends Model
{
    use HasFactory, ModelTrait, ActivityLogsTrait;

    protected \$fillable = {$fillableExport};

    protected \$minimumAllowedKey = {$minAllowedExport};
    protected \$notAllowedKey = {$notAllowedExport};
    protected \$specialFields = [];
    protected \$extraFields = [];
    protected \$logExcept = {$logExceptExport};

    public function __construct(array \$attributes = [])
    {
        \$this->extraFields = [];
        \$this->specialFields = [
            'created_at' => function () {
                return AppHelper::humanDate(\$this->created_at);
            },
            'updated_at' => function () {
                return AppHelper::humanDate(\$this->updated_at);
            },
        ];
        parent::__construct(\$attributes);
    }
}

PHP;

        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, $content);
        $this->line("  <info>✔</info> Model created: <comment>{$relativePath}</comment>");
    }

    /**
     * 3. Create the FormRequest file.
     *
     * @param  array<int, string>  $columnNames
     */
    protected function createRequest(
        string $folderPath,
        string $folderNamespace,
        string $modelName,
        array $columnNames
    ): void {
        $requestClass = "Create{$modelName}Request";
        $relativePath = "app/Http/Requests/Cms/{$folderPath}/{$requestClass}.php";
        $fullPath = base_path($relativePath);

        if (File::exists($fullPath) && ! $this->option('force')) {
            $this->warn("Request already exists: {$relativePath}");
            return;
        }

        if (empty($columnNames)) {
            $rulesBody = '';
        } else {
            $lines = array_map(
                fn (string $col) => "            '{$col}' => ['sometimes'],",
                $columnNames
            );
            $rulesBody = "\n" . implode("\n", $lines) . "\n        ";
        }

        $content = <<<PHP
<?php

namespace App\Http\Requests\Cms\\{$folderNamespace};

use Illuminate\Foundation\Http\FormRequest;

class {$requestClass} extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [{$rulesBody}];
    }
}

PHP;

        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, $content);
        $this->line("  <info>✔</info> Request created: <comment>{$relativePath}</comment>");
    }

    /**
     * 4. Create the Controller file.
     */
    protected function createController(
        string $folderPath,
        string $folderNamespace,
        string $modelName,
        string $permissionPrefix
    ): void {
        $controllerClass = "{$modelName}Controller";
        $serviceClass = "{$modelName}Service";
        $serviceVar = Str::camel($serviceClass);
        $requestClass = "Create{$modelName}Request";

        $relativePath = "app/Http/Controllers/Cms/{$folderPath}/{$controllerClass}.php";
        $fullPath = base_path($relativePath);

        if (File::exists($fullPath) && ! $this->option('force')) {
            $this->warn("Controller already exists: {$relativePath}");
            return;
        }

        $content = <<<PHP
<?php

namespace App\Http\Controllers\Cms\\{$folderNamespace};

use App\Http\Controllers\CrudController;
use App\Http\Requests\Cms\\{$folderNamespace}\\{$requestClass};
use App\Services\Cms\\{$folderNamespace}\\{$serviceClass};

class {$controllerClass} extends CrudController
{
    protected string \$permissionPrefix = '{$permissionPrefix}';

    public function __construct({$serviceClass} \${$serviceVar})
    {
        \$this->createRequest = {$requestClass}::class;
        \$this->updateRequest = {$requestClass}::class;

        parent::__construct(\${$serviceVar});
    }
}

PHP;

        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, $content);
        $this->line("  <info>✔</info> Controller created: <comment>{$relativePath}</comment>");
    }

    /**
     * 5. Create the Service file.
     *
     * @param  array<int, string>  $columnNames
     */
    protected function createService(
        string $folderPath,
        string $folderNamespace,
        string $modelName,
        string $tableName,
        array $columnNames
    ): void {
        $serviceClass = "{$modelName}Service";
        $relativePath = "app/Services/Cms/{$folderPath}/{$serviceClass}.php";
        $fullPath = base_path($relativePath);

        if (File::exists($fullPath) && ! $this->option('force')) {
            $this->warn("Service already exists: {$relativePath}");
            return;
        }

        $normalColumns = ! empty($columnNames) ? $columnNames : ['id'];
        $advancedColumns = array_values(array_unique(array_merge($columnNames, ['created_at'])));

        $headings = array_merge(
            ['#ID'],
            array_map(fn (string $col) => Str::headline($col), $columnNames),
            ['Date']
        );

        $fields = array_merge(
            ["{$tableName}.id"],
            array_map(fn (string $col) => "{$tableName}.{$col}", $columnNames),
            ["{$tableName}.created_at"]
        );

        $normalExport = $this->formatPhpArray($normalColumns);
        $advancedExport = $this->formatPhpArray($advancedColumns);
        $headingsExport = $this->formatPhpArray($headings);
        $fieldsExport = $this->formatPhpArray($fields);

        $content = <<<PHP
<?php

namespace App\Services\Cms\\{$folderNamespace};

use App\Models\\{$folderNamespace}\\{$modelName};
use App\Services\Cms\CrudService;

class {$serviceClass} extends CrudService
{
    public function __construct()
    {
        parent::__construct({$modelName}::class);

        \$this->searchColumns(
            normal: {$normalExport},
            advanced: {$advancedExport}
        )
            ->orderBy(['id'], 'desc')
            ->report(
                headings: {$headingsExport},
                fields: {$fieldsExport}
            );
    }
}

PHP;

        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, $content);
        $this->line("  <info>✔</info> Service created: <comment>{$relativePath}</comment>");
    }

    /**
     * 6. Create or update the routes file in routes/cms/ and register it in bootstrap/app.php.
     */
    protected function createAndRegisterRoutes(
        string $folderNamespace,
        string $modelName,
        string $routeUri,
        string $routeFileName
    ): void {
        $controllerClass = "{$modelName}Controller";
        $controllerFqn = "App\\Http\\Controllers\\Cms\\{$folderNamespace}\\{$controllerClass}";
        $sectionComment = Str::headline(Str::pluralStudly($modelName));

        $relativeRoutePath = "routes/cms/{$routeFileName}.php";
        $fullRoutePath = base_path($relativeRoutePath);

        $routeBlock = <<<PHP
    // {$sectionComment}
    Route::get('/{$routeUri}/builder', [{$controllerClass}::class, 'builder']);
    Route::resource('{$routeUri}', {$controllerClass}::class)->except(['create', 'edit']);
    Route::put('/{$routeUri}/{id}/toggle-active', [{$controllerClass}::class, 'toggleActiveStatus']);
PHP;

        if (! File::exists($fullRoutePath)) {
            $routeFileContent = <<<PHP
<?php

use {$controllerFqn};
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
{$routeBlock}
});

PHP;
            File::ensureDirectoryExists(dirname($fullRoutePath));
            File::put($fullRoutePath, $routeFileContent);
            $this->line("  <info>✔</info> Routes file created: <comment>{$relativeRoutePath}</comment>");
        } else {
            $existingContent = File::get($fullRoutePath);

            if (! str_contains($existingContent, "use {$controllerFqn};")) {
                $existingContent = preg_replace(
                    '/^(<\?php\s*(?:use [^\n]+;\s*)*)/',
                    "$1use {$controllerFqn};\n",
                    $existingContent,
                    1
                ) ?? $existingContent;
            }

            if (! str_contains($existingContent, "Route::resource('{$routeUri}'")) {
                $existingContent = preg_replace(
                    '/\}\);\s*$/',
                    "\n{$routeBlock}\n});\n",
                    $existingContent,
                    1
                ) ?? $existingContent;

                File::put($fullRoutePath, $existingContent);
                $this->line("  <info>✔</info> Routes added to existing file: <comment>{$relativeRoutePath}</comment>");
            } else {
                $this->line("  <info>ℹ</info> Routes for [{$routeUri}] already exist in: <comment>{$relativeRoutePath}</comment>");
            }
        }

        // Register route file in bootstrap/app.php
        $bootstrapPath = base_path('bootstrap/app.php');
        if (File::exists($bootstrapPath)) {
            $bootstrapContent = File::get($bootstrapPath);
            $groupCall = "->group(base_path('routes/cms/{$routeFileName}.php'))";

            if (! str_contains($bootstrapContent, "routes/cms/{$routeFileName}.php")) {
                $updatedBootstrap = preg_replace(
                    "/(Route::prefix\(['\"]cms['\"]\)(?:\s*->group\(base_path\(['\"][^'\"]+['\"]\)\))+)\s*;/",
                    "$1\n                {$groupCall};",
                    $bootstrapContent,
                    1
                );

                if ($updatedBootstrap !== null && $updatedBootstrap !== $bootstrapContent) {
                    File::put($bootstrapPath, $updatedBootstrap);
                    $this->line("  <info>✔</info> Route registered in: <comment>bootstrap/app.php</comment>");
                } else {
                    $this->warn("Could not automatically locate Route::prefix('cms') chain in bootstrap/app.php.");
                }
            } else {
                $this->line("  <info>ℹ</info> Route file already registered in: <comment>bootstrap/app.php</comment>");
            }
        }
    }

    /**
     * 7. Add permissions to RolePermissionSeeder and create in DB if available.
     */
    protected function registerPermissions(string $permissionPrefix, string $modelName): void
    {
        $actions = ['view', 'create', 'edit', 'delete'];
        $permissions = array_map(fn (string $action) => "{$permissionPrefix}.{$action}", $actions);

        // Update RolePermissionSeeder.php
        $seederPath = database_path('seeders/RolePermissionSeeder.php');
        if (File::exists($seederPath)) {
            $seederContent = File::get($seederPath);
            if (! str_contains($seederContent, "'{$permissionPrefix}.view'")) {
                $label = Str::headline($modelName) . ' management';
                $permLines = array_map(fn (string $perm) => "            '{$perm}',", $permissions);
                $permBlock = "\n            // {$label}\n" . implode("\n", $permLines) . "\n        ];";

                $updatedSeeder = preg_replace(
                    '/(\n\s*\$permissions\s*=\s*\[.*?)\n\s*\];/s',
                    "$1{$permBlock}",
                    $seederContent,
                    1
                );

                if ($updatedSeeder !== null && $updatedSeeder !== $seederContent) {
                    File::put($seederPath, $updatedSeeder);
                    $this->line("  <info>✔</info> Permissions added to: <comment>database/seeders/RolePermissionSeeder.php</comment>");
                }
            }
        }

        // Create permissions in DB if table exists
        try {
            if (Schema::hasTable('permissions')) {
                app()[PermissionRegistrar::class]->forgetCachedPermissions();

                $createdPermissions = [];
                foreach ($permissions as $permission) {
                    $createdPermissions[] = Permission::firstOrCreate([
                        'name' => $permission,
                        'guard_name' => 'cms',
                    ]);
                }

                if (Schema::hasTable('roles')) {
                    $superAdmin = Role::where('name', 'super_admin')
                        ->where('guard_name', 'cms')
                        ->first();

                    if ($superAdmin) {
                        $superAdmin->givePermissionTo($createdPermissions);
                    }
                }

                $this->line("  <info>✔</info> Permissions synced to database for guard [cms]: <comment>" . implode(', ', $permissions) . "</comment>");
            }
        } catch (Throwable) {
            $this->line("  <info>ℹ</info> Database not ready; permissions will be seeded via RolePermissionSeeder.");
        }
    }

    /**
     * Format a flat list of strings as a single-line PHP array literal.
     *
     * @param  array<int, string>  $items
     */
    protected function formatPhpArray(array $items): string
    {
        if (empty($items)) {
            return '[]';
        }

        $quoted = array_map(fn (string $item) => "'" . addslashes($item) . "'", $items);

        return '[' . implode(', ', $quoted) . ']';
    }
}
