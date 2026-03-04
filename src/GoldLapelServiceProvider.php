<?php

namespace GoldLapel\Laravel;

use GoldLapel\GoldLapel;
use Illuminate\Support\ServiceProvider;

class GoldLapelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $connections = config('database.connections', []);

        foreach ($connections as $name => $config) {
            if (($config['driver'] ?? '') !== 'pgsql') {
                continue;
            }

            $glConfig = $config['goldlapel'] ?? [];

            if (($glConfig['enabled'] ?? true) === false) {
                continue;
            }

            $port = $glConfig['port'] ?? GoldLapel::DEFAULT_PORT;
            $extraArgs = $glConfig['extra_args'] ?? [];

            $upstream = buildUpstreamUrl($config);

            GoldLapel::start($upstream, $port, $extraArgs);

            config([
                "database.connections.{$name}.host" => '127.0.0.1',
                "database.connections.{$name}.port" => $port,
            ]);
        }
    }
}
