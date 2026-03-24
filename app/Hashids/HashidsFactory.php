<?php

declare(strict_types=1);

namespace App\Hashids;

use Hashids\Hashids;
use Illuminate\Support\Arr;

class HashidsFactory
{
    public function make(array $config): Hashids
    {
        $config = $this->getConfig($config);

        return $this->getClient($config);
    }

    /**
     * @return array{salt: string, length: int, alphabet: string}
     */
    protected function getConfig(array $config): array
    {
        return [
            'salt' => Arr::get($config, 'salt', ''),
            'length' => Arr::get($config, 'length', 0),
            'alphabet' => Arr::get($config, 'alphabet', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'),
        ];
    }

    protected function getClient(array $config): Hashids
    {
        return new Hashids($config['salt'], $config['length'], $config['alphabet']);
    }
}
