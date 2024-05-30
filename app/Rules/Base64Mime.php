<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Base64Mime implements ValidationRule
{
    private $attribute;

    private $extensions;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(array $extensions)
    {
        $this->extensions = $extensions;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->attribute = $attribute;
        $failed = false;

        try {
            $decoded = json_decode(trim($value));
            $name = ! empty($decoded->name) ? $decoded->name : '';
            $data = ! empty($decoded->data) ? $decoded->data : '';
        } catch (\Exception $e) {
            $failed = true;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        if (! in_array($extension, $this->extensions)) {
            $failed = true;
        }

        $pattern = '/^data:\w+\/[\w\+]+;base64,[\w\+\=\/]+$/';

        if (! preg_match($pattern, $data)) {
            $failed = true;
        }

        $data = explode(',', $data);

        if (! isset($data[1]) || empty($data[1])) {
            $failed = true;
        }

        try {
            $data = base64_decode($data[1]);
            $f = finfo_open();
            $result = finfo_buffer($f, $data, FILEINFO_EXTENSION);

            if ($result === '???') {
                $failed = true;
            }

            if (strpos($result, '/')) {
                foreach (explode('/', $result) as $ext) {
                    if (in_array($ext, $this->extensions)) {
                        $failed = false;
                    }
                }
            } else {
                if (in_array($result, $this->extensions)) {
                    $failed = false;
                }
            }
        } catch (\Exception $e) {
            $failed = true;
        }

        if ($failed) {
            $fail('The '.$this->attribute.' must be a json with file of type: '.implode(', ', $this->extensions).' encoded in base64.');
        }
    }
}
