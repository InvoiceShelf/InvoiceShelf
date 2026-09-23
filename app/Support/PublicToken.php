<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The secret in a link that opens a document without signing in: a
 * document's `unique_hash`, or the token of the email that carried it.
 *
 * Random, so one link says nothing about any other. These used to be Hashids
 * of the row id, and Hashids reads only the start of its salt, so the APP_KEY
 * appended to it never counted: every installation gave id 1 the same token.
 */
final class PublicToken
{
    public const LENGTH = 40;

    public static function make(): string
    {
        return Str::random(self::LENGTH);
    }
}
