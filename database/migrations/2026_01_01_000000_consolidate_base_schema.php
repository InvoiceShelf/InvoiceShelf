<?php

use App\Platform\Operations\Database\SchemaConsolidationGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the 150 historical migrations with the 2.4.x boundary schema.
 *
 * {@see SchemaConsolidationGuard} decides what happens: an empty database is
 * built, a database that already ran the replaced chain is left as it is
 * (only its stale history rows are pruned), and anything in between is
 * refused before any write.
 *
 * Portable schema-builder calls only. The two driver-specific spots are
 * commented where they occur: the `after()` placements (MySQL-only) and the
 * legacy columns only SQLite databases carry.
 */
return new class extends Migration
{
    /**
     * The version the replaced chain left in the settings table.
     */
    private const BOUNDARY_VERSION = '1.3.0';

    /**
     * Unused columns only SQLite databases carry: three replaced migrations
     * skipped their column drops on SQLite. Reproduced so a fresh SQLite
     * database matches an upgraded one.
     *
     * @var array<string, string> table => the column it kept
     */
    private const SQLITE_RETAINED_COLUMNS = [
        'users' => 'company_id',
        'estimates' => 'user_id',
        'expenses' => 'user_id',
        'invoices' => 'user_id',
        'payments' => 'user_id',
    ];

    /**
     * True when this database is one of the SQLite ones described above.
     */
    private bool $retainsLegacyColumns = false;

    public function up(): void
    {
        $verdict = SchemaConsolidationGuard::inspect();

        if ($verdict->isAbort()) {
            $verdict->fail();
        }

        if (! $verdict->isBuild()) {
            // SKIP: schema untouched, stale history rows pruned. The
            // framework records this file when the body returns.
            $this->pruneStaleHistory();

            return;
        }

        $this->retainsLegacyColumns = Schema::getConnection()->getDriverName() === 'sqlite';

        $this->createStandaloneTables();
        $this->createAccountTables();
        $this->createCatalogTables();
        $this->createContactTables();
        $this->createSpendingTables();
        $this->createBillingTables();
        $this->createTaxTables();
        $this->createAuthorizationTables();
        $this->placeRelocatedColumns();

        $this->seedFileDisks();
        $this->seedVersion();
    }

    /**
     * Irreversible: SKIP ran nothing to undo, and on a fresh database a
     * rollback would drop every table in the product.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'The base schema consolidation is irreversible: on an upgraded database its forward run '
            .'made no changes, so a rollback cannot know what to undo. Development environments should '
            .'rebuild the database from scratch instead of rolling this migration back.'
        );
    }

    /**
     * Delete the history rows for the 150 replaced files plus the one
     * 2.4.x-only name. Explicit name list only: module rows and any other
     * row survive. Writes on the connection this migration runs on.
     */
    private function pruneStaleHistory(): void
    {
        Schema::getConnection()
            ->table(SchemaConsolidationGuard::repositoryTable())
            ->whereIn('migration', SchemaConsolidationGuard::staleRecordedMigrations())
            ->delete();
    }

    /**
     * Tables that reference nothing, so nothing constrains their order.
     */
    private function createStandaloneTables(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('code');
            $table->string('symbol')->nullable();
            $table->integer('precision');
            $table->string('thousand_separator');
            $table->string('decimal_separator');
            $table->boolean('swap_currency_symbol')->default(false);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->string('name');
            $table->integer('phonecode');
            $table->index('id');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('option');
            $table->string('value')->nullable();
            $table->timestamps();
        });

        Schema::create('file_disks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('type')->default('REMOTE');
            $table->string('driver');
            $table->boolean('set_as_default')->default(false);
            $table->json('credentials');
            $table->timestamps();
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('version');
            $table->boolean('installed')->default(false);
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->unsignedInteger('size');
            $table->text('manipulations');
            $table->text('custom_properties');
            $table->text('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();
            $table->uuid('uuid')->nullable();
            $table->string('conversions_disk')->nullable();
            $table->json('generated_conversions')->nullable();
            $table->index(['model_type', 'model_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id']);
        });

        // Renamed from `password_resets` long after its index was named, and
        // the index kept the old name. It has no primary key.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            $table->index('email', 'password_resets_email_index');
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['tokenable_type', 'tokenable_id']);
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('from');
            $table->string('to');
            $table->string('subject');
            $table->text('body');
            $table->string('mailable_type');
            $table->string('mailable_id');
            $table->timestamps();
            $table->string('token')->nullable()->unique();
        });
    }

    /**
     * Users before companies (`companies.owner_id` points at users). The
     * reverse reference exists only on SQLite, which resolves foreign keys at
     * write time.
     */
    private function createAccountTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->default('user');
            $table->rememberToken();
            $table->string('facebook_id')->nullable();
            $table->string('google_id')->nullable();
            $table->string('github_id')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('website')->nullable();
            $table->boolean('enable_portal')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $this->addRetainedColumn($table, 'users');
            $table->timestamps();
            $table->unsignedInteger('creator_id')->nullable();

            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('unique_hash')->nullable();
            $table->timestamps();
            $table->string('slug')->nullable();
            $table->unsignedInteger('owner_id')->nullable();

            $table->foreign('owner_id')->references('id')->on('users');
        });

        Schema::create('user_company', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key');
            $table->text('value');
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('option');
            $table->text('value');
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('slug');
            $table->string('label');
            $table->string('model_type');
            $table->string('type');
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->boolean('boolean_answer')->nullable();
            $table->date('date_answer')->nullable();
            $table->time('time_answer')->nullable();
            $table->text('string_answer')->nullable();
            $table->unsignedBigInteger('number_answer')->nullable();
            $table->dateTime('date_time_answer')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedBigInteger('order')->default(1);
            $table->unsignedInteger('company_id');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('custom_field_valuable_type');
            $table->unsignedInteger('custom_field_valuable_id');
            $table->string('type');
            $table->boolean('boolean_answer')->nullable();
            $table->date('date_answer')->nullable();
            $table->time('time_answer')->nullable();
            $table->text('string_answer')->nullable();
            $table->unsignedBigInteger('number_answer')->nullable();
            $table->dateTime('date_time_answer')->nullable();
            $table->unsignedBigInteger('custom_field_id');
            $table->unsignedInteger('company_id');
            $table->timestamps();

            $table->foreign('custom_field_id')->references('id')->on('custom_fields');
            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type');
            $table->string('name');
            $table->text('notes');
            $table->timestamps();
            $table->unsignedInteger('company_id')->nullable();
            $table->boolean('is_default')->default(false);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::create('exchange_rate_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('base_currency_id')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('base_currency_id')->references('id')->on('currencies');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });

        Schema::create('exchange_rate_providers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('driver');
            $table->string('key');
            $table->json('currencies')->nullable();
            $table->json('driver_config')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * What the business sells.
     */
    private function createCatalogTables(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::create('items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('unit_id')->nullable();
            $table->timestamps();
            $table->unsignedInteger('creator_id')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->boolean('tax_per_item')->default(false);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });
    }

    /**
     * Who the business sells to.
     */
    private function createContactTables(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->string('facebook_id')->nullable();
            $table->string('google_id')->nullable();
            $table->string('github_id')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('website')->nullable();
            $table->boolean('enable_portal')->default(false);
            $table->unsignedInteger('currency_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('creator_id')->nullable();
            $table->timestamps();

            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('creator_id')->references('id')->on('users');
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('address_street_1')->nullable();
            $table->string('address_street_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->unsignedInteger('country_id')->nullable();
            $table->string('zip')->nullable();
            $table->string('phone')->nullable();
            $table->string('fax')->nullable();
            $table->string('type')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();

            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers');
        });
    }

    /**
     * Money going out.
     */
    private function createSpendingTables(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();
            $table->string('driver')->nullable();
            $table->enum('type', ['GENERAL', 'MODULE'])->default('GENERAL');
            $table->json('settings')->nullable();
            $table->boolean('active')->default(false);
            $table->boolean('use_test_env')->default(false);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        // `customer_id` and `currency_id` are plain indexed-free columns here:
        // the relationship is enforced in application code, not by the database.
        Schema::create('expenses', function (Blueprint $table) {
            $table->increments('id');
            $table->date('expense_date');
            $table->string('attachment_receipt')->nullable();
            $table->unsignedBigInteger('amount');
            $table->text('notes')->nullable();
            $table->unsignedInteger('expense_category_id');
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();
            $this->addRetainedColumn($table, 'expenses');
            $table->unsignedInteger('creator_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->unsignedBigInteger('base_amount')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->unsignedInteger('payment_method_id')->nullable();

            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
        });
    }

    /**
     * Money coming in.
     */
    private function createBillingTables(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('starts_at');
            $table->boolean('send_automatically')->default(false);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->enum('status', ['COMPLETED', 'ON_HOLD', 'ACTIVE'])->default('ACTIVE');
            $table->dateTime('next_invoice_at')->nullable();
            $table->unsignedInteger('creator_id')->nullable();
            $table->string('frequency');
            $table->enum('limit_by', ['NONE', 'COUNT', 'DATE'])->default('NONE');
            $table->integer('limit_count')->nullable();
            $table->date('limit_date')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->string('tax_per_item');
            $table->string('discount_per_item');
            $table->text('notes')->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('discount', 15, 2)->nullable();
            $table->unsignedBigInteger('discount_val')->nullable();
            $table->unsignedBigInteger('sub_total');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('tax');
            $table->string('template_name')->nullable();
            $table->unsignedBigInteger('due_amount');
            $table->timestamps();
            $table->string('sales_tax_type')->nullable();
            $table->string('sales_tax_address_type')->nullable();
            $table->boolean('tax_included')->default(false);

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('creator_id')->references('id')->on('users');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });

        // The money columns here are signed: credit-style invoices are allowed
        // to carry negative totals.
        Schema::create('invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('invoice_number');
            $table->string('reference_number')->nullable();
            $table->string('status');
            $table->string('paid_status');
            $table->string('tax_per_item');
            $table->string('discount_per_item');
            $table->text('notes')->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('discount', 15, 2)->nullable();
            $table->bigInteger('discount_val')->nullable();
            $table->bigInteger('sub_total');
            $table->bigInteger('total');
            $table->bigInteger('tax');
            $table->bigInteger('due_amount');
            $table->boolean('sent')->default(false);
            $table->boolean('viewed')->default(false);
            $table->string('unique_hash')->nullable();
            $this->addRetainedColumn($table, 'invoices');
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();
            $table->unsignedInteger('creator_id')->nullable();
            $table->string('template_name')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('recurring_invoice_id')->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->bigInteger('base_discount_val')->nullable();
            $table->bigInteger('base_sub_total')->nullable();
            $table->bigInteger('base_total')->nullable();
            $table->bigInteger('base_tax')->nullable();
            $table->bigInteger('base_due_amount')->nullable();
            $table->unsignedInteger('currency_id')->nullable();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('recurring_invoice_id')->references('id')->on('recurring_invoices');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type');
            $table->bigInteger('price');
            $table->decimal('quantity', 15, 2);
            $table->decimal('discount', 15, 2)->nullable();
            $table->bigInteger('discount_val');
            $table->bigInteger('tax');
            $table->bigInteger('total');
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::create('estimates', function (Blueprint $table) {
            $table->increments('id');
            $table->date('estimate_date');
            $table->date('expiry_date')->nullable();
            $table->string('estimate_number');
            $table->string('status');
            $table->string('reference_number')->nullable();
            $table->string('tax_per_item');
            $table->string('discount_per_item');
            $table->text('notes')->nullable();
            $table->decimal('discount', 15, 2)->nullable();
            $table->string('discount_type')->nullable();
            $table->unsignedBigInteger('discount_val')->nullable();
            $table->unsignedBigInteger('sub_total');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('tax');
            $table->string('unique_hash')->nullable();
            $this->addRetainedColumn($table, 'estimates');
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();
            $table->unsignedInteger('creator_id')->nullable();
            $table->string('template_name')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->unsignedBigInteger('base_discount_val')->nullable();
            $table->unsignedBigInteger('base_sub_total')->nullable();
            $table->unsignedBigInteger('base_total')->nullable();
            $table->unsignedBigInteger('base_tax')->nullable();
            $table->unsignedInteger('currency_id')->nullable();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });

        Schema::create('estimate_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type');
            $table->decimal('quantity', 15, 2);
            $table->decimal('discount', 15, 2)->nullable();
            $table->unsignedBigInteger('discount_val')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('tax');
            $table->unsignedBigInteger('total');
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('estimate_id');
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('estimate_id')->references('id')->on('estimates')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('transaction_id')->nullable();
            $table->string('unique_hash')->nullable();
            $table->string('type')->nullable();
            $table->string('status');
            $table->dateTime('transaction_date');
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('invoice_id');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('invoice_id')->references('id')->on('invoices');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('payment_number');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('unique_hash')->nullable();
            $this->addRetainedColumn($table, 'payments');
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('payment_method_id')->nullable();
            $table->timestamps();
            $table->unsignedInteger('creator_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->unsignedBigInteger('base_amount')->nullable();
            $table->unsignedInteger('currency_id')->nullable();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });
    }

    /**
     * Tax definitions and the tax lines they produce.
     */
    private function createTaxTables(): void
    {
        Schema::create('tax_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->decimal('percent', 5, 2)->nullable();
            $table->tinyInteger('compound_tax')->default(0);
            $table->tinyInteger('collective_tax')->default(0);
            $table->text('description')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();
            $table->enum('type', ['GENERAL', 'MODULE'])->default('GENERAL');

            $table->foreign('company_id')->references('id')->on('companies');
        });

        // Every document type that can carry tax points here, which is why the
        // table has nine foreign keys and eight nullable owners.
        Schema::create('taxes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tax_type_id');
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('estimate_id')->nullable();
            $table->unsignedInteger('invoice_item_id')->nullable();
            $table->unsignedInteger('estimate_item_id')->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->string('name');
            $table->bigInteger('amount');
            $table->decimal('percent', 5, 2)->nullable();
            $table->tinyInteger('compound_tax')->default(0);
            $table->timestamps();
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->unsignedBigInteger('base_amount')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->unsignedBigInteger('recurring_invoice_id')->nullable();

            $table->foreign('tax_type_id')->references('id')->on('tax_types');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('estimate_id')->references('id')->on('estimates')->onDelete('cascade');
            $table->foreign('invoice_item_id')->references('id')->on('invoice_items')->onDelete('cascade');
            $table->foreign('estimate_item_id')->references('id')->on('estimate_items')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('recurring_invoice_id')->references('id')->on('recurring_invoices');
        });
    }

    /**
     * Bouncer's four tables. Each foreign-key column gets an explicit index
     * so MySQL reuses it for the constraint, matching the boundary schema.
     */
    private function createAuthorizationTables(): void
    {
        Schema::create('abilities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('title')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->boolean('only_owned')->default(false);
            $table->json('options')->nullable();
            $table->integer('scope')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('title')->nullable();
            $table->unsignedInteger('level')->nullable();
            $table->integer('scope')->nullable()->index();
            $table->timestamps();

            $table->unique(['name', 'scope'], 'roles_name_unique');
        });

        Schema::create('assigned_roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_type');
            $table->unsignedBigInteger('restricted_to_id')->nullable();
            $table->string('restricted_to_type')->nullable();
            $table->integer('scope')->nullable()->index();

            $table->index(['entity_id', 'entity_type', 'scope'], 'assigned_roles_entity_index');
            $table->index('role_id', 'assigned_roles_role_id_index');
            $table->foreign('role_id')->references('id')->on('roles')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ability_id');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->boolean('forbidden')->default(false);
            $table->integer('scope')->nullable()->index();

            $table->index(['entity_id', 'entity_type', 'scope'], 'permissions_entity_index');
            $table->index('ability_id', 'permissions_ability_id_index');
            $table->foreign('ability_id')->references('id')->on('abilities')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Columns added mid-table by the replaced chain. `after()` positions them
     * on MySQL and is ignored elsewhere, so a built database matches an
     * upgraded one on every driver. Column order has no meaning to the app.
     */
    private function placeRelocatedColumns(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('vat_id')->nullable()->after('unique_hash');
            $table->string('tax_id')->nullable()->after('vat_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('prefix')->nullable()->after('id');
            $table->string('tax_id')->nullable()->after('github_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('expense_number')->nullable()->after('expense_date');
            $table->index('expense_number');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dateTime('expires_at')->nullable()->after('last_used_at');
        });

        Schema::table('tax_types', function (Blueprint $table) {
            $table->enum('calculation_type', ['percentage', 'fixed'])->default('percentage')->after('name');
            $table->integer('fixed_amount')->nullable()->after('percent');
        });

        Schema::table('taxes', function (Blueprint $table) {
            $table->enum('calculation_type', ['percentage', 'fixed'])->default('percentage')->after('name');
            $table->integer('fixed_amount')->nullable()->after('percent');
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->unsignedMediumInteger('sequence_number')->nullable()->after('id');
            $table->unsignedMediumInteger('customer_sequence_number')->nullable()->after('sequence_number');
            $table->string('sales_tax_type')->nullable()->after('currency_id');
            $table->string('sales_tax_address_type')->nullable()->after('sales_tax_type');
            $table->boolean('tax_included')->default(false)->after('sales_tax_address_type');
        });

        Schema::table('estimate_items', function (Blueprint $table) {
            $table->string('unit_name')->nullable()->after('quantity');
            $table->decimal('exchange_rate', 19, 6)->nullable()->after('updated_at');
            $table->unsignedBigInteger('base_discount_val')->nullable()->after('exchange_rate');
            $table->unsignedBigInteger('base_price')->nullable()->after('base_discount_val');
            $table->unsignedBigInteger('base_tax')->nullable()->after('base_price');
            $table->unsignedBigInteger('base_total')->nullable()->after('base_tax');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedMediumInteger('sequence_number')->nullable()->after('id');
            $table->unsignedMediumInteger('customer_sequence_number')->nullable()->after('sequence_number');
            $table->string('sales_tax_type')->nullable()->after('currency_id');
            $table->string('sales_tax_address_type')->nullable()->after('sales_tax_type');
            $table->boolean('overdue')->default(false)->after('sales_tax_address_type');
            $table->boolean('tax_included')->default(false)->after('overdue');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('unit_name')->nullable()->after('quantity');
            $table->unsignedBigInteger('recurring_invoice_id')->nullable()->after('updated_at');
            $table->unsignedBigInteger('base_price')->nullable()->after('recurring_invoice_id');
            $table->decimal('exchange_rate', 19, 6)->nullable()->after('base_price');
            $table->bigInteger('base_discount_val')->nullable()->after('exchange_rate');
            $table->bigInteger('base_tax')->nullable()->after('base_discount_val');
            $table->bigInteger('base_total')->nullable()->after('base_tax');

            $table->foreign('recurring_invoice_id')->references('id')->on('recurring_invoices');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedMediumInteger('sequence_number')->nullable()->after('id');
            $table->unsignedMediumInteger('customer_sequence_number')->nullable()->after('sequence_number');
            $table->unsignedBigInteger('transaction_id')->nullable()->after('currency_id');

            $table->foreign('transaction_id')->references('id')->on('transactions');
        });
    }

    /**
     * The two local disks every installation starts with. Paths come from
     * the running application, not from values baked into this file.
     */
    private function seedFileDisks(): void
    {
        $now = now();

        DB::table('file_disks')->insert([
            [
                'name' => 'public',
                'type' => 'SYSTEM',
                'driver' => 'local',
                'set_as_default' => false,
                'credentials' => json_encode([
                    'driver' => 'local',
                    'root' => config('filesystems.disks.public.root'),
                    'url' => config('app.url').'/storage',
                    'visibility' => 'public',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'local_private',
                'type' => 'SYSTEM',
                'driver' => 'local',
                'set_as_default' => true,
                'credentials' => json_encode([
                    'root' => config('filesystems.disks.local.root'),
                    'driver' => 'local',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Stamp the boundary version; the v3-era migrations move it forward.
     */
    private function seedVersion(): void
    {
        $now = now();

        $existing = DB::table('settings')->where('option', 'version')->exists();

        if ($existing) {
            DB::table('settings')
                ->where('option', 'version')
                ->update([
                    'value' => self::BOUNDARY_VERSION,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('settings')->insert([
            'option' => 'version',
            'value' => self::BOUNDARY_VERSION,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Declare one of the SQLite-only legacy columns, if this is SQLite.
     *
     * Called from inside the table's own closure so the column lands in the
     * position the historical chain left it in.
     */
    private function addRetainedColumn(Blueprint $table, string $forTable): void
    {
        if (! $this->retainsLegacyColumns) {
            return;
        }

        $column = self::SQLITE_RETAINED_COLUMNS[$forTable];
        $references = $column === 'company_id' ? 'companies' : 'users';

        $table->unsignedInteger($column)->nullable();
        $table->foreign($column)->references('id')->on($references)->onDelete('cascade');
    }
};
