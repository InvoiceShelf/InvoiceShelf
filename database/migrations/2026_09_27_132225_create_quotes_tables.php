<?php

use App\Domains\Accounts\Application\RolePresetService;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\RolePreset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('creator_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->index();
            $table->unsignedInteger('currency_id')->index();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->unsignedInteger('customer_sequence_number')->nullable();
            $table->date('quote_date');
            $table->date('expiry_date')->nullable();
            $table->string('quote_number');
            $table->string('status')->default('DRAFT')->index();
            $table->string('reference_number')->nullable();
            $table->string('tax_per_item')->default('NO');
            $table->string('discount_per_item')->default('NO');
            $table->text('notes')->nullable();
            $table->decimal('discount', 15, 2)->default(0);
            $table->string('discount_type')->default('fixed');
            foreach (['discount_val', 'sub_total', 'total', 'tax', 'base_discount_val', 'base_sub_total', 'base_total', 'base_tax'] as $column) {
                $table->unsignedBigInteger($column)->default(0);
            }
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->string('unique_hash')->nullable()->unique();
            $table->string('template_name')->nullable();
            $table->string('sales_tax_type')->nullable();
            $table->string('sales_tax_address_type')->nullable();
            $table->boolean('tax_included')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'quote_number']);
        });
        Schema::create('quote_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quote_id')->index();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('item_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit_name')->nullable();
            $table->decimal('quantity', 15, 2);
            $table->string('discount_type')->default('fixed');
            $table->decimal('discount', 15, 2)->default(0);
            foreach (['discount_val', 'price', 'tax', 'total', 'base_discount_val', 'base_price', 'base_tax', 'base_total'] as $column) {
                $table->unsignedBigInteger($column)->default(0);
            }
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->timestamps();
        });
        Schema::table('taxes', function (Blueprint $table) {
            $table->unsignedInteger('quote_id')->nullable()->index();
            $table->unsignedInteger('quote_item_id')->nullable()->index();
        });
        RolePreset::query()->whereIn('key', ['manager', 'read-only'])->each(function ($preset) {
            $abilities = $preset->abilities ?? [];
            foreach ($abilities as $ability) {
                if (str_ends_with($ability, '-estimate')) {
                    $abilities[] = str_replace('-estimate', '-quote', $ability);
                }
            }
            $preset->update(['abilities' => array_values(array_unique($abilities))]);
        });
        app(RolePresetService::class)->syncAll();
        // Copy defaults once for existing companies. Subsequent settings are independent.
        Company::query()->each(function (Company $company) {
            $settings = CompanySetting::getSettings(['estimate_mail_body', 'estimate_company_address_format', 'estimate_shipping_address_format', 'estimate_billing_address_format', 'estimate_email_attachment', 'estimate_set_expiry_date_automatically', 'estimate_expiry_date_days', 'estimate_convert_action', 'notify_estimate_viewed'], $company->id);
            $quotes = ['quote_number_format' => '{{SERIES:QUO}}{{DELIMITER:-}}{{SEQUENCE:6}}'];
            foreach ($settings as $name => $value) {
                $quotes[str_replace('estimate', 'quote', $name)] = strtr((string) $value, ['{ESTIMATE_DATE}' => '{QUOTE_DATE}', '{ESTIMATE_EXPIRY_DATE}' => '{QUOTE_EXPIRY_DATE}', '{ESTIMATE_NUMBER}' => '{QUOTE_NUMBER}', '{ESTIMATE_REF_NUMBER}' => '{QUOTE_REF_NUMBER}']);
            }
            // Preserve custom text and branding; translate only known defaults and tokens.
            if (($quotes['quote_mail_body'] ?? null) === 'You have received a new estimate from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:') {
                $quotes['quote_mail_body'] = 'You have received a new quote from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:';
            }
            $quotes['quote_convert_action'] = match ($quotes['quote_convert_action'] ?? 'no_action') {
                'delete_estimate' => 'delete_quote',
                'mark_estimate_as_accepted' => 'mark_quote_as_accepted',
                default => 'no_action',
            };
            CompanySetting::setSettings($quotes, $company->id);
        });
    }

    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->dropIndex(['quote_id']);
            $table->dropIndex(['quote_item_id']);
            $table->dropColumn(['quote_id', 'quote_item_id']);
        });
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
