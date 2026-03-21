<?php

declare(strict_types=1);

namespace App\Hashids;

use Hashids\Hashids;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

/**
 * Resolves named Hashids clients from config/hashids.php (one "connection" per model class).
 *
 * @method string encode(mixed ...$numbers)
 * @method array decode(string $hash)
 * @method string encodeHex(string $str)
 * @method string decodeHex(string $hash)
 */
class HashidsManager
{
    /**
     * @var array<string, Hashids>
     */
    protected array $connections = [];

    public function __construct(
        protected Repository $config,
        protected HashidsFactory $factory
    ) {}

    public function connection(?string $name = null): Hashids
    {
        $name = $name ?? $this->getDefaultConnection();

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->factory->make(
                $this->getConnectionConfig($name)
            );
        }

        return $this->connections[$name];
    }

    public function getDefaultConnection(): string
    {
        return (string) $this->config->get('hashids.default');
    }

    public function getFactory(): HashidsFactory
    {
        return $this->factory;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConnectionConfig(string $name): array
    {
        $connections = $this->config->get('hashids.connections', []);

        if (! is_array($connections) || ! isset($connections[$name])) {
            throw new InvalidArgumentException("Hashids connection [{$name}] not configured.");
        }

        return $connections[$name];
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }
}
