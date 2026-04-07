<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PdfTemplateCatalog
{
    public function __construct(
        private readonly string $manifestUrl,
    ) {}

    public static function make(): self
    {
        return new self((string) config('invoiceshelf.extensions.templates_manifest_url'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTemplates(): array
    {
        $url = trim($this->manifestUrl);

        if ($url === '') {
            return [];
        }

        $response = Http::timeout(30)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'InvoiceShelf (+'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost').')',
            ])
            ->get($url);

        $response->throw();

        $payload = $response->json();

        return $this->normalizePayload($payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalizePayload(mixed $payload): array
    {
        if ($payload === null) {
            throw new \RuntimeException('Invalid PDF templates manifest: empty response.');
        }

        if (is_array($payload) && isset($payload['templates']) && is_array($payload['templates'])) {
            $rows = $payload['templates'];
        } elseif (is_array($payload)) {
            $rows = $payload;
        } else {
            throw new \RuntimeException('Invalid PDF templates manifest: unexpected root type.');
        }

        if ($rows !== [] && ! array_is_list($rows) && isset($rows['name'])) {
            $rows = [$rows];
        }

        /** @var array<int, array<string, mixed>> $filtered */
        $filtered = array_values(array_filter($rows, fn ($row) => is_array($row)));

        $out = [];
        foreach ($filtered as $row) {
            try {
                $out[] = $this->normalizeRow($row);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $slug = $this->deriveSlug($row);
        $type = strtolower((string) ($row['type'] ?? ''));

        if (! in_array($type, ['invoice', 'estimate'], true)) {
            throw new \RuntimeException('PDF template "'.$slug.'" has invalid type; must be "invoice" or "estimate".');
        }

        $templateName = trim((string) ($row['template_name'] ?? ''));
        if ($templateName === '') {
            throw new \RuntimeException('PDF template "'.$slug.'" is missing template_name (Blade basename without .blade.php).');
        }

        $cover = trim((string) ($row['cover'] ?? ''));
        if ($cover === '') {
            throw new \RuntimeException('PDF template "'.$slug.'" must include a cover URL (screenshot).');
        }

        $moduleName = $this->deriveModuleName($row, $slug);

        return [
            'catalog_kind' => 'pdf_template',
            'pdf_template_type' => $type,
            'template_name' => $templateName,
            'slug' => $slug,
            'module_name' => $moduleName,
            'name' => $row['name'] ?? $templateName,
            'description' => $row['description'] ?? '',
            'version' => $row['version'] ?? '0.0.0',
            'author' => $row['author'] ?? '',
            'license' => $row['license'] ?? '',
            'tags' => $row['tags'] ?? [],
            'compatibility' => $row['compatibility'] ?? [],
            'repository' => $row['repository'] ?? '',
            'download_url' => $row['download_url'] ?? '',
            'cover' => $cover,
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

        return Str::slug((string) ($row['name'] ?? 'template'));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function deriveModuleName(array $row, string $slug): string
    {
        if (! empty($row['module_name'])) {
            return (string) $row['module_name'];
        }

        return 'PdfTemplate_'.Str::studly(str_replace('-', '_', $slug));
    }
}
