<?php

// Define a spy GoldLapel class before the autoloader loads the real one.
// This lets us test the service provider without needing the actual binary.

namespace GoldLapel {
    class GoldLapel
    {
        const DEFAULT_PORT = 7932;

        public static array $calls = [];

        public static function reset(): void
        {
            self::$calls = [];
        }

        public static function start(string $upstream, ?int $port = null, array $config = [], array $extraArgs = []): string
        {
            self::$calls[] = compact('upstream', 'port', 'config', 'extraArgs');
            return "postgresql://localhost:{$port}/db";
        }

        public static function stop(): void {}
        public static function proxyUrl(): ?string { return null; }
        public static function cleanup(): void {}
    }
}

namespace {
    require __DIR__ . '/../vendor/autoload.php';
}
