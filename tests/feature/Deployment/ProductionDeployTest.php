<?php

namespace SzentirasHu\Test\Deployment;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class ProductionDeployTest extends TestCase
{
    public function test_all_relative_production_bind_mounts_are_deployed(): void
    {
        $projectDirectory = dirname(__DIR__, 3);
        $compose = file_get_contents($projectDirectory.'/docker-compose.prod.yml');
        $deployScript = file_get_contents($projectDirectory.'/deploy-prod.sh');

        self::assertNotFalse($compose);
        self::assertNotFalse($deployScript);
        self::assertSame(1, preg_match('/DEPLOY_FILES=\(\n(?<files>.*?)\n\)/s', $deployScript, $manifestMatch));

        preg_match_all('/^\s+"(?<path>[^"]+)"/m', $manifestMatch['files'], $manifestPaths);
        preg_match_all('/^\s+- \.\/(?<path>[^\r\n:]+):/m', $compose, $bindMountPaths);

        foreach ($bindMountPaths['path'] as $bindMountPath) {
            self::assertTrue(
                $this->isCoveredByManifest($bindMountPath, $manifestPaths['path']),
                "Bind-mounted path [{$bindMountPath}] is missing from DEPLOY_FILES."
            );
        }
    }

    public function test_sphinx_is_recreated_when_its_runtime_files_change(): void
    {
        $deployScript = file_get_contents(dirname(__DIR__, 3).'/deploy-prod.sh');

        self::assertNotFalse($deployScript);
        self::assertStringContainsString(
            'docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --force-recreate sphinx',
            $deployScript
        );
    }

    public function test_sphinx_files_are_verified_after_they_are_uploaded(): void
    {
        $deployScript = file_get_contents(dirname(__DIR__, 3).'/deploy-prod.sh');

        self::assertNotFalse($deployScript);

        $uploadPosition = strpos($deployScript, 'tar -czf - "${DEPLOY_FILES[@]}"');
        $verificationPosition = strpos($deployScript, 'if [ "$LOCAL_SPHINX_CHECKSUM" != "$DEPLOYED_SPHINX_CHECKSUM" ]');

        self::assertNotFalse($uploadPosition);
        self::assertNotFalse($verificationPosition);
        self::assertLessThan($verificationPosition, $uploadPosition);
        self::assertStringContainsString('Remote Sphinx config:', $deployScript);
    }

    public function test_sphinx_does_not_start_with_stale_indexes_when_initial_indexing_fails(): void
    {
        $startScript = file_get_contents(dirname(__DIR__, 3).'/docker/sphinx/start.sh');

        self::assertNotFalse($startScript);
        self::assertStringContainsString('set -eu', $startScript);
        self::assertStringContainsString(
            'if ! indexer --config /etc/sphinxsearch/sphinx.conf --all; then',
            $startScript
        );
        self::assertStringContainsString('exit 1', $startScript);
        self::assertLessThan(
            strpos($startScript, 'searchd -c /etc/sphinxsearch/sphinx.conf'),
            strpos($startScript, 'if ! indexer --config /etc/sphinxsearch/sphinx.conf --all; then')
        );
    }

    public function test_failed_sphinx_reindex_keeps_the_trigger_for_a_retry(): void
    {
        $projectDirectory = dirname(__DIR__, 3);
        $startScript = file_get_contents($projectDirectory.'/docker/sphinx/start.sh');
        $reindexScript = file_get_contents($projectDirectory.'/docker/sphinx/reindex.sh');

        self::assertNotFalse($startScript);
        self::assertNotFalse($reindexScript);
        self::assertStringContainsString(
            'if indexer --config /etc/sphinxsearch/sphinx.conf --all --rotate; then',
            $reindexScript
        );
        self::assertStringContainsString('rm -f "$FILE"', $reindexScript);
        self::assertStringContainsString('exit 1', $reindexScript);
        self::assertStringContainsString('while true; do', $startScript);
        self::assertStringNotContainsString('> /dev/null', $startScript);
    }

    public function test_database_health_is_checked_before_migrations_run(): void
    {
        $deployScript = file_get_contents(dirname(__DIR__, 3).'/deploy-prod.sh');

        self::assertNotFalse($deployScript);

        $healthCheckPosition = strpos($deployScript, 'ps database | grep -q');
        $migrationPosition = strpos($deployScript, 'run --rm --no-deps migrator');

        self::assertNotFalse($healthCheckPosition);
        self::assertNotFalse($migrationPosition);
        self::assertLessThan($migrationPosition, $healthCheckPosition);
    }

    public function test_database_is_recreated_when_its_configuration_changes(): void
    {
        $deployScript = file_get_contents(dirname(__DIR__, 3).'/deploy-prod.sh');

        self::assertNotFalse($deployScript);
        self::assertStringContainsString(
            'docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --force-recreate database',
            $deployScript
        );
    }

    public function test_postgres_healthcheck_uses_the_container_network_address(): void
    {
        $projectDirectory = dirname(__DIR__, 3);
        $compose = file_get_contents($projectDirectory.'/docker-compose.prod.yml');
        $postgresConfiguration = file_get_contents($projectDirectory.'/docker/postgres/postgresql.conf');

        self::assertNotFalse($compose);
        self::assertNotFalse($postgresConfiguration);
        self::assertStringContainsString('pg_isready -h \"$${HOSTNAME}\"', $compose);
        self::assertStringContainsString("listen_addresses = '*'", $postgresConfiguration);
    }

    public function test_verse_analysis_deployment_syncs_to_s3_then_validates_and_imports_on_production(): void
    {
        $projectDirectory = dirname(__DIR__, 3);
        $deploymentScript = $projectDirectory.'/deploy-verse-analysis.sh';
        $setupScript = file_get_contents($projectDirectory.'/setup-ssh-deploy.sh');
        $filesystem = new Filesystem;
        $temporaryDirectory = sys_get_temp_dir().'/verse-analysis-deploy-'.bin2hex(random_bytes(8));
        $binaryDirectory = $temporaryDirectory.'/bin';
        $sourceDirectory = $temporaryDirectory.'/analysis';
        $commandLog = $temporaryDirectory.'/commands.log';
        $deployConfig = $temporaryDirectory.'/.env.deploy';

        self::assertNotFalse($setupScript);
        self::assertStringContainsString('AWS_PROFILE=', $setupScript);
        self::assertStringContainsString('VERSE_ANALYSIS_BUCKET=', $setupScript);

        try {
            $filesystem->mkdir([$binaryDirectory, $sourceDirectory]);
            $filesystem->dumpFile($sourceDirectory.'/MAT_1.json', '{}');
            $filesystem->dumpFile(
                $deployConfig,
                implode("\n", [
                    'AWS_PROFILE=test-profile',
                    'DEPLOY_SERVER=example.test',
                    'DEPLOY_PORT=2222',
                    'DEPLOY_USER=deployer',
                    'DEPLOY_REMOTE_PATH=/srv/szentiras',
                    'SSH_KEY_PATH='.$temporaryDirectory.'/missing-key',
                    'VERSE_ANALYSIS_BUCKET=test-bucket',
                    '',
                ])
            );

            foreach (['aws', 'ssh'] as $command) {
                $filesystem->dumpFile(
                    $binaryDirectory.'/'.$command,
                    "#!/usr/bin/env bash\nprintf '{$command}:%s\\n' \"\$*\" >> \"\$COMMAND_LOG\"\n"
                );
                $filesystem->chmod($binaryDirectory.'/'.$command, 0755);
            }

            $process = new Process(
                ['bash', $deploymentScript],
                $projectDirectory,
                [
                    'PATH' => $binaryDirectory.':'.getenv('PATH'),
                    'COMMAND_LOG' => $commandLog,
                    'DEPLOY_CONFIG_FILE' => $deployConfig,
                    'VERSE_ANALYSIS_SOURCE_DIRECTORY' => $sourceDirectory,
                ]
            );
            $process->run();

            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());

            $commands = file($commandLog, FILE_IGNORE_NEW_LINES);

            self::assertNotFalse($commands);
            self::assertCount(3, $commands);
            self::assertSame(
                "aws:--profile test-profile s3 sync {$sourceDirectory}/ s3://test-bucket/greek/verse-analysis/OpenGNT/hu/v1/",
                $commands[0]
            );
            self::assertStringContainsString(
                'ssh:-p 2222 deployer@example.test cd /srv/szentiras && docker compose -f docker-compose.prod.yml --env-file .env.prod exec -T app php artisan szentiras:import-verse-analysis --disk=s3 --no-interaction --dry-run',
                $commands[1]
            );
            self::assertStringContainsString(
                'ssh:-p 2222 deployer@example.test cd /srv/szentiras && docker compose -f docker-compose.prod.yml --env-file .env.prod exec -T app php artisan szentiras:import-verse-analysis --disk=s3 --no-interaction',
                $commands[2]
            );
            self::assertStringNotContainsString('--dry-run', $commands[2]);
        } finally {
            $filesystem->remove($temporaryDirectory);
        }
    }

    /**
     * @param  list<string>  $manifestPaths
     */
    private function isCoveredByManifest(string $bindMountPath, array $manifestPaths): bool
    {
        foreach ($manifestPaths as $manifestPath) {
            if ($bindMountPath === $manifestPath || str_starts_with($bindMountPath, $manifestPath.'/')) {
                return true;
            }
        }

        return false;
    }
}
