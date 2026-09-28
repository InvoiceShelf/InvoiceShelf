<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Purchases\Application\RecurringCostService;
use App\Domains\Purchases\Application\SupplierService;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\RecurringCost;
use App\Domains\Purchases\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

beforeEach(fn () => purchaseFixtures($this));
afterEach(fn () => CarbonImmutable::setTestNow());

function purchaseField($test, string $model = 'Bill', array $attributes = []): CustomField
{
    return CustomField::factory()->create(['order' => 0, ...$attributes, 'company_id' => $attributes['company_id'] ?? $test->companyId, 'model_type' => $model, 'type' => $attributes['type'] ?? 'Input', 'label' => $attributes['label'] ?? 'Purchase code', 'is_required' => $attributes['is_required'] ?? false, 'placement' => 'internal']);
}
function customPurchasePayload($test, string $model): array
{
    return $model === 'Supplier' ? ['name' => 'Custom supplier', 'currency_id' => $test->currencyId, 'payment_terms' => 30] : purchaseBillPayload($test, 10000);
}
function customRecurringPayload($test): array
{
    return ['name' => 'Monthly bill fields', 'supplier_id' => $test->supplier->id, 'mode' => 'BILL', 'frequency' => 'MONTH', 'interval' => 1, 'starts_at' => '2026-01-01', 'max_occurrences' => 3, 'due_days' => 14, 'auto_record_paid' => false, 'template' => purchaseBillPayload($test, 10000)];
}

test('purchase answers round trip, preserve omitted values and allow explicit clearing', function (string $model, string $endpoint) {
    $text = purchaseField($this, $model);
    $number = purchaseField($this, $model, ['type' => 'Number', 'number_answer' => 0]);
    $switch = purchaseField($this, $model, ['type' => 'Switch', 'boolean_answer' => false]);
    $payload = [...customPurchasePayload($this, $model), 'customFields' => [['id' => $text->id, 'value' => 'PO-123']]];
    $record = $this->postJson($endpoint, $payload)->assertSuccessful()->assertJsonCount(3, 'data.fields')->json('data');
    $fields = collect($record['fields'])->keyBy('custom_field_id');
    expect($fields[$text->id]['default_answer'])->toBe('PO-123')->and((int) $fields[$number->id]['default_answer'])->toBe(0)->and((bool) $fields[$switch->id]['default_answer'])->toBeFalse();
    $this->getJson($endpoint.'/'.$record['id'])->assertOk()->assertJsonCount(3, 'data.fields');
    $this->putJson($endpoint.'/'.$record['id'], customPurchasePayload($this, $model))->assertSuccessful()->assertJsonPath('data.fields.0.default_answer', 'PO-123');
    $payload['customFields'][0]['value'] = null;
    $this->putJson($endpoint.'/'.$record['id'], $payload)->assertSuccessful()->assertJsonPath('data.fields.0.default_answer', null);
})->with([['Supplier', '/api/v1/suppliers'], ['Bill', '/api/v1/bills']]);

test('purchase answer validation rejects wrong company, wrong model, duplicates and invalid values', function (string $model, string $endpoint) {
    $valid = purchaseField($this, $model, ['validation' => ['pattern' => '^PO-[0-9]+$']]);
    $wrongModel = purchaseField($this, $model === 'Supplier' ? 'Bill' : 'Supplier');
    $wrongCompany = purchaseField($this, $model, ['company_id' => Company::factory()->create()->id]);
    foreach ([$wrongModel, $wrongCompany] as $field) {
        $this->postJson($endpoint, [...customPurchasePayload($this, $model), 'customFields' => [['id' => $field->id, 'value' => 'PO-1']]])->assertUnprocessable()->assertJsonValidationErrors('customFields.0.id');
    }
    foreach (['bad-code', ['not a scalar']] as $value) {
        $this->postJson($endpoint, [...customPurchasePayload($this, $model), 'customFields' => [['id' => $valid->id, 'value' => $value]]])->assertUnprocessable()->assertJsonValidationErrors('customFields.0.value');
    }
    $answer = ['id' => $valid->id, 'value' => 'PO-1'];
    $this->postJson($endpoint, [...customPurchasePayload($this, $model), 'customFields' => [$answer, $answer]])->assertUnprocessable();
})->with([['Supplier', '/api/v1/suppliers'], ['Bill', '/api/v1/bills']]);

test('required purchase answers cannot be omitted or cleared and service calls enforce constraints', function () {
    $field = purchaseField($this, 'Supplier', ['is_required' => true, 'string_answer' => null]);
    $data = customPurchasePayload($this, 'Supplier');
    $this->postJson('/api/v1/suppliers', $data)->assertUnprocessable();
    expect(fn () => app(SupplierService::class)->save(null, $this->companyId, $this->user->id, $data))->toThrow(ValidationException::class);
    expect(Supplier::count())->toBe(1);
    $record = $this->postJson('/api/v1/suppliers', [...$data, 'customFields' => [['id' => $field->id, 'value' => 'Saved']]])->assertSuccessful()->json('data');
    $this->putJson('/api/v1/suppliers/'.$record['id'], [...$data, 'customFields' => [['id' => $field->id, 'value' => null]]])->assertUnprocessable();
    $this->getJson('/api/v1/suppliers/'.$record['id'])->assertOk()->assertJsonPath('data.fields.0.default_answer', 'Saved');
});

test('settled bill metadata remains editable without changing finances and void bills remain read only', function () {
    $field = purchaseField($this);
    $payload = purchaseBillPayload($this, 10000);
    $bill = $this->postJson('/api/v1/bills', $payload)->assertSuccessful()->json('data');
    $this->postJson('/api/v1/supplier-payments', ['supplier_id' => $this->supplier->id, 'currency_id' => $this->currencyId, 'exchange_rate' => 1, 'amount' => 10000, 'payment_date' => '2026-09-02', 'allocations' => [['bill_id' => $bill['id'], 'amount' => 10000]]])->assertSuccessful();
    $this->putJson('/api/v1/bills/'.$bill['id'], [...$payload, 'customFields' => [['id' => $field->id, 'value' => 'Approved']]])->assertSuccessful()->assertJsonPath('data.total', 10000)->assertJsonPath('data.due_amount', 0)->assertJsonPath('data.fields.0.default_answer', 'Approved');
    $fresh = Bill::find($bill['id']);
    expect($fresh->financial_locked_at)->not->toBeNull()->and($fresh->paymentAllocations()->count())->toBe(1);
    $fresh->update(['status' => 'VOID']);
    $this->putJson('/api/v1/bills/'.$bill['id'], [...$payload, 'customFields' => [['id' => $field->id, 'value' => 'Changed']]])->assertUnprocessable();
    expect($fresh->fields()->first()->defaultAnswer)->toBe('Approved');
});

test('purchase form definitions follow record permissions without custom field settings access', function (string $model, string $ability, string $class) {
    $field = purchaseField($this, $model);
    purchaseField($this, $model === 'Supplier' ? 'Bill' : 'Supplier');
    $user = User::factory()->create(['role' => 'customer']);
    $user->companies()->attach($this->companyId);
    BouncerFacade::scope()->to($this->companyId);
    BouncerFacade::allow($user)->to($ability, $class);
    BouncerFacade::refresh();
    Sanctum::actingAs($user);
    $this->getJson('/api/v1/purchase-options?custom_field_model='.$model)->assertOk()->assertJsonCount(1, 'data.custom_fields')->assertJsonPath('data.custom_fields.0.id', $field->id);
    $this->getJson('/api/v1/custom-fields')->assertForbidden();
    $this->getJson('/api/v1/purchase-options?custom_field_model='.($model === 'Supplier' ? 'Bill' : 'Supplier'))->assertForbidden();
})->with([['Supplier', 'create-supplier', Supplier::class], ['Bill', 'edit-bill', Bill::class], ['Bill', 'create-recurring-cost', RecurringCost::class]]);

test('recurring templates copy answers, preserve omitted edits and affect only future bills', function () {
    CarbonImmutable::setTestNow('2026-01-02');
    $field = purchaseField($this);
    $data = customRecurringPayload($this);
    $data['template']['customFields'] = [['id' => $field->id, 'value' => 'January']];
    $service = app(RecurringCostService::class);
    $schedule = $service->save(null, $this->companyId, $this->user->id, $data);
    expect($service->generate($schedule))->toBe(1);
    $data['template']['customFields'][0]['value'] = 'February';
    $service->save($schedule, $this->companyId, $this->user->id, $data);
    unset($data['template']['customFields']);
    $service->save($schedule, $this->companyId, $this->user->id, $data);
    CarbonImmutable::setTestNow('2026-02-02');
    expect($service->generate($schedule))->toBe(1);
    $bills = Bill::orderBy('id')->get();
    expect($bills[0]->fields()->first()->defaultAnswer)->toBe('January')->and($bills[1]->fields()->first()->defaultAnswer)->toBe('February');
});

test('recurring generation drops deleted definitions and retries missing required answers atomically', function () {
    CarbonImmutable::setTestNow('2026-01-02');
    $old = purchaseField($this);
    $data = customRecurringPayload($this);
    $data['template']['customFields'] = [['id' => $old->id, 'value' => 'Old']];
    $service = app(RecurringCostService::class);
    $schedule = $service->save(null, $this->companyId, $this->user->id, $data);
    $old->delete();
    $required = purchaseField($this, 'Bill', ['is_required' => true, 'string_answer' => null]);
    expect($service->generate($schedule))->toBe(0)->and(Bill::count())->toBe(0)->and($schedule->fresh()->next_run_at)->toBe('2026-01-01')->and($schedule->fresh()->last_error)->not->toBeNull();
    $data['template']['customFields'] = [['id' => $required->id, 'value' => 'Fixed']];
    $service->save($schedule, $this->companyId, $this->user->id, $data);
    expect($service->generate($schedule))->toBe(1)->and($service->generate($schedule))->toBe(0)->and($schedule->fresh()->last_error)->toBeNull();
    expect(Bill::first()->fields()->count())->toBe(1);
});

test('bill custom fields do not leak into credits or recurring expenses and answers clean up with owners', function () {
    $field = purchaseField($this);
    $this->postJson('/api/v1/supplier-credits', [...purchaseBillPayload($this), 'customFields' => [['id' => $field->id, 'value' => 'No']]])->assertUnprocessable();
    $bill = $this->postJson('/api/v1/bills', purchaseBillPayload($this))->assertSuccessful()->json('data');
    $model = Bill::find($bill['id']);
    $answerId = $model->fields()->first()->id;
    $model->delete();
    $this->assertDatabaseMissing('custom_field_values', ['id' => $answerId]);
});

test('supplier and bill definitions are offered in settings and remain internal', function (string $model) {
    $this->getJson('/api/v1/config?key=custom_field_models')->assertOk()->assertJsonFragment(['value' => $model]);
    $data = ['name' => 'Procurement tag', 'label' => 'Procurement tag', 'type' => 'Input', 'model_type' => $model, 'is_required' => false, 'order' => 1, 'placement' => 'internal'];
    $this->postJson('/api/v1/custom-fields', $data)->assertCreated()->assertJsonPath('data.model_type', $model)->assertJsonPath('data.placement', 'internal');
    $this->postJson('/api/v1/custom-fields', [...$data, 'placement' => 'document'])->assertUnprocessable()->assertJsonValidationErrors('placement');
})->with(['Supplier', 'Bill']);

test('purchase fields enforce dropdown, numeric and date constraints', function () {
    $number = purchaseField($this, 'Bill', ['type' => 'Number', 'validation' => ['min' => 0, 'max' => 10]]);
    $date = purchaseField($this, 'Bill', ['type' => 'Date', 'validation' => ['earliest' => '2026-01-01', 'latest' => '2026-12-31']]);
    $dropdown = purchaseField($this, 'Bill', ['type' => 'Dropdown', 'options' => ['Operations', 'Sales']]);
    foreach ([[$number, 11], [$date, '2027-01-01'], [$date, 'not a date'], [$dropdown, 'Not offered']] as [$field, $value]) {
        $this->postJson('/api/v1/bills', [...purchaseBillPayload($this), 'customFields' => [['id' => $field->id, 'value' => $value]]])->assertUnprocessable()->assertJsonValidationErrors('customFields.0.value');
    }
    expect(Bill::count())->toBe(0);
});

test('recurring custom field payloads are validated before service processing', function () {
    $data = customRecurringPayload($this);
    foreach (['not an array', [['value' => 'missing id']], [['id' => 999999, 'value' => 'unknown field']]] as $answers) {
        $data['template']['customFields'] = $answers;
        $this->postJson('/api/v1/recurring-costs', $data)->assertUnprocessable();
        expect(fn () => app(RecurringCostService::class)->save(null, $this->companyId, $this->user->id, $data))->toThrow(ValidationException::class);
    }
    $field = purchaseField($this);
    $data['mode'] = 'EXPENSE';
    $data['auto_record_paid'] = true;
    $data['template'] = ['amount' => 100, 'currency_id' => $this->currencyId, 'exchange_rate' => 1, 'expense_category_id' => $this->category->id, 'customFields' => [['id' => $field->id, 'value' => 'Bill answer']]];
    $this->postJson('/api/v1/recurring-costs', $data)->assertUnprocessable()->assertJsonValidationErrors('customFields');
    expect(RecurringCost::count())->toBe(0);
});
