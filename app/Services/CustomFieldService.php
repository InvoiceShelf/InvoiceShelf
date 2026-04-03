<?php

namespace App\Services;

use App\Models\CustomField;
use Illuminate\Http\Request;

class CustomFieldService
{
    public function create(Request $request): CustomField
    {
        $data = $request->validated();
        $data[getCustomFieldValueKey($request->type)] = $request->default_answer;
        $data['company_id'] = $request->header('company');
        $data['slug'] = clean_slug($request->model_type, $request->name);

        return CustomField::create($data);
    }

    public function update(CustomField $customField, Request $request): CustomField
    {
        $data = $request->validated();
        $data[getCustomFieldValueKey($request->type)] = $request->default_answer;
        $customField->update($data);

        return $customField;
    }
}
