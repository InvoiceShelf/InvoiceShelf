<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the JSON envelope the SPA posts for logo, avatar and receipt
 * uploads: an object carrying a file `name` plus a `data:` URI whose payload
 * is base64.
 *
 * Every check has to pass. The file name's extension must be an accepted one,
 * because the upload is stored under that name and served with the type its
 * extension implies; the data URI must be well formed; and the decoded bytes
 * must sniff as an accepted type too. A name alone, or bytes alone, is not
 * enough: an image-looking payload named page.html would otherwise be stored
 * as an HTML page on the public disk.
 */
class Base64Mime implements ValidationRule
{
    /** Extensions that name the same type as an accepted one. */
    private const ALIASES = ['jpeg' => 'jpg', 'jpe' => 'jpg', 'jfif' => 'jpg'];

    private $extensions;

    /**
     * @param  array  $extensions  File extensions considered acceptable.
     * @return void
     */
    public function __construct(array $extensions)
    {
        $this->extensions = $extensions;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->passes($value)) {
            $fail('The '.$attribute.' must be a json with file of type: '.implode(', ', $this->extensions).' encoded in base64.');
        }
    }

    private function passes(mixed $value): bool
    {
        $envelope = is_string($value) ? json_decode(trim($value)) : null;

        if (! is_object($envelope)) {
            return false;
        }

        $name = is_string($envelope->name ?? null) ? $envelope->name : '';
        $uri = is_string($envelope->data ?? null) ? $envelope->data : '';

        if (! $this->accepts(pathinfo($name, PATHINFO_EXTENSION))) {
            return false;
        }

        if (! preg_match('/^data:\w+\/[\w\+]+;base64,([\w\+\=\/]+)$/', $uri, $matches)) {
            return false;
        }

        $bytes = base64_decode($matches[1], true);

        if ($bytes === false || $bytes === '') {
            return false;
        }

        $sniffed = finfo_buffer(finfo_open(), $bytes, FILEINFO_EXTENSION);

        if (! is_string($sniffed) || $sniffed === '???') {
            return false;
        }

        // A sniff may answer with several equivalent extensions joined by
        // slashes, e.g. "jpeg/jpg/jpe/jfif"; any one of them will do
        foreach (explode('/', $sniffed) as $candidate) {
            if ($this->accepts($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function accepts(string $extension): bool
    {
        $extension = strtolower($extension);

        return in_array(self::ALIASES[$extension] ?? $extension, $this->extensions, true);
    }
}
