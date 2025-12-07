<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\BotSDK;

use OCP\IConfig;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Auto-discovers and loads bots from lib/Bots/
 *
 * Usage:
 *   $loader = new BotLoader($container, $logger, $config, 'docuseal_integration');
 *   $registry = $loader->loadAll();
 *
 * Bots are discovered by scanning lib/Bots/*\/*Bot.php
 * Each bot can optionally provide a static create() factory:
 *
 *   public static function create(ContainerInterface $c): static
 *
 * If no factory exists, the loader tries to instantiate via the DI container.
 *
 * Bots can be enabled/disabled in Nextcloud config:
 *   bots_enabled = ["docuseal", "example"]  (empty = all enabled)
 *   bots_disabled = ["example"]             (takes precedence)
 */
class BotLoader
{
    private string $botsPath;

    public function __construct(
        private ContainerInterface $container,
        private LoggerInterface $logger,
        private IConfig $config,
        private string $appId,
    ) {
        // Path to Bots directory
        $this->botsPath = dirname(__DIR__) . '/Bots';
    }

    /**
     * Load all discovered bots into a registry
     */
    public function loadAll(): BotRegistry
    {
        $registry = new BotRegistry($this->logger);

        foreach ($this->discoverBots() as $botClass) {
            try {
                $bot = $this->instantiate($botClass);

                if ($bot && $this->isEnabled($bot)) {
                    $registry->register($bot);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to load bot', [
                    'class' => $botClass,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $registry;
    }

    /**
     * Discover bot classes by scanning the Bots directory
     *
     * @return string[] Fully qualified class names
     */
    public function discoverBots(): array
    {
        $bots = [];

        if (!is_dir($this->botsPath)) {
            return $bots;
        }

        foreach (scandir($this->botsPath) as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $botDir = $this->botsPath . '/' . $dir;
            if (!is_dir($botDir)) {
                continue;
            }

            // Look for *Bot.php files
            foreach (glob($botDir . '/*Bot.php') as $file) {
                $className = $this->resolveClassName($dir, basename($file, '.php'));
                if ($className && class_exists($className)) {
                    $bots[] = $className;
                }
            }
        }

        return $bots;
    }

    /**
     * Resolve full class name from directory and file name
     */
    private function resolveClassName(string $dir, string $class): string
    {
        return "OCA\\DocuSealIntegration\\Bots\\{$dir}\\{$class}";
    }

    /**
     * Instantiate a bot class
     */
    private function instantiate(string $className): ?BotInterface
    {
        // Check for static factory method
        if (method_exists($className, 'create')) {
            $bot = $className::create($this->container);
            if ($bot instanceof BotInterface) {
                return $bot;
            }
        }

        // Try DI container
        try {
            $bot = $this->container->get($className);
            if ($bot instanceof BotInterface) {
                return $bot;
            }
        } catch (\Throwable $e) {
            // Container couldn't resolve it, try manual instantiation
        }

        // Manual instantiation with reflection
        return $this->instantiateWithReflection($className);
    }

    /**
     * Instantiate using reflection to resolve constructor dependencies
     */
    private function instantiateWithReflection(string $className): ?BotInterface
    {
        $reflection = new \ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            return null;
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                // Try to resolve from container
                try {
                    $params[] = $this->container->get($typeName);
                    continue;
                } catch (\Throwable $e) {
                    // Couldn't resolve
                }
            }

            // Use default value if available
            if ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
                continue;
            }

            // Can't resolve this parameter
            $this->logger->debug('Cannot resolve constructor parameter', [
                'class' => $className,
                'param' => $param->getName(),
            ]);
            return null;
        }

        return $reflection->newInstanceArgs($params);
    }

    /**
     * Check if a bot is enabled in config
     */
    private function isEnabled(BotInterface $bot): bool
    {
        $botId = $bot->getId();

        // Check disabled list first (takes precedence)
        $disabled = $this->config->getAppValue($this->appId, 'bots_disabled', '');
        if ($disabled) {
            $disabledList = json_decode($disabled, true) ?: [];
            if (in_array($botId, $disabledList, true)) {
                return false;
            }
        }

        // Check enabled list (empty = all enabled)
        $enabled = $this->config->getAppValue($this->appId, 'bots_enabled', '');
        if ($enabled) {
            $enabledList = json_decode($enabled, true) ?: [];
            return in_array($botId, $enabledList, true);
        }

        // Default: enabled
        return true;
    }
}
