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

        Schema::table('expenses', function (Blueprint $table): void {
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
        });

        // Bills and supplier credits share one document shape.
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

        $itemTables = [
            'bill_items' => 'bill_id',
            'supplier_credit_items' => 'supplier_credit_id',
        ];

        foreach ($itemTables as $name => $parent) {
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

        $allocationTables = [
            'supplier_payment_allocations' => 'supplier_payment_id',
            'supplier_credit_allocations' => 'supplier_credit_id',
        ];

        foreach ($allocationTables as $name => $parent) {
            Schema::create($name, function (Blueprint $table) use ($parent): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('company_id')->index();
                $table->unsignedBigInteger($parent)->index();
                $table->unsignedBigInteger('bill_id')->index();
                $table->bigInteger('amount');
                $table->bigInteger('base_amount');
                $table->timestamps();

                $uniqueName = $parent === 'supplier_payment_id'
                    ? 'supplier_payment_bill_unique'
                    : 'supplier_credit_bill_unique';

                $table->unique([$parent, 'bill_id'], $uniqueName);
            });
        }

        app(RolePresetService::class)->syncAll();
    }

    /**
     * The columns every supplier payment, refund, bill and credit carries.
     */
    private function record(Blueprint $table): void
    {
        $table->bigIncrements('id');
        $table->unsignedInteger('company_id')->index();
        $table->unsignedInteger('creator_id')->nullable();
        $table->unsignedBigInteger('supplier_id')->index();
        $table->unsignedInteger('currency_id');
        $table->decimal('exchange_rate', 20, 6)->default(1);
        $table->string('number')->nullable();
        $table->unsignedInteger('sequence_number')->nullable();
        $table->string('reference')->nullable();
        $table->string('status')->default('DRAFT');
        $table->text('notes')->nullable();
        $table->timestamp('voided_at')->nullable();
        $table->text('void_reason')->nullable();
        $table->timestamps();
        $table->index(['company_id', 'supplier_id', 'status']);
        $table->index(['company_id', 'sequence_number']);
    }

    public function down(): void
    {
        $tables = [
            'supplier_credit_allocations',
            'supplier_payment_allocations',
            'supplier_refunds',
            'supplier_payments',
            'supplier_credit_items',
            'bill_items',
            'supplier_credits',
            'bills',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        // SQLite rebuilds the table on a column drop and fails while the index still names it.
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
        Schema::dropIfExists('suppliers');
    }
};
