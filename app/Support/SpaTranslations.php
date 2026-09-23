<?php

namespace App\Support;

/**
 * Strings from the SPA's translation files, for the few server-rendered
 * places that must read like the SPA.
 *
 * The files in `lang/` are nested JSON, which the framework's translator
 * cannot address, so they are read here directly. `{name}` placeholders are
 * filled in the way vue-i18n fills them.
 */
final class SpaTranslations
{
    /**
     * Locale codes whose translation file is named differently, mirroring
     * LOCALE_FILE_MAP in resources/scripts/plugins/i18n.ts.
     */
    private const LOCALE_FILE_MAP = [
        'zh_CN' => 'zh-cn',
        'pt_BR' => 'pt-br',
    ];

    /** @var array<string, array<string, mixed>> */
    private static array $messages = [];

    /**
     * The string at a dotted key, falling back to English, then to the key.
     *
     * @param  array<string, string>  $replace
     */
    public static function get(string $locale, string $key, array $replace = []): string
    {
        $line = data_get(self::messages($locale), $key) ?? data_get(self::messages('en'), $key);

        if (! is_string($line)) {
            return $key;
        }

        foreach ($replace as $name => $value) {
            $line = str_replace('{'.$name.'}', $value, $line);
        }

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    private static function messages(string $locale): array
    {
        if (! isset(self::$messages[$locale])) {
            $file = lang_path((self::LOCALE_FILE_MAP[$locale] ?? $locale).'.json');
            $decoded = preg_match('/^[A-Za-z_-]+$/', $locale) === 1 && is_file($file)
                ? json_decode((string) file_get_contents($file), true)
                : null;

            self::$messages[$locale] = is_array($decoded) ? $decoded : [];
        }

        return self::$messages[$locale];
    }
}
