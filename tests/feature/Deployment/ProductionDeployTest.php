<?php

namespace SzentirasHu\Test\Deployment;

use PHPUnit\Framework\TestCase;

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
