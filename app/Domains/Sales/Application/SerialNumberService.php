<?php

namespace App\Domains\Sales\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;

/**
 * Renders document numbers from a per-company format string.
 *
 * A format is a run of `{{NAME}}` / `{{NAME:value}}` tokens; anything outside a
 * recognised token contributes nothing to the result. Tokens naming a sequence
 * are resolved against the highest number already stored for the company (and,
 * for the per-customer sequence, the customer), so the rendered number is the
 * one the document about to be written should carry.
 */
class SerialNumberService
{
    public const VALID_PLACEHOLDERS = ['CUSTOMER_SERIES', 'SEQUENCE', 'DATE_FORMAT', 'SERIES', 'RANDOM_SEQUENCE', 'DELIMITER', 'CUSTOMER_SEQUENCE'];

    /**
     * Bytes a token name is spelled with.
     */
    private const NAME_BYTES = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ_';

    /**
     * Bytes a token value is spelled with when it runs longer than one byte.
     */
    private const VALUE_BYTES = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_';

    /**
     * Longest multi-byte token value that is still read as one value.
     */
    private const VALUE_LIMIT = 6;

    private $model;

    private $ob;

    private $customer;

    private $company;

    private $settingKey;

    private $sequenceScope = [];

    private bool $customerSequence = true;

    /**
     * @var string
     */
    public $nextSequenceNumber;

    /**
     * @var string
     */
    public $nextCustomerSequenceNumber;

    /**
     * Point the service at the model class whose rows carry the sequences.
     *
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Adopt an existing row's sequences so an update keeps its numbers.
     *
     * The per-customer sequence is only adopted while the row still belongs to
     * the customer in play; moving a document to another customer therefore
     * leaves it to be renumbered for the new one.
     */
    public function setModelObject($id = null)
    {
        $this->ob = $this->model::find($id);

        if ($this->ob && $this->ob->sequence_number) {
            $this->nextSequenceNumber = $this->ob->sequence_number;
        }

        if (isset($this->ob->customer_sequence_number, $this->customer)
            && $this->ob->customer_id == $this->customer->id) {
            $this->nextCustomerSequenceNumber = $this->ob->customer_sequence_number;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setCompany($company)
    {
        $this->company = $company;

        return $this;
    }

    /**
     * Resolve the customer the per-customer sequence and series belong to.
     *
     * @return $this
     */
    public function setCustomer($customer = null)
    {
        $this->customer = Customer::find($customer);

        return $this;
    }

    /**
     * Override the company setting the number format is read from.
     *
     * Without this the key is derived from the model class name, which is not
     * enough for documents that share a table (credit notes are Invoice rows
     * but carry their own format).
     *
     * @return $this
     */
    public function setSettingKey(string $key)
    {
        $this->settingKey = $key;

        return $this;
    }

    /**
     * Restrict the sequence lookups to a subset of the model's rows.
     *
     * Takes column => value constraints that are applied on top of the company
     * (and customer) filters, so documents sharing a table can each keep an
     * independent, gapless sequence.
     *
     * @return $this
     */
    public function setSequenceScope(array $constraints)
    {
        $this->sequenceScope = $constraints;

        return $this;
    }

    /**
     * Leave out the per-customer sequence, for documents whose table has no
     * `customer_id` (purchasing documents belong to suppliers).
     *
     * @return $this
     */
    public function withoutCustomerSequence()
    {
        $this->customerSequence = false;

        return $this;
    }

    /**
     * Render the number the next document should carry.
     *
     * Passing no format falls back to the company setting for this document
     * kind.
     *
     * @return string
     */
    public function getNextNumber(?string $format = null)
    {
        $derivedKey = strtolower(class_basename($this->model)).'_number_format';

        if ($format === null) {
            $format = CompanySetting::getSetting(
                $this->settingKey ?: $derivedKey,
                $this->company
            );
        }

        $this->setNextNumbers();

        return $this->generateSerialNumber($format);
    }

    /**
     * Fill in whichever of the two sequences is still unresolved.
     */
    public function setNextNumbers()
    {
        if (! $this->nextSequenceNumber) {
            $this->setNextSequenceNumber();
        }

        if ($this->customerSequence && ! $this->nextCustomerSequenceNumber) {
            $this->setNextCustomerSequenceNumber();
        }

        return $this;
    }

    /**
     * Resolve the company-wide sequence as one past the highest in use.
     *
     * @return $this
     */
    public function setNextSequenceNumber()
    {
        $highest = $this->scopedQuery()
            ->whereNotNull('sequence_number')
            ->orderByDesc('sequence_number')
            ->first();

        $this->nextSequenceNumber = $highest ? $highest->sequence_number + 1 : 1;

        return $this;
    }

    /**
     * Resolve the per-customer sequence as one past the highest in use.
     *
     * With no customer resolved the lookup falls back to customer 1 rather
     * than skipping the customer filter.
     *
     * @return self
     */
    public function setNextCustomerSequenceNumber()
    {
        $highest = $this->scopedQuery()
            ->where('customer_id', $this->customer ? $this->customer->id : 1)
            ->whereNotNull('customer_sequence_number')
            ->orderByDesc('customer_sequence_number')
            ->first();

        $this->nextCustomerSequenceNumber = $highest ? $highest->customer_sequence_number + 1 : 1;

        return $this;
    }

    /**
     * List the recognised tokens of a format, in the order they appear.
     *
     * Each entry is a `name` / `value` pair; a token written without a value
     * yields an empty string. Tokens whose name is not one this service knows
     * about are dropped, as is any text between tokens.
     */
    public static function getPlaceholders(string $format)
    {
        $recognised = collect();
        $end = strlen($format);
        $cursor = 0;

        while ($cursor < $end) {
            $token = self::readToken($format, $cursor, $end);

            if ($token === null) {
                $cursor++;

                continue;
            }

            [$name, $value, $cursor] = $token;

            if (in_array($name, self::VALID_PLACEHOLDERS)) {
                $recognised->push([
                    'name' => $name,
                    'value' => $value,
                ]);
            }
        }

        return $recognised;
    }

    /**
     * Read the token opening at the given offset, if there is one.
     *
     * Both the name/value split and the value itself are ambiguous: a value
     * may be written with or without a leading colon, and may be either a run
     * of word bytes or a single arbitrary byte. Candidates are therefore tried
     * longest-name first, colon first, and word-run before single byte; the
     * first spelling whose closing braces line up wins.
     *
     * @return array{0: string, 1: string, 2: int}|null name, value, offset just past the token
     */
    private static function readToken(string $format, int $start, int $end)
    {
        if (substr($format, $start, 2) !== '{{') {
            return null;
        }

        $nameAt = $start + 2;

        for ($width = self::runLength($format, self::NAME_BYTES, $nameAt, $end); $width > 0; $width--) {
            foreach ([true, false] as $colon) {
                $valueAt = $nameAt + $width;

                if ($colon) {
                    if (($format[$valueAt] ?? null) !== ':') {
                        continue;
                    }

                    $valueAt++;
                }

                $value = self::readValue($format, $valueAt, $end);

                if ($value !== null) {
                    return [substr($format, $nameAt, $width), $value[0], $value[1]];
                }
            }
        }

        return null;
    }

    /**
     * Read a token's value plus its closing braces at the given offset.
     *
     * @return array{0: string, 1: int}|null value, offset just past the closing braces
     */
    private static function readValue(string $format, int $at, int $end)
    {
        $run = self::runLength($format, self::VALUE_BYTES, $at, $end);

        if ($run > 0 && $run <= self::VALUE_LIMIT && substr($format, $at + $run, 2) === '}}') {
            return [substr($format, $at, $run), $at + $run + 2];
        }

        $byte = $format[$at] ?? null;

        if ($byte !== null && $byte !== "\n" && substr($format, $at + 1, 2) === '}}') {
            return [$byte, $at + 3];
        }

        if (substr($format, $at, 2) === '}}') {
            return ['', $at + 2];
        }

        return null;
    }

    /**
     * Count the bytes at the given offset that belong to the given set.
     */
    private static function runLength(string $format, string $bytes, int $at, int $end): int
    {
        return $at < $end ? strspn($format, $bytes, $at) : 0;
    }

    /**
     * Concatenate what every recognised token of the format renders to.
     *
     * @return string
     */
    private function generateSerialNumber(string $format)
    {
        $serialNumber = '';

        foreach (self::getPlaceholders($format) as $placeholder) {
            $serialNumber .= $this->renderPlaceholder($placeholder['name'], $placeholder['value']);
        }

        return $serialNumber;
    }

    /**
     * Render one token.
     *
     * A token whose name is not one of the computed ones (a series or a
     * delimiter) simply renders its own value.
     */
    private function renderPlaceholder(string $name, string $value): string
    {
        return match ($name) {
            'SEQUENCE' => str_pad($this->nextSequenceNumber, $value ?: 6, 0, STR_PAD_LEFT),
            'CUSTOMER_SEQUENCE' => str_pad($this->nextCustomerSequenceNumber, $value, 0, STR_PAD_LEFT),
            'DATE_FORMAT' => date($value ?: 'Y'),
            'RANDOM_SEQUENCE' => substr(bin2hex(random_bytes($value ?: 6)), 0, $value ?: 6),
            'CUSTOMER_SERIES' => isset($this->customer) ? ($this->customer->prefix ?? 'CST') : 'CST',
            default => $value,
        };
    }

    /**
     * Start a lookup narrowed to the company and the configured scope.
     */
    private function scopedQuery()
    {
        $query = $this->model::query()->where('company_id', $this->company);

        foreach ($this->sequenceScope as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }
}
