<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionDeploymentArtifactsTest extends TestCase
{
    #[DataProvider('requiredFilesProvider')]
    public function test_production_deployment_files_exist(string $relativePath): void
    {
        $this->assertFileExists(base_path($relativePath), "Missing deployment artifact: {$relativePath}");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function requiredFilesProvider(): array
    {
        return [
            'dockerfile' => ['Dockerfile'],
            'compose' => ['compose.production.yml'],
            'dockerignore' => ['.dockerignore'],
            'caddyfile' => ['deploy/Caddyfile'],
            'php ini' => ['deploy/php.ini'],
            'app env example' => ['deploy/app.env.example'],
            'deploy common lib' => ['deploy/scripts/lib/deploy-common.sh'],
            'deploy script' => ['deploy/scripts/deploy.sh'],
            'backup script' => ['deploy/scripts/backup-database.sh'],
            'restore script' => ['deploy/scripts/restore-database.sh'],
            'ci workflow' => ['.github/workflows/ci.yml'],
            'deploy workflow' => ['.github/workflows/deploy-production.yml'],
            'documentation' => ['docs/PRODUCTION_DEPLOYMENT.md'],
        ];
    }

    public function test_compose_production_yml_does_not_expose_database_port(): void
    {
        $contents = file_get_contents(base_path('compose.production.yml'));

        $this->assertIsString($contents);
        $this->assertStringNotContainsString('5432:5432', $contents);
        $this->assertStringContainsString('postgres:16-bookworm', $contents);
        $this->assertStringNotContainsString('redis:', $contents);
    }

    public function test_compose_uses_single_db_credential_source_and_internal_healthcheck(): void
    {
        $compose = file_get_contents(base_path('compose.production.yml'));
        $envExample = file_get_contents(base_path('deploy/app.env.example'));

        $this->assertIsString($compose);
        $this->assertIsString($envExample);

        $this->assertStringContainsString('POSTGRES_DB: ${DB_DATABASE', $compose);
        $this->assertStringContainsString('POSTGRES_USER: ${DB_USERNAME', $compose);
        $this->assertStringContainsString('POSTGRES_PASSWORD: ${DB_PASSWORD', $compose);
        $this->assertStringContainsString('$$POSTGRES_USER', $compose);
        $this->assertStringContainsString('$$POSTGRES_DB', $compose);
        $this->assertStringContainsString('http://127.0.0.1:8080/up', $compose);

        $this->assertStringNotContainsString('POSTGRES_DB=', $envExample);
        $this->assertStringNotContainsString('POSTGRES_USER=', $envExample);
        $this->assertStringNotContainsString('POSTGRES_PASSWORD=', $envExample);
        $this->assertStringContainsString('DB_DATABASE=dentalfinance', $envExample);
        $this->assertStringContainsString('DB_USERNAME=dentalfinance', $envExample);
    }

    public function test_caddyfile_exposes_internal_health_listener_only_on_localhost(): void
    {
        $caddyfile = file_get_contents(base_path('deploy/Caddyfile'));

        $this->assertIsString($caddyfile);
        $this->assertStringContainsString(':8080 {', $caddyfile);
        $this->assertStringContainsString('bind 127.0.0.1', $caddyfile);
        $this->assertStringContainsString('root * /app/public', $caddyfile);
        $this->assertStringContainsString('php_server', $caddyfile);
    }

    public function test_dockerfile_uses_shared_php_base_composer_binary_and_oci_label(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertIsString($dockerfile);
        $this->assertStringContainsString(
            'composer:2.8 AS composer_binary',
            $dockerfile
        );
        $this->assertStringContainsString(
            'COPY --from=composer_binary /usr/bin/composer /usr/bin/composer',
            $dockerfile
        );
        $this->assertStringContainsString(
            'frankenphp:1.9.1-php8.3-bookworm AS php_base',
            $dockerfile
        );
        $this->assertStringContainsString(
            'FROM php_base AS vendor',
            $dockerfile
        );
        $this->assertStringContainsString(
            'FROM php_base AS runtime',
            $dockerfile
        );
        $this->assertStringContainsString(
            'org.opencontainers.image.source',
            $dockerfile
        );
        $this->assertStringContainsString(
            'http://127.0.0.1:8080/up',
            $dockerfile
        );
        $this->assertStringNotContainsString(
            'composer:2-php8.3-bookworm',
            $dockerfile
        );
    }

    public function test_deploy_workflow_uses_safe_rsync_targets(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/deploy-production.yml'));

        $this->assertIsString($workflow);
        $this->assertStringContainsString('/opt/dentalfinance/compose.production.yml', $workflow);
        $this->assertStringContainsString('/opt/dentalfinance/deploy/', $workflow);
        $this->assertStringNotContainsString('rsync -az --delete ./', $workflow);
        $this->assertStringNotContainsString('/opt/dentalfinance/ --delete', $workflow);
        $this->assertStringNotContainsString('IMAGE_NAME,,', $workflow);
        $this->assertStringContainsString('GITHUB_OUTPUT', $workflow);
        $this->assertStringContainsString('tr \'[:upper:]\' \'[:lower:]\'', $workflow);
    }

    public function test_deploy_scripts_use_compose_env_file_without_sourcing_app_env(): void
    {
        $common = file_get_contents(base_path('deploy/scripts/lib/deploy-common.sh'));
        $deploy = file_get_contents(base_path('deploy/scripts/deploy.sh'));

        $this->assertIsString($common);
        $this->assertIsString($deploy);

        $this->assertStringContainsString('--env-file "${APP_ENV_FILE}"', $common);
        $this->assertStringNotContainsString('source "${APP_ENV_FILE}"', $common);
        $this->assertStringNotContainsString('source app.env', $deploy);
        $this->assertStringContainsString('resolve_app_image', file_get_contents(base_path('deploy/scripts/backup-database.sh')));
    }

    public function test_welcome_view_is_not_a_production_route(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $welcome = file_get_contents(base_path('resources/views/welcome.blade.php'));

        $this->assertIsString($routes);
        $this->assertIsString($welcome);

        $this->assertStringContainsString("Route::get('/', [LandingController::class, 'index'])", $routes);
        $this->assertStringNotContainsString('welcome', $routes);
        $this->assertStringContainsString('@vite', $welcome);
    }
}
