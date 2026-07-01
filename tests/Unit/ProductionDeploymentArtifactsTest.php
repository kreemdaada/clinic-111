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

    public function test_compose_healthchecks_are_scoped_to_http_app_and_postgres_only(): void
    {
        $compose = file_get_contents(base_path('compose.production.yml'));

        $this->assertIsString($compose);

        preg_match('/\n  app:\n(.*?)(?=\n  worker:)/s', $compose, $appBlock);
        preg_match('/\n  worker:\n(.*?)(?=\n  scheduler:)/s', $compose, $workerBlock);
        preg_match('/\n  scheduler:\n(.*?)(?=\n  database:)/s', $compose, $schedulerBlock);
        preg_match('/\n  database:\n(.*?)(?=\nnetworks:)/s', $compose, $databaseBlock);

        $this->assertNotEmpty($appBlock[1] ?? null, 'Expected app service block in compose.production.yml');
        $this->assertNotEmpty($workerBlock[1] ?? null, 'Expected worker service block in compose.production.yml');
        $this->assertNotEmpty($schedulerBlock[1] ?? null, 'Expected scheduler service block in compose.production.yml');
        $this->assertNotEmpty($databaseBlock[1] ?? null, 'Expected database service block in compose.production.yml');

        $this->assertStringContainsString('http://127.0.0.1:8080/up', $appBlock[1]);
        $this->assertStringNotContainsString('disable: true', $appBlock[1]);

        $this->assertStringNotContainsString('http://127.0.0.1:8080/up', $workerBlock[1]);
        $this->assertStringContainsString('healthcheck:', $workerBlock[1]);
        $this->assertStringContainsString('disable: true', $workerBlock[1]);
        $this->assertStringContainsString('stop_grace_period: 120s', $workerBlock[1]);

        $this->assertStringNotContainsString('http://127.0.0.1:8080/up', $schedulerBlock[1]);
        $this->assertStringContainsString('healthcheck:', $schedulerBlock[1]);
        $this->assertStringContainsString('disable: true', $schedulerBlock[1]);
        $this->assertStringContainsString('stop_grace_period: 30s', $schedulerBlock[1]);

        $this->assertStringContainsString('pg_isready', $databaseBlock[1]);
        $this->assertStringNotContainsString('disable: true', $databaseBlock[1]);
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
        $this->assertStringContainsString(
            'pcntl',
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
        $this->assertStringContainsString('ServerAliveInterval=30', $workflow);
        $this->assertStringContainsString('ServerAliveCountMax=10', $workflow);
        $this->assertStringContainsString('TCPKeepAlive=yes', $workflow);
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

    public function test_deploy_script_stops_background_services_before_backup_and_uses_controlled_compose_up(): void
    {
        $deploy = file_get_contents(base_path('deploy/scripts/deploy.sh'));
        $common = file_get_contents(base_path('deploy/scripts/lib/deploy-common.sh'));

        $this->assertIsString($deploy);
        $this->assertIsString($common);

        $queueRestartPos = strpos($common, 'queue:restart');
        $backupPos = strpos($deploy, 'Creating pre-migration backup');
        $migratePos = strpos($deploy, 'Running migrations');
        $composeUpPos = strpos($deploy, 'compose_up_application');
        $stopServicesPos = strpos($deploy, 'stop_background_services_for_deploy');

        $this->assertNotFalse($queueRestartPos);
        $this->assertNotFalse($backupPos);
        $this->assertNotFalse($migratePos);
        $this->assertNotFalse($composeUpPos);
        $this->assertNotFalse($stopServicesPos);
        $this->assertLessThan($backupPos, $stopServicesPos);
        $this->assertLessThan($migratePos, $backupPos);
        $this->assertLessThan($composeUpPos, $migratePos);

        $this->assertStringContainsString('stop_background_services_for_deploy', $deploy);
        $this->assertStringContainsString('compose stop -t "${stop_timeout}"', $common);
        $this->assertStringContainsString('compose up -d', $common);
        $this->assertStringContainsString('--timeout 30', $common);
        $this->assertStringContainsString('--wait', $common);
        $this->assertStringContainsString('--wait-timeout 180', $common);
        $this->assertStringContainsString('--remove-orphans', $common);
        $this->assertStringNotContainsString('compose down', $deploy);
        $this->assertStringNotContainsString('compose down', $common);
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

    public function test_production_mail_configuration_uses_resend_smtp_scheme_for_port_587(): void
    {
        $mailConfig = file_get_contents(base_path('config/mail.php'));
        $productionEnv = file_get_contents(base_path('deploy/app.env.example'));

        $this->assertIsString($mailConfig);
        $this->assertIsString($productionEnv);

        $this->assertStringContainsString("'scheme' => env('MAIL_SCHEME')", $mailConfig);
        $this->assertStringNotContainsString('MAIL_ENCRYPTION', $mailConfig);

        foreach ([
            'MAIL_MAILER=smtp',
            'MAIL_SCHEME=smtp',
            'MAIL_HOST=smtp.resend.com',
            'MAIL_PORT=587',
            'MAIL_USERNAME=resend',
            'MAIL_FROM_ADDRESS=system@dentalfinance.eu',
            'MAIL_FROM_NAME=DentalFinance',
            'MAIL_EHLO_DOMAIN=dentalfinance.eu',
        ] as $expectedLine) {
            $this->assertStringContainsString($expectedLine, $productionEnv, "Missing in deploy/app.env.example: {$expectedLine}");
        }

        $this->assertStringNotContainsString('MAIL_ENCRYPTION=', $productionEnv);
        $this->assertStringNotContainsString('brevo', strtolower($productionEnv));
    }
}
