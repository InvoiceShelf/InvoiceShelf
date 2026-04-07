<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExtensionCatalog
{
    private const int CACHE_MINUTES = 10;

    public function __construct(
        private readonly string $manifestUrl,
    ) {}

    public static function make(): self
    {
        return new self((string) config('invoiceshelf.extensions.manifest_url'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchExtensions(): array
    {
        $url = trim($this->manifestUrl);

        if ($url === '') {
            throw new \RuntimeException('Extensions manifest URL is empty. Set INVOICESHELF_EXTENSIONS_MANIFEST_URL or remove it from .env to use the default.');
        }

        $cacheKey = 'extensions_manifest:'.sha1($url);

        /** @var array<int, array<string, mixed>> $cached */
        $cached = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_MINUTES), function () use ($url) {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'InvoiceShelf (+'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost').')',
                ])
                ->get($url);

            $response->throw();

            $payload = $response->json();

            return $this->normalizePayload($payload);
        });

        return $cached;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalizePayload(mixed $payload): array
    {
        if ($payload === null) {
            throw new \RuntimeException('Invalid extensions manifest: empty response.');
        }

        if (is_array($payload) && isset($payload['extensions']) && is_array($payload['extensions'])) {
            $rows = $payload['extensions'];
        } elseif (is_array($payload)) {
            $rows = $payload;
        } else {
            throw new \RuntimeException('Invalid extensions manifest: unexpected root type.');
        }

        if ($rows !== [] && ! array_is_list($rows) && isset($rows['name'])) {
            $rows = [$rows];
        }

        /** @var array<int, array<string, mixed>> $filtered */
        $filtered = array_values(array_filter($rows, fn ($row) => is_array($row)));

        return array_map(fn (array $row) => $this->normalizeRow($row), $filtered);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $slug = $this->deriveSlug($row);
        $moduleName = $this->deriveModuleName($row, $slug);

        return [
            'slug' => $slug,
            'module_name' => $moduleName,
            'name' => $row['name'] ?? $moduleName,
            'description' => $row['description'] ?? '',
            'version' => $row['version'] ?? '0.0.0',
            'author' => $row['author'] ?? '',
            'license' => $row['license'] ?? '',
            'tags' => $row['tags'] ?? [],
            'compatibility' => $row['compatibility'] ?? [],
            'repository' => $row['repository'] ?? '',
            'download_url' => $row['download_url'] ?? '',
            'cover' => $row['cover'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function deriveSlug(array $row): string
    {
        if (! empty($row['slug'])) {
            return Str::slug((string) $row['slug']);
        }

        if (! empty($row['repository'])) {
            $path = parse_url((string) $row['repository'], PHP_URL_PATH) ?? '';
            $path = trim($path, '/');
            $basename = basename($path);

            if ($basename !== '') {
                return Str::slug($basename);
            }
        }

        return Str::slug((string) ($row['name'] ?? 'extension'));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function deriveModuleName(array $row, string $slug): string
    {
        if (! empty($row['module_name'])) {
            return (string) $row['module_name'];
        }

        return Str::studly(str_replace('-', '_', $slug));
    }
}
