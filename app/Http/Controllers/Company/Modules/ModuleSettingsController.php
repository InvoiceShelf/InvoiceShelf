<?php

namespace App\Http\Controllers\Company\Modules;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvoiceShelf\Modules\Registry as ModuleRegistry;
use InvoiceShelf\Modules\Settings\Schema;

/**
 * Schema-driven module settings backend.
 *
 * Each active module's ServiceProvider::boot() calls
 * Registry::registerSettings($slug, $schema) once at app boot. This controller
 * exposes that schema to the frontend, validates submitted values against the
 * schema's per-field rules, and persists per-company values into CompanySetting
 * under the key prefix `module.{slug}.{field_key}`.
 *
 * Activation is instance-global, but settings are per-company — two companies
 * on the same instance can configure the same activated module differently.
 */
class ModuleSettingsController extends Controller
{
    public function show(Request $request, string $slug): JsonResponse
    {
        $this->authorize('manage modules');

        $schema = ModuleRegistry::settingsFor($slug);

        if ($schema === null) {
            abort(404, "Module '{$slug}' has not registered a settings schema.");
        }

        $values = collect($schema->fields())
            ->mapWithKeys(fn (array $field) => [
                $field['key'] => CompanySetting::getSetting(
                    "module.{$slug}.{$field['key']}",
                    $request->header('company')
                ) ?? $field['default'],
            ])
            ->all();

        return response()->json([
            'schema' => $this->translateSchema($schema->toArray()),
            'values' => $values,
        ]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $this->authorize('manage modules');

        $schema = ModuleRegistry::settingsFor($slug);

        if ($schema === null) {
            abort(404, "Module '{$slug}' has not registered a settings schema.");
        }

        $rules = $this->buildRules($schema);
        $allowedKeys = array_keys($rules);

        $validated = $request->validate($rules);

        $companyId = $request->header('company');

        // Only persist keys the schema knows about — silently drop unknown keys
        // rather than letting modules write arbitrary settings.
        $settingsToWrite = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $validated)) {
                $settingsToWrite["module.{$slug}.{$key}"] = $this->normalizeForStorage($validated[$key]);
            }
        }

        if ($settingsToWrite !== []) {
            CompanySetting::setSettings($settingsToWrite, $companyId);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Convert a Schema's field rule arrays into a flat Laravel validator rules array.
     *
     * Field rules are passed through verbatim — a field declared as
     * `'rules' => ['required', 'string', 'max:255']` becomes
     * `['my_field' => ['required', 'string', 'max:255']]`. The frontend's
     * BaseSchemaForm.vue understands a subset of these for client-side validation;
     * the backend validator is the source of truth.
     *
     * @return array<string, array<int, string>>
     */
    private function buildRules(Schema $schema): array
    {
        $rules = [];

        foreach ($schema->fields() as $field) {
            $rules[$field['key']] = $this->withTypeRule($field);
        }

        return $rules;
    }

    /**
     * Prepend a sensible per-type validation rule so booleans must be booleans,
     * numbers must be numeric, etc., even if the module didn't declare it.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, string>
     */
    private function withTypeRule(array $field): array
    {
        /** @var array<int, string> $declared */
        $declared = $field['rules'] ?? [];

        $typeRule = match ($field['type']) {
            'switch' => 'boolean',
            'number' => 'numeric',
            'multiselect' => 'array',
            default => 'nullable',
        };

        // Avoid duplicating the type rule if the module already declared it
        if (in_array($typeRule, $declared, true)) {
            return $declared;
        }

        return array_merge([$typeRule], $declared);
    }

    /**
     * CompanySetting stores everything as strings. Cast booleans, ints, and
     * arrays to a representation that round-trips through getSetting/setSetting
     * without losing information. Reads happen in show() above and naturally
     * return strings; the frontend handles re-coercion in BaseSchemaForm.vue.
     */
    /**
     * Translate section titles and field labels in the schema so the
     * frontend receives ready-to-display strings instead of Laravel
     * translation keys it cannot resolve (e.g. `helloworld::settings.greeting`).
     *
     * @param  array{sections: list<array<string, mixed>>}  $schema
     * @return array{sections: list<array<string, mixed>>}
     */
    private function translateSchema(array $schema): array
    {
        foreach ($schema['sections'] as &$section) {
            if (isset($section['title'])) {
                $section['title'] = __($section['title']);
            }

            foreach ($section['fields'] as &$field) {
                if (isset($field['label'])) {
                    $field['label'] = __($field['label']);
                }
            }
        }

        return $schema;
    }

    private function normalizeForStorage(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) ($value ?? '');
    }
}
