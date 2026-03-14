# goldlapel-laravel

Laravel service provider for [Gold Lapel](https://goldlapel.com) — self-optimizing Postgres proxy that automatically creates materialized views and indexes.

One `composer require` and a config key. Gold Lapel starts automatically when Laravel boots.

## Install

```bash
composer require goldlapel/goldlapel-laravel
```

Laravel's package auto-discovery registers the service provider automatically.

## Usage

All PostgreSQL connections are proxied automatically. Add a `goldlapel` key to customize settings or disable for a specific connection:

```php
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'goldlapel' => [
        'enabled' => true,      // default: true
        'port' => 7932,         // default: 7932
        'config' => [           // default: []
            'mode' => 'butler',
            'pool_size' => 30,
        ],
        'extra_args' => [],     // default: []
    ],
],
```

That's it. Gold Lapel starts when Laravel boots, watches your query patterns, and automatically optimizes your database.

## Configuration

| Key | Default | Description |
|-----|---------|-------------|
| `goldlapel.enabled` | `true` | Set to `false` to disable for this connection |
| `goldlapel.port` | `7932` | Local proxy port |
| `goldlapel.config` | `[]` | Configuration array passed to Gold Lapel (see below) |
| `goldlapel.extra_args` | `[]` | Extra CLI args passed to the Gold Lapel binary |

The `config` array accepts any Gold Lapel configuration key using snake_case:

```php
'goldlapel' => [
    'config' => [
        'mode' => 'butler',
        'pool_size' => 30,
        'refresh_interval_secs' => 120,
        'disable_result_cache' => true,
        'replica' => ['postgresql://replica1:5432/db', 'postgresql://replica2:5432/db'],
    ],
],
```

See [`GoldLapel::configKeys()`](https://github.com/goldlapel/goldlapel-php) for the full list of supported keys.

## Multiple Databases

If you have multiple PostgreSQL connections, each must use a different proxy port:

```php
'primary' => [
    'driver' => 'pgsql',
    'host' => 'db1.example.com',
    // ...
    'goldlapel' => ['port' => 7932],
],
'analytics' => [
    'driver' => 'pgsql',
    'host' => 'db2.example.com',
    // ...
    'goldlapel' => ['port' => 7933],
],
```

## Requirements

- PHP 8.1+
- Laravel 10+
- PostgreSQL (TCP connections only — Unix sockets are not supported)

## How It Works

The service provider runs at boot time. For each PostgreSQL connection, it:

1. Builds the upstream PostgreSQL URL from your connection parameters
2. Starts the Gold Lapel proxy via [`GoldLapel::start()`](https://github.com/goldlapel/goldlapel-php)
3. Rewrites the connection config to route through the proxy (`127.0.0.1:7932`)

Everything else — Eloquent, migrations, raw queries — works exactly as before, just faster.
