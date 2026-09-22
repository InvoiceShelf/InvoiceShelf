<?php

namespace App\Domains\Metadata\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CustomFieldValueWriter
{
    /**
     * @param  int|string|null  $companyId  The company the caller is acting
     *                                      for. Needed only when the record
     *                                      cannot name one itself, as a user
     *                                      belonging to several cannot, and a
     *                                      slug is unique only within one.
     */
    public function attach(Model $valuable, iterable $customFields, int|string|null $companyId = null): void;

    public function update(Model $valuable, iterable $customFields, int|string|null $companyId = null): void;
}
