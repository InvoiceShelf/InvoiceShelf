<?php

use App\Domains\Accounts\Application\RolePresetService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('creator_id')->nullable();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_id')->nullable();
            $table->unsignedInteger('currency_id');
            $table->unsignedInteger('expense_category_id')->nullable();
            $table->unsignedInteger('payment_terms')->default(30);
            $table->text('addresses')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'name']);
        });
        Schema::table('expenses', fn (Blueprint $table) => $table->unsignedBigInteger('supplier_id')->nullable()->index());
        foreach (['bills', 'supplier_credits'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $this->record($table);
                $table->date('document_date')->index();
                $table->date('due_date')->nullable()->index();
                $table->boolean('tax_included')->default(false);
                $table->timestamp('financial_locked_at')->nullable();
                $table->bigInteger('sub_total')->default(0);
                $table->bigInteger('tax')->default(0);
                $table->bigInteger('total')->default(0);
                $table->bigInteger('base_total')->default(0);
                $table->bigInteger('due_amount')->default(0);
                $table->bigInteger('base_due_amount')->default(0);
                $table->unsignedBigInteger('source_bill_id')->nullable()->index();
                $table->unsignedInteger('source_expense_id')->nullable()->index();
                $table->text('supplier_snapshot')->nullable();
                $table->unique(['company_id', 'number']);
            });
        }
        foreach (['bill_items' => 'bill_id', 'supplier_credit_items' => 'supplier_credit_id'] as $name => $parent) {
            Schema::create($name, function (Blueprint $table) use ($parent): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger($parent)->index();
                $table->unsignedInteger('company_id')->index();
                $table->unsignedInteger('expense_category_id')->index();
                $table->unsignedBigInteger('source_bill_item_id')->nullable()->index();
                $table->string('description', 1000);
                $table->decimal('quantity', 15, 2);
                $table->bigInteger('price');
                $table->decimal('discount', 15, 2)->default(0);
                $table->string('discount_type')->default('percentage');
                $table->bigInteger('sub_total');
                $table->bigInteger('discount_val')->default(0);
                $table->bigInteger('tax')->default(0);
                $table->bigInteger('total');
                $table->bigInteger('base_total');
                $table->text('taxes')->nullable();
                $table->timestamps();
            });
        }
        foreach (['supplier_payments', 'supplier_refunds'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $this->record($table);
                $table->date('payment_date')->index();
                $table->bigInteger('amount');
                $table->bigInteger('base_amount');
                $table->unsignedInteger('payment_method_id')->nullable()->index();
                if ($name === 'supplier_refunds') {
                    $table->unsignedBigInteger('supplier_payment_id')->nullable()->index();
                    $table->unsignedBigInteger('supplier_credit_id')->nullable()->index();
                }
                $table->unique(['company_id', 'number']);
            });
        }
        foreach (['supplier_payment_allocations' => 'supplier_payment_id', 'supplier_credit_allocations' => 'supplier_credit_id'] as $name => $parent) {
            Schema::create($name, function (Blueprint $table) use ($parent): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('company_id')->index();
                $table->unsignedBigInteger($parent)->index();
                $table->unsignedBigInteger('bill_id')->index();
                $table->bigInteger('amount');
                $table->bigInteger('base_amount');
                $table->timestamps();
                $table->unique([$parent, 'bill_id'], $parent === 'supplier_payment_id' ? 'supplier_payment_bill_unique' : 'supplier_credit_bill_unique');
            });
        }
        Schema::create('recurring_costs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('creator_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->string('name');
            $table->string('mode')->default('BILL');
            $table->string('status')->default('ACTIVE');
            $table->string('frequency')->default('MONTH');
            $table->unsignedInteger('interval')->default(1);
            $table->string('timezone');
            $table->date('starts_at');
            $table->date('next_run_at')->nullable()->index();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->unsignedInteger('occurrence_count')->default(0);
            $table->unsignedInteger('due_days')->default(30);
            $table->boolean('auto_record_paid')->default(false);
            $table->text('template');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('recurring_cost_occurrences', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedBigInteger('recurring_cost_id')->index();
            $table->date('scheduled_for');
            $table->string('record_type');
            $table->unsignedBigInteger('record_id');
            $table->timestamps();
            $table->unique(['recurring_cost_id', 'scheduled_for'], 'recurring_cost_occurrence_unique');
        });
        Schema::create('purchase_activities', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('action');
            $table->text('details')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
        app(RolePresetService::class)->syncAll();
    }

    private function record(Blueprint $table): void
    {
        $table->bigIncrements('id');
        $table->unsignedInteger('company_id')->index();
        $table->unsignedInteger('creator_id')->nullable();
        $table->unsignedBigInteger('supplier_id')->index();
        $table->unsignedInteger('currency_id');
        $table->decimal('exchange_rate', 20, 6)->default(1);
        $table->string('number')->nullable();
        $table->string('reference')->nullable();
        $table->string('status')->default('DRAFT');
        $table->text('notes')->nullable();
        $table->timestamp('voided_at')->nullable();
        $table->text('void_reason')->nullable();
        $table->timestamps();
        $table->index(['company_id', 'supplier_id', 'status']);
    }

    public function down(): void
    {
        foreach (['purchase_activities', 'recurring_cost_occurrences', 'recurring_costs', 'supplier_credit_allocations', 'supplier_payment_allocations', 'supplier_refunds', 'supplier_payments', 'supplier_credit_items', 'bill_items', 'supplier_credits', 'bills'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn('supplier_id'));
        Schema::dropIfExists('suppliers');
    }
};
