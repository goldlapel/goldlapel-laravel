<?php

namespace GoldLapel\Laravel;

use InvalidArgumentException;

function buildUpstreamUrl(array $config): string
{
    $host = $config['host'] ?? '';
    if ($host === '') {
        $host = 'localhost';
    }

    if (str_starts_with($host, '/')) {
        throw new InvalidArgumentException(
            "Unix socket connections are not supported by Gold Lapel (host: {$host}). Use a TCP host instead."
        );
    }

    $port = $config['port'] ?? '';
    if ($port === '' || $port === null) {
        $port = '5432';
    }

    $database = $config['database'] ?? '';

    $user = $config['username'] ?? '';
    $password = $config['password'] ?? '';

    $userinfo = '';
    if ($user !== '' && $user !== null) {
        $userinfo = rawurlencode($user);
        if ($password !== '' && $password !== null) {
            $userinfo .= ':' . rawurlencode($password);
        }
        $userinfo .= '@';
    }

    $encodedDb = rawurlencode($database);

    return "postgresql://{$userinfo}{$host}:{$port}/{$encodedDb}";
}
