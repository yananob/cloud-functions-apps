<?php

declare(strict_types=1);

namespace PubSubWriter;

class ConfigLoader
{
    private string $configPath;
    private array $config = [];

    public function __construct(string $basePath = __DIR__ . '/..')
    {
        $this->configPath = $basePath . '/configs/config.json';
        $this->load();
    }

    private function load(): void
    {
        if (file_exists($this->configPath)) {
            $configJson = file_get_contents($this->configPath);
            $this->config = json_decode($configJson, true) ?: [];
        }
    }

    public function getProjectId(): ?string
    {
        return $this->config['project_id'] ?? null;
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function getAll(): array
    {
        return $this->config;
    }
}
