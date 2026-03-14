<?php

namespace GoldLapel\Laravel\Tests;

use GoldLapel\GoldLapel;
use GoldLapel\Laravel\GoldLapelServiceProvider;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\TestCase as PureTestCase;

use function GoldLapel\Laravel\buildUpstreamUrl;

// --- buildUpstreamUrl (pure unit tests, no Laravel needed) ---

class BuildUpstreamUrlTest extends PureTestCase
{
    public function testStandard(): void
    {
        $url = buildUpstreamUrl([
            'host' => 'db.example.com', 'port' => '5432', 'database' => 'mydb',
            'username' => 'admin', 'password' => 'secret',
        ]);
        $this->assertSame('postgresql://admin:secret@db.example.com:5432/mydb', $url);
    }

    public function testEmptyHostDefaultsToLocalhost(): void
    {
        $url = buildUpstreamUrl(['host' => '', 'port' => '5432', 'database' => 'db']);
        $this->assertStringContainsString('localhost:', $url);
    }

    public function testMissingHostDefaultsToLocalhost(): void
    {
        $url = buildUpstreamUrl(['port' => '5432', 'database' => 'db']);
        $this->assertStringContainsString('localhost:', $url);
    }

    public function testEmptyPortDefaultsTo5432(): void
    {
        $url = buildUpstreamUrl(['host' => 'h', 'port' => '', 'database' => 'db']);
        $this->assertStringContainsString(':5432/', $url);
    }

    public function testMissingPortDefaultsTo5432(): void
    {
        $url = buildUpstreamUrl(['host' => 'h', 'database' => 'db']);
        $this->assertStringContainsString(':5432/', $url);
    }

    public function testSpecialCharsInPassword(): void
    {
        $url = buildUpstreamUrl([
            'host' => 'h', 'port' => '5432', 'database' => 'db',
            'username' => 'u', 'password' => '@:/',
        ]);
        $this->assertStringContainsString('u:%40%3A%2F@', $url);
    }

    public function testSpecialCharsInUser(): void
    {
        $url = buildUpstreamUrl([
            'host' => 'h', 'port' => '5432', 'database' => 'db',
            'username' => 'u@ser', 'password' => 'p',
        ]);
        $this->assertStringContainsString('u%40ser:p@', $url);
    }

    public function testNoUserNoPassword(): void
    {
        $url = buildUpstreamUrl(['host' => 'h', 'port' => '5432', 'database' => 'db']);
        $this->assertSame('postgresql://h:5432/db', $url);
    }

    public function testUserWithoutPassword(): void
    {
        $url = buildUpstreamUrl([
            'host' => 'h', 'port' => '5432', 'database' => 'db',
            'username' => 'admin',
        ]);
        $this->assertStringContainsString('admin@h:', $url);
        $this->assertStringNotContainsString(':admin', $url);
    }

    public function testSpecialCharsInDatabase(): void
    {
        $url = buildUpstreamUrl([
            'host' => 'h', 'port' => '5432', 'database' => 'my#db?v=1',
            'username' => 'u', 'password' => 'p',
        ]);
        $this->assertStringEndsWith('/my%23db%3Fv%3D1', $url);
    }

    public function testUnixSocketRaises(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unix socket');
        buildUpstreamUrl(['host' => '/var/run/postgresql', 'port' => '5432', 'database' => 'db']);
    }
}

// --- GoldLapelServiceProvider (uses Orchestra Testbench for real Laravel config) ---

class GoldLapelServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        GoldLapel::reset();
        parent::setUp();
    }

    private function bootProvider(array $connections): void
    {
        // Replace all connections to avoid Testbench defaults interfering
        config(['database.connections' => $connections]);

        $provider = new GoldLapelServiceProvider($this->app);
        $provider->boot();
    }

    public function testRewritesPgsqlConnection(): void
    {
        $this->bootProvider([
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'db.example.com',
                'port' => '5432',
                'database' => 'mydb',
                'username' => 'admin',
                'password' => 'secret',
            ],
        ]);

        $this->assertCount(1, GoldLapel::$calls);
        $call = GoldLapel::$calls[0];
        $this->assertSame('postgresql://admin:secret@db.example.com:5432/mydb', $call['upstream']);
        $this->assertSame(GoldLapel::DEFAULT_PORT, $call['port']);
        $this->assertSame([], $call['config']);
        $this->assertSame([], $call['extraArgs']);

        $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
        $this->assertSame(GoldLapel::DEFAULT_PORT, config('database.connections.pgsql.port'));
    }

    public function testSkipsNonPgsqlConnections(): void
    {
        $this->bootProvider([
            'mysql' => ['driver' => 'mysql', 'host' => 'db.example.com'],
            'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]);

        $this->assertCount(0, GoldLapel::$calls);
    }

    public function testSkipsWhenDisabled(): void
    {
        $this->bootProvider([
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'db.example.com',
                'port' => '5432',
                'database' => 'mydb',
                'username' => 'u',
                'password' => 'p',
                'goldlapel' => ['enabled' => false],
            ],
        ]);

        $this->assertCount(0, GoldLapel::$calls);
    }

    public function testCustomPortAndExtraArgs(): void
    {
        $this->bootProvider([
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'h',
                'port' => '5432',
                'database' => 'db',
                'username' => 'u',
                'password' => 'p',
                'goldlapel' => [
                    'port' => 9000,
                    'extra_args' => ['--threshold-duration-ms', '200'],
                ],
            ],
        ]);

        $this->assertCount(1, GoldLapel::$calls);
        $call = GoldLapel::$calls[0];
        $this->assertSame(9000, $call['port']);
        $this->assertSame(['--threshold-duration-ms', '200'], $call['extraArgs']);

        $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
        $this->assertSame(9000, config('database.connections.pgsql.port'));
    }

    public function testConfigPassthrough(): void
    {
        $this->bootProvider([
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'h',
                'port' => '5432',
                'database' => 'db',
                'username' => 'u',
                'password' => 'p',
                'goldlapel' => [
                    'config' => [
                        'mode' => 'butler',
                        'pool_size' => 30,
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, GoldLapel::$calls);
        $call = GoldLapel::$calls[0];
        $this->assertSame(['mode' => 'butler', 'pool_size' => 30], $call['config']);
        $this->assertSame([], $call['extraArgs']);
    }

    public function testConfigWithPortAndExtraArgs(): void
    {
        $this->bootProvider([
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'h',
                'port' => '5432',
                'database' => 'db',
                'username' => 'u',
                'password' => 'p',
                'goldlapel' => [
                    'port' => 9000,
                    'config' => [
                        'mode' => 'butler',
                        'disable_pool' => true,
                    ],
                    'extra_args' => ['--threshold-duration-ms', '200'],
                ],
            ],
        ]);

        $this->assertCount(1, GoldLapel::$calls);
        $call = GoldLapel::$calls[0];
        $this->assertSame(9000, $call['port']);
        $this->assertSame(['mode' => 'butler', 'disable_pool' => true], $call['config']);
        $this->assertSame(['--threshold-duration-ms', '200'], $call['extraArgs']);

        $this->assertSame(9000, config('database.connections.pgsql.port'));
    }

    public function testEmptyConfigArray(): void
    {
        $this->bootProvider([
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'h',
                'port' => '5432',
                'database' => 'db',
                'username' => 'u',
                'password' => 'p',
                'goldlapel' => [
                    'config' => [],
                ],
            ],
        ]);

        $this->assertCount(1, GoldLapel::$calls);
        $call = GoldLapel::$calls[0];
        $this->assertSame([], $call['config']);
    }

    public function testMultiplePgsqlConnections(): void
    {
        $this->bootProvider([
            'primary' => [
                'driver' => 'pgsql',
                'host' => 'db1.example.com',
                'port' => '5432',
                'database' => 'app',
                'username' => 'u',
                'password' => 'p',
                'goldlapel' => ['port' => 7932],
            ],
            'analytics' => [
                'driver' => 'pgsql',
                'host' => 'db2.example.com',
                'port' => '5432',
                'database' => 'analytics',
                'username' => 'u',
                'password' => 'p',
                'goldlapel' => ['port' => 7933],
            ],
        ]);

        $this->assertCount(2, GoldLapel::$calls);
        $this->assertSame(7932, GoldLapel::$calls[0]['port']);
        $this->assertSame(7933, GoldLapel::$calls[1]['port']);

        $this->assertSame('127.0.0.1', config('database.connections.primary.host'));
        $this->assertSame('127.0.0.1', config('database.connections.analytics.host'));
    }

    public function testDefaultsWhenNoGoldlapelConfig(): void
    {
        $this->bootProvider([
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'h',
                'port' => '5432',
                'database' => 'db',
                'username' => 'u',
                'password' => 'p',
            ],
        ]);

        $this->assertCount(1, GoldLapel::$calls);
        $call = GoldLapel::$calls[0];
        $this->assertSame(GoldLapel::DEFAULT_PORT, $call['port']);
        $this->assertSame([], $call['config']);
        $this->assertSame([], $call['extraArgs']);
    }
}
