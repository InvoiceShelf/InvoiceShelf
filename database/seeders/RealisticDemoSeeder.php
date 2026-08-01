<?php

namespace Database\Seeders;

use App\Facades\Hashids;
use App\Models\Address;
use App\Models\AiConversation;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomField;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\RecurringInvoice;
use App\Models\Tax;
use App\Models\TaxType;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * ⚠️  DEVELOPMENT ONLY — DO NOT RUN IN PRODUCTION
 *
 * Populates the demo company with ~100 realistic records (8 customers, 12
 * catalog items, 6 expense categories, 35 invoices, ~20 payments, 8 estimates,
 * 15 expenses, 2 tax types, a notes library and a recurring invoice) so the app
 * looks like a real install during local development, and so the AI chat
 * assistant has meaningful data to query.
 *
 * This seeder is intentionally NOT wired into DatabaseSeeder and is NOT used
 * by the test suite (the minimal DemoSeeder remains in the test path to keep
 * test runs fast). To use:
 *
 *     php artisan db:seed --class=RealisticDemoSeeder --force
 *
 * Prerequisite: the standard DemoSeeder must have run first (creates the demo
 * user, company, company settings, and default payment methods / units via
 * CompanyService::setupDefaults()). If the demo user is missing, this seeder
 * will invoke DemoSeeder automatically before proceeding.
 *
 * Design notes:
 *
 *   - Records are created via Model::create() rather than factories. The
 *     existing InvoiceFactory / InvoiceItemFactory / ItemFactory have bugs
 *     (unconditional RecurringInvoice cascades, creator_id pointing at a
 *     nonexistent User->company_id field, hardcoded User::find(1) lookups)
 *     that make them unsuitable for seeding realistic data.
 *
 *   - Item prices and all monetary columns are stored in **cents**. A $250
 *     item has `price = 25000`. The frontend divides by 100 for display.
 *
 *   - Dates are deliberately distributed over the last 6 months so that
 *     AI tool queries like `get_company_stats(period=this_month)` vs
 *     `get_company_stats(period=last_month)` return different numbers.
 *
 *   - Invoice totals are computed from line items, not random. Tax is applied
 *     at document level (tax_per_item = 'NO') to most but not all documents,
 *     computed once off the subtotal and carried through total, due_amount and
 *     every base_* twin — the service layer trusts the amount it is given
 *     rather than recomputing it, so the arithmetic is the caller's to get
 *     right. No per-item tax or discount.
 */
class RealisticDemoSeeder extends Seeder
{
    private User $user;

    private int $companyId;

    /** @var array<int, Customer> */
    private array $customers = [];

    /** @var array<int, Item> */
    private array $items = [];

    /** @var array<int, ExpenseCategory> */
    private array $expenseCategories = [];

    private int $paymentMethodId;

    private int $unitId;

    private int $currencyId;

    private ?int $countryId = null;

    private int $invoiceSequence = 1;

    private int $estimateSequence = 1;

    private int $paymentSequence = 1;

    /** @var array<int, TaxType> */
    private array $taxTypes = [];

    /** @var array<int, string> */
    private array $invoiceNotes = [];

    public function run(): void
    {
        $this->ensureReferenceData();
        $this->resolveDemoContext();
        $this->cleanupExistingDemoData();

        $this->seedCompanyLogo();
        $this->seedTaxTypes();
        $this->seedNotes();
        $this->seedCustomFields();
        $this->seedCustomers();
        $this->seedCatalogItems();
        $this->seedExpenseCategories();
        $this->seedInvoicesWithPayments();
        $this->seedEstimates();
        $this->seedRecurringInvoice();
        $this->seedExpenses();

        $this->info(sprintf(
            'RealisticDemoSeeder done: %d customers, %d items, %d invoices (%d overdue, %d paid, %d partially_paid), %d payments, %d estimates, %d expenses, %d tax types, %d notes, %d recurring.',
            Customer::where('company_id', $this->companyId)->count(),
            Item::where('company_id', $this->companyId)->count(),
            Invoice::where('company_id', $this->companyId)->count(),
            Invoice::where('company_id', $this->companyId)->where('overdue', true)->count(),
            Invoice::where('company_id', $this->companyId)->where('paid_status', Invoice::STATUS_PAID)->count(),
            Invoice::where('company_id', $this->companyId)->where('paid_status', Invoice::STATUS_PARTIALLY_PAID)->count(),
            Payment::where('company_id', $this->companyId)->count(),
            Estimate::where('company_id', $this->companyId)->count(),
            Expense::where('company_id', $this->companyId)->count(),
            TaxType::where('company_id', $this->companyId)->count(),
            Note::where('company_id', $this->companyId)->count(),
            RecurringInvoice::where('company_id', $this->companyId)->count(),
        ));
    }

    /**
     * Find the demo user + company. If missing, run the base DemoSeeder first.
     */
    /**
     * Wrap command output so the seeder can also run programmatically
     * (e.g. from a test) without a Command instance attached.
     */
    private function info(string $message): void
    {
        if ($this->command !== null) {
            $this->command->info($message);
        }
    }

    /**
     * Verify the base reference tables (currencies, countries) have data.
     *
     * Both are seeded by `php artisan db:seed` / the installer via DatabaseSeeder
     * ahead of DemoSeeder. If they're empty, every downstream insert that
     * references them (customers.currency_id, addresses.country_id, items.currency_id…)
     * will fail with an opaque SQLite "FOREIGN KEY constraint failed" — so we bail
     * early with an actionable error.
     */
    private function ensureReferenceData(): void
    {
        if (Currency::count() === 0) {
            throw new RuntimeException(
                'Currencies table is empty. Run `php artisan db:seed --class=CurrenciesTableSeeder --force` first.'
            );
        }

        if (Country::count() === 0) {
            throw new RuntimeException(
                'Countries table is empty. Run `php artisan db:seed --class=CountriesTableSeeder --force` first.'
            );
        }

        // Resolve the USD currency (or fall back to whatever is at id=1 / first).
        $this->currencyId = Currency::where('code', 'USD')->first()?->id
            ?? Currency::find(1)?->id
            ?? Currency::first()->id;

        // Resolve country for demo addresses — prefer US if present, else first.
        $this->countryId = Country::where('code', 'US')->first()?->id
            ?? Country::find(1)?->id
            ?? Country::first()?->id;
    }

    private function resolveDemoContext(): void
    {
        $user = User::where('email', 'demo@invoiceshelf.com')->first();

        if ($user === null) {
            $this->info('Demo user missing; running DemoSeeder first…');
            Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
            $user = User::where('email', 'demo@invoiceshelf.com')->firstOrFail();
        }

        $this->user = $user;
        $this->companyId = $user->companies()->firstOrFail()->id;

        // CompanyService::setupDefaults() seeds default payment methods + units;
        // use whichever rows exist for this company. Fall back to creating one if
        // setupDefaults wasn't called (edge case — the standard DemoSeeder invokes it).
        $paymentMethod = PaymentMethod::where('company_id', $this->companyId)->first()
            ?? PaymentMethod::create(['name' => 'Bank Transfer', 'company_id' => $this->companyId]);
        $this->paymentMethodId = $paymentMethod->id;

        $unit = Unit::where('company_id', $this->companyId)->first()
            ?? Unit::create(['name' => 'pc', 'company_id' => $this->companyId]);
        $this->unitId = $unit->id;

        // Override the company's default currency to match the USD id we just
        // resolved. DemoSeeder hardcodes `'currency' => 1`, but since migration
        // 2025_08_18 inserts Algerian Dinar (DZD, symbol "DA") via firstOrCreate()
        // BEFORE CurrenciesTableSeeder runs, currency id 1 in a fresh install is
        // Algerian Dinar, not USD. Without this override the company's display
        // currency shows "DA" even though all our seeded records are priced in USD.
        CompanySetting::setSettings(
            ['currency' => (string) $this->currencyId],
            $this->companyId,
        );
    }

    /**
     * Wipe any previously seeded demo data so the seeder is idempotent — you
     * can re-run it after code changes without duplicating records.
     *
     * Deletes in child-first order to respect foreign keys. We deliberately
     * do NOT touch company_settings, the demo user, or the demo company.
     */
    private function cleanupExistingDemoData(): void
    {
        AiConversation::where('company_id', $this->companyId)->delete(); // cascades to ai_messages
        Payment::where('company_id', $this->companyId)->delete();
        InvoiceItem::where('company_id', $this->companyId)->delete();
        Invoice::where('company_id', $this->companyId)->delete();
        EstimateItem::where('company_id', $this->companyId)->delete();
        Estimate::where('company_id', $this->companyId)->delete();
        Expense::where('company_id', $this->companyId)->delete();
        ExpenseCategory::where('company_id', $this->companyId)->delete();

        // Customers: delete along with their addresses (addresses keyed by customer_id)
        $customerIds = Customer::where('company_id', $this->companyId)->pluck('id');
        Address::whereIn('customer_id', $customerIds)->delete();
        Customer::whereIn('id', $customerIds)->delete();

        Item::where('company_id', $this->companyId)->delete();

        // Taxes cascade from their documents, but the reusable definitions and
        // the standalone rows do not.
        Tax::where('company_id', $this->companyId)->delete();
        RecurringInvoice::where('company_id', $this->companyId)->delete();
        TaxType::where('company_id', $this->companyId)->delete();
        Note::where('company_id', $this->companyId)->delete();
        CustomField::where('company_id', $this->companyId)->delete();
    }

    private function seedCustomers(): void
    {
        $customers = [
            ['Acme Corp', 'Sarah Chen', 'ap@acmecorp.example', '+1-617-555-0142', 'https://acmecorp.example', '100 Industrial Way', 'Boston', 'MA', '02108'],
            ['Widget Industries LLC', 'Marcus Johnson', 'billing@widget.example', '+1-415-555-0173', 'https://widget.example', '2200 Market Street', 'San Francisco', 'CA', '94114'],
            ['Global Tech Solutions', 'Priya Patel', 'accounts@globaltech.example', '+1-512-555-0188', 'https://globaltech.example', '800 Congress Avenue', 'Austin', 'TX', '78701'],
            ['Blue Ridge Consulting', 'James Walker', 'finance@blueridge.example', '+1-303-555-0129', 'https://blueridge.example', '1700 Broadway', 'Denver', 'CO', '80202'],
            ['Apex Design Studio', 'Elena Rossi', 'hello@apexdesign.example', '+1-212-555-0164', 'https://apexdesign.example', '350 West 42nd Street', 'New York', 'NY', '10036'],
            ['Evergreen Media Group', 'Thomas Anderson', 'ap@evergreen.example', '+1-206-555-0115', 'https://evergreen.example', '1201 Third Avenue', 'Seattle', 'WA', '98101'],
            ['Summit Software', 'Maya Sharma', 'billing@summitsw.example', '+1-503-555-0197', 'https://summitsw.example', '621 SW Morrison Street', 'Portland', 'OR', '97205'],
            ['Pacific Logistics', 'Robert Kim', 'ap@pacificlog.example', '+1-213-555-0108', 'https://pacificlog.example', '400 South Hope Street', 'Los Angeles', 'CA', '90071'],
        ];

        foreach ($customers as [$companyName, $contactName, $email, $phone, $website, $street, $city, $state, $zip]) {
            $customer = Customer::create([
                'name' => $companyName,
                'company_name' => $companyName,
                'contact_name' => $contactName,
                'email' => $email,
                'phone' => $phone,
                'website' => $website,
                'enable_portal' => true,
                'currency_id' => $this->currencyId,
                'company_id' => $this->companyId,
                'creator_id' => $this->user->id,
            ]);

            // Billing + shipping addresses — created via the Customer relation so
            // customer_id is set automatically. We deliberately DO NOT set user_id
            // or company_id: customer addresses leave both null (the real app's
            // CustomerService follows the same pattern). If we did set company_id,
            // Company::address() — a bare hasOne with no scoping — would pick up
            // customer addresses and CompanyResource would try to serialize them,
            // triggering a Company → Address → User → Companies → Company circular
            // reference that crashes json_encode in BootstrapController.
            foreach ([Address::BILLING_TYPE, Address::SHIPPING_TYPE] as $type) {
                $customer->addresses()->create([
                    'name' => $companyName,
                    'address_street_1' => $street,
                    'city' => $city,
                    'state' => $state,
                    'country_id' => $this->countryId,
                    'zip' => $zip,
                    'phone' => $phone,
                    'type' => $type,
                ]);
            }

            $this->customers[] = $customer;
        }
    }

    private function seedCatalogItems(): void
    {
        // Format: [name, description, unit_price_in_cents]
        $items = [
            ['Senior Consulting', 'Strategic advisory work from a principal consultant (per hour)', 25000],
            ['Junior Consulting', 'Implementation and support from an associate consultant (per hour)', 12500],
            ['Web Development', 'Full-stack web development billed hourly', 18000],
            ['UX Design', 'User experience research and interface design (per hour)', 20000],
            ['Technical Writing', 'Documentation and knowledge base articles (per hour)', 15000],
            ['SEO Audit', 'Comprehensive site audit with prioritized recommendations', 150000],
            ['Brand Strategy Session', 'Half-day workshop with deliverables', 300000],
            ['Code Review Pack', 'Up to 20 hours of senior code review across a repo', 120000],
            ['On-site Training Day', 'Full-day training at the client site', 250000],
            ['Custom Integration Setup', 'Bespoke third-party integration, one-time setup', 500000],
            ['Starter License', 'Annual software license, Starter tier', 240000],
            ['Enterprise License', 'Annual software license, Enterprise tier', 1200000],
        ];

        foreach ($items as [$name, $description, $priceCents]) {
            $this->items[] = Item::create([
                'name' => $name,
                'description' => $description,
                'price' => $priceCents,
                'unit_id' => $this->unitId,
                'currency_id' => $this->currencyId,
                'tax_per_item' => false,
                'company_id' => $this->companyId,
                'creator_id' => $this->user->id,
            ]);
        }
    }

    private function seedExpenseCategories(): void
    {
        $names = [
            ['Software', 'SaaS subscriptions, licenses, developer tools'],
            ['Travel', 'Client visits, conferences, transportation'],
            ['Marketing', 'Advertising, content, campaigns'],
            ['Office Supplies', 'Stationery, furniture, office equipment'],
            ['Utilities', 'Internet, phone, electricity, coworking fees'],
            ['Contractors', 'Freelancers and independent contractors'],
        ];

        foreach ($names as [$name, $description]) {
            $this->expenseCategories[] = ExpenseCategory::create([
                'name' => $name,
                'description' => $description,
                'company_id' => $this->companyId,
            ]);
        }
    }

    /**
     * Create 35 invoices with a deliberate status + time distribution, then
     * back-fill payments for the PAID and PARTIALLY_PAID ones.
     */
    private function seedInvoicesWithPayments(): void
    {
        // Distribution plan: [count, status, paid_status, overdue, age_weeks_min, age_weeks_max]
        //
        // Split by time bucket so get_company_stats(period=this_month/last_month/this_quarter) differ.
        $plan = [
            // This month (0-4 weeks ago) — fresh activity
            [3, Invoice::STATUS_SENT,      Invoice::STATUS_UNPAID,         false, 0, 3],
            [2, Invoice::STATUS_VIEWED,    Invoice::STATUS_UNPAID,         false, 0, 4],
            [2, Invoice::STATUS_DRAFT,     Invoice::STATUS_UNPAID,         false, 0, 2],
            [3, Invoice::STATUS_COMPLETED, Invoice::STATUS_PAID,           false, 0, 4],
            [1, Invoice::STATUS_COMPLETED, Invoice::STATUS_PARTIALLY_PAID, false, 0, 4],
            // Last month (4-8 weeks ago)
            [2, Invoice::STATUS_VIEWED,    Invoice::STATUS_UNPAID,         false, 4, 8],
            [1, Invoice::STATUS_DRAFT,     Invoice::STATUS_UNPAID,         false, 4, 8],
            [2, Invoice::STATUS_SENT,      Invoice::STATUS_UNPAID,         true,  5, 8],  // overdue
            [3, Invoice::STATUS_COMPLETED, Invoice::STATUS_PAID,           false, 4, 8],
            [2, Invoice::STATUS_COMPLETED, Invoice::STATUS_PARTIALLY_PAID, false, 4, 8],
            // 2-3 months ago
            [2, Invoice::STATUS_SENT,      Invoice::STATUS_UNPAID,         true,  10, 13], // overdue
            [3, Invoice::STATUS_COMPLETED, Invoice::STATUS_PAID,           false, 8, 13],
            [2, Invoice::STATUS_COMPLETED, Invoice::STATUS_PARTIALLY_PAID, false, 9, 13],
            [1, Invoice::STATUS_VIEWED,    Invoice::STATUS_UNPAID,         false, 10, 13],
            // 4-6 months ago (older)
            [2, Invoice::STATUS_COMPLETED, Invoice::STATUS_PAID,           false, 16, 24],
            [1, Invoice::STATUS_COMPLETED, Invoice::STATUS_PARTIALLY_PAID, false, 16, 22],
            [1, Invoice::STATUS_VIEWED,    Invoice::STATUS_UNPAID,         false, 18, 24],
            [1, Invoice::STATUS_SENT,      Invoice::STATUS_UNPAID,         false, 18, 24],
            [1, Invoice::STATUS_DRAFT,     Invoice::STATUS_UNPAID,         false, 20, 26],
        ];

        foreach ($plan as [$count, $status, $paidStatus, $overdue, $minWeeks, $maxWeeks]) {
            for ($i = 0; $i < $count; $i++) {
                $weeksAgo = random_int($minWeeks, $maxWeeks);
                $invoiceDate = Carbon::now()->subWeeks($weeksAgo)->subDays(random_int(0, 6))->startOfDay();
                $dueDate = $overdue
                    ? Carbon::now()->subDays(random_int(3, 45))->startOfDay()
                    : $invoiceDate->copy()->addDays(30);

                $itemCount = random_int(1, 4);
                $this->createInvoice($invoiceDate, $dueDate, $status, $paidStatus, $itemCount, $overdue);
            }
        }
    }

    private function createInvoice(
        Carbon $invoiceDate,
        Carbon $dueDate,
        string $status,
        string $paidStatus,
        int $itemCount,
        bool $overdue,
    ): void {
        $customer = $this->customers[array_rand($this->customers)];
        $selectedItems = collect($this->items)->random($itemCount)->all();

        // Compute totals from the selected line items
        $lines = [];
        $subTotal = 0;
        foreach ($selectedItems as $item) {
            $quantity = random_int(1, 8);
            $lineTotal = $item->price * $quantity;
            $subTotal += $lineTotal;
            $lines[] = [
                'item' => $item,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        // Tax is computed once off the subtotal, not per line, and then has to be
        // carried through total, due_amount and every base_* twin. Miss due_amount
        // and a fully paid invoice renders as part-paid.
        $taxType = $this->taxTypeForDocument($this->invoiceSequence);
        $taxAmount = $taxType ? (int) round($subTotal * $taxType->percent / 100) : 0;

        $total = $subTotal + $taxAmount;
        $dueAmount = match ($paidStatus) {
            Invoice::STATUS_PAID => 0,
            Invoice::STATUS_PARTIALLY_PAID => (int) round($total * 0.6),  // 40% paid, 60% still due
            default => $total,
        };

        $invoiceNumber = 'INV-'.str_pad((string) $this->invoiceSequence, 6, '0', STR_PAD_LEFT);
        $this->invoiceSequence++;

        $invoice = Invoice::create([
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'invoice_number' => $invoiceNumber,
            'reference_number' => null,
            'template_name' => 'invoice1',
            'status' => $status,
            'paid_status' => $paidStatus,
            'overdue' => $overdue,
            'tax_per_item' => 'NO',
            'tax_included' => false,
            'discount_per_item' => 'NO',
            'discount_type' => 'fixed',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => $subTotal,
            'total' => $total,
            'tax' => $taxAmount,
            'due_amount' => $dueAmount,
            'exchange_rate' => 1,
            'base_discount_val' => 0,
            'base_sub_total' => $subTotal,
            'base_total' => $total,
            'base_tax' => $taxAmount,
            'base_due_amount' => $dueAmount,
            'currency_id' => $this->currencyId,
            'customer_id' => $customer->id,
            'company_id' => $this->companyId,
            'user_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'sent' => $status !== Invoice::STATUS_DRAFT,
            'viewed' => in_array($status, [Invoice::STATUS_VIEWED, Invoice::STATUS_COMPLETED], true),
            'notes' => $this->invoiceNotes === [] ? null : $this->invoiceNotes[$this->invoiceSequence % count($this->invoiceNotes)],
        ]);

        // Touch timestamps to match the invoice_date so tool queries like
        // `latest('invoice_date')` match `latest('created_at')` plausibly.
        // The PDF routes bind on unique_hash. Creating through the model rather
        // than InvoiceService skips the one place that normally assigns it, so
        // set it here or every seeded document 404s on preview and download.
        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->created_at = $invoiceDate;
        $invoice->updated_at = $invoiceDate;
        $invoice->save();

        foreach ($lines as $line) {
            InvoiceItem::create([
                'item_id' => $line['item']->id,
                'name' => $line['item']->name,
                'description' => $line['item']->description,
                'price' => $line['item']->price,
                'quantity' => $line['quantity'],
                'total' => $line['line_total'],
                'discount_type' => 'fixed',
                'discount' => 0,
                'discount_val' => 0,
                'tax' => 0,
                'invoice_id' => $invoice->id,
                'company_id' => $this->companyId,
                'exchange_rate' => 1,
                'base_price' => $line['item']->price,
                'base_discount_val' => 0,
                'base_tax' => 0,
                'base_total' => $line['line_total'],
            ]);
        }

        if ($taxType !== null) {
            $this->applyDocumentTax($invoice, $taxType, $taxAmount, 'invoice_id');
        }

        // Back-fill payments for PAID and PARTIALLY_PAID invoices.
        if ($paidStatus === Invoice::STATUS_PAID) {
            $this->createPayment($invoice, $total, $invoiceDate->copy()->addDays(random_int(3, 25)));
        } elseif ($paidStatus === Invoice::STATUS_PARTIALLY_PAID) {
            // 40% of the total, in one payment
            $partialAmount = $total - $dueAmount;
            $this->createPayment($invoice, $partialAmount, $invoiceDate->copy()->addDays(random_int(5, 20)));
        }
    }

    private function createPayment(Invoice $invoice, int $amount, Carbon $paymentDate): void
    {
        $paymentNumber = 'PAY-'.str_pad((string) $this->paymentSequence, 6, '0', STR_PAD_LEFT);
        $this->paymentSequence++;

        // Cap payment_date at today so `list_recent_payments(days=N)` doesn't
        // return future-dated rows for tests done close to the invoice_date.
        if ($paymentDate->isFuture()) {
            $paymentDate = Carbon::now()->subDays(random_int(1, 7));
        }

        $payment = Payment::create([
            'payment_number' => $paymentNumber,
            'payment_date' => $paymentDate->toDateString(),
            'amount' => $amount,
            'base_amount' => $amount,
            'exchange_rate' => 1,
            'user_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'payment_method_id' => $this->paymentMethodId,
            'currency_id' => $this->currencyId,
            'company_id' => $this->companyId,
            'notes' => null,
        ]);

        // See seedInvoice(): the PDF routes bind on unique_hash.
        $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);
        $payment->created_at = $paymentDate;
        $payment->updated_at = $paymentDate;
        $payment->save();
    }

    private function seedEstimates(): void
    {
        // Status mix inferred from Estimate model constants — use strings directly.
        $plan = [
            [2, 'DRAFT',    0,  3],
            [3, 'SENT',     2,  6],
            [2, 'ACCEPTED', 4,  8],
            [1, 'REJECTED', 8, 12],
        ];

        foreach ($plan as [$count, $status, $minWeeks, $maxWeeks]) {
            for ($i = 0; $i < $count; $i++) {
                $weeksAgo = random_int($minWeeks, $maxWeeks);
                $estimateDate = Carbon::now()->subWeeks($weeksAgo)->subDays(random_int(0, 6))->startOfDay();
                $expiryDate = $estimateDate->copy()->addDays(30);

                $this->createEstimate($estimateDate, $expiryDate, $status, random_int(1, 3));
            }
        }
    }

    private function createEstimate(Carbon $estimateDate, Carbon $expiryDate, string $status, int $itemCount): void
    {
        $customer = $this->customers[array_rand($this->customers)];
        $selectedItems = collect($this->items)->random($itemCount)->all();

        $lines = [];
        $subTotal = 0;
        foreach ($selectedItems as $item) {
            $quantity = random_int(1, 6);
            $lineTotal = $item->price * $quantity;
            $subTotal += $lineTotal;
            $lines[] = ['item' => $item, 'quantity' => $quantity, 'line_total' => $lineTotal];
        }

        // Same arithmetic as createInvoice(): one rounding off the subtotal,
        // then carried through total and the base_* twins.
        $taxType = $this->taxTypeForDocument($this->estimateSequence);
        $taxAmount = $taxType ? (int) round($subTotal * $taxType->percent / 100) : 0;
        $total = $subTotal + $taxAmount;

        $estimateNumber = 'EST-'.str_pad((string) $this->estimateSequence, 6, '0', STR_PAD_LEFT);
        $this->estimateSequence++;

        $estimate = Estimate::create([
            'estimate_date' => $estimateDate->toDateString(),
            'expiry_date' => $expiryDate->toDateString(),
            'estimate_number' => $estimateNumber,
            // Without this the estimate has no template and its PDF route 500s.
            // seedInvoice() has always set it; the estimate side never did.
            'template_name' => 'estimate1',
            'status' => $status,
            'tax_per_item' => 'NO',
            'tax_included' => false,
            'discount_per_item' => 'NO',
            'discount_type' => 'fixed',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => $subTotal,
            'total' => $total,
            'tax' => $taxAmount,
            'exchange_rate' => 1,
            'base_discount_val' => 0,
            'base_sub_total' => $subTotal,
            'base_total' => $total,
            'base_tax' => $taxAmount,
            'currency_id' => $this->currencyId,
            'customer_id' => $customer->id,
            'company_id' => $this->companyId,
            'user_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'notes' => null,
        ]);

        if ($taxType !== null) {
            $this->applyDocumentTax($estimate, $taxType, $taxAmount, 'estimate_id');
        }

        // See seedInvoice(): the PDF routes bind on unique_hash.
        $estimate->unique_hash = Hashids::connection(Estimate::class)->encode($estimate->id);
        $estimate->created_at = $estimateDate;
        $estimate->updated_at = $estimateDate;
        $estimate->save();

        foreach ($lines as $line) {
            EstimateItem::create([
                'item_id' => $line['item']->id,
                'name' => $line['item']->name,
                'description' => $line['item']->description,
                'price' => $line['item']->price,
                'quantity' => $line['quantity'],
                'total' => $line['line_total'],
                'discount_type' => 'fixed',
                'discount' => 0,
                'discount_val' => 0,
                'tax' => 0,
                'estimate_id' => $estimate->id,
                'company_id' => $this->companyId,
                'exchange_rate' => 1,
                'base_price' => $line['item']->price,
                'base_discount_val' => 0,
                'base_tax' => 0,
                'base_total' => $line['line_total'],
            ]);
        }
    }

    /**
     * Attach the demo company's logo.
     *
     * Without one the templates fall back to plain company-name text, which is
     * fine but reads as an unfinished install. The asset is a generated Acme Inc
     * mark rather than one of InvoiceShelf's own logos, which would make the
     * demo look as though InvoiceShelf were billing the customer.
     */
    private function seedCompanyLogo(): void
    {
        $company = Company::find($this->companyId);
        $logo = database_path('seeders/assets/acme-inc-logo.png');

        if (! file_exists($logo)) {
            return;
        }

        $company->clearMediaCollection('logo');
        $company->addMedia($logo)->preservingOriginal()->toMediaCollection('logo');
    }

    /**
     * Reusable tax definitions.
     *
     * type must be GENERAL: TaxTypesController::index() filters on it, so a
     * MODULE row would be invisible in the admin UI.
     */
    private function seedTaxTypes(): void
    {
        $definitions = [
            ['name' => 'Sales Tax', 'percent' => 8.5, 'description' => 'State and local sales tax'],
            ['name' => 'Zero Rated', 'percent' => 0, 'description' => 'Exempt supplies'],
        ];

        foreach ($definitions as $definition) {
            $this->taxTypes[] = TaxType::create([
                'name' => $definition['name'],
                'percent' => $definition['percent'],
                'calculation_type' => 'percentage',
                'compound_tax' => false,
                'collective_tax' => false,
                'description' => $definition['description'],
                'type' => TaxType::TYPE_GENERAL,
                'company_id' => $this->companyId,
            ]);
        }
    }

    /**
     * The reusable notes library, plus the text those notes put on documents.
     *
     * These are two unrelated things in this application: a Note row is a
     * snippet an operator inserts by hand, and a document's `notes` column is a
     * plain string copied at that moment. There is no foreign key between them,
     * and is_default only controls a badge in the settings list -- nothing
     * pre-fills a new document with it. So the library is seeded for the
     * settings screen, and the same text is put on documents separately.
     */
    private function seedNotes(): void
    {
        $notes = [
            ['type' => 'Invoice', 'name' => 'Payment terms', 'notes' => 'Payment is due within 14 days. Late payments may incur a 1.5% monthly charge.', 'is_default' => true],
            ['type' => 'Invoice', 'name' => 'Thank you', 'notes' => 'Thank you for your business. We appreciate the opportunity to work with you.', 'is_default' => false],
            ['type' => 'Estimate', 'name' => 'Estimate validity', 'notes' => 'This estimate is valid for 30 days from the date of issue.', 'is_default' => true],
            ['type' => 'Payment', 'name' => 'Receipt confirmation', 'notes' => 'Payment received with thanks. This receipt confirms the amount applied to your account.', 'is_default' => true],
        ];

        foreach ($notes as $note) {
            Note::create($note + ['company_id' => $this->companyId]);
        }

        $this->invoiceNotes = array_column(
            array_filter($notes, fn ($note) => $note['type'] === 'Invoice'),
            'notes'
        );
    }

    /**
     * Custom fields on customers.
     *
     * Customer is the only model_type with a create/edit UI end to end, so a
     * seeded field is both visible and editable. The PDF renders only
     * model_type 'Item' fields, which would add a column to the items table and
     * change a layout that was just squared up across both drivers -- left
     * alone deliberately.
     */
    private function seedCustomFields(): void
    {
        $fields = [
            ['name' => 'Account Manager', 'type' => 'Input', 'string_answer' => 'Dana Whitfield'],
            ['name' => 'Contract Renewal', 'type' => 'Date', 'date_answer' => Carbon::now()->addMonths(8)->toDateString()],
        ];

        foreach ($fields as $order => $field) {
            CustomField::create($field + [
                'label' => $field['name'],
                'model_type' => 'Customer',
                'slug' => clean_slug('Customer', $field['name']),
                'is_required' => false,
                'order' => $order + 1,
                'company_id' => $this->companyId,
            ]);
        }
    }

    /**
     * One active recurring invoice, so the feature is not an empty screen.
     *
     * frequency is a five-field cron expression, not a keyword, and
     * next_invoice_at has to be derived from it -- nothing computes that at read
     * time. Line items hang off recurring_invoice_id, leaving invoice_id null
     * until an invoice is actually generated.
     */
    private function seedRecurringInvoice(): void
    {
        $customer = $this->customers[0];
        $items = collect($this->items)->random(2)->all();

        $subTotal = 0;
        foreach ($items as $item) {
            $subTotal += $item->price;
        }

        $taxType = $this->taxTypes[0];
        $tax = (int) round($subTotal * $taxType->percent / 100);
        $total = $subTotal + $tax;

        $startsAt = Carbon::now()->startOfMonth()->addMonth();
        $frequency = '0 0 1 * *'; // monthly, on the first

        $recurring = RecurringInvoice::create([
            'starts_at' => $startsAt,
            'send_automatically' => false,
            'customer_id' => $customer->id,
            'company_id' => $this->companyId,
            'creator_id' => $this->user->id,
            'status' => RecurringInvoice::ACTIVE,
            'next_invoice_at' => RecurringInvoice::getNextInvoiceDate($frequency, $startsAt),
            'frequency' => $frequency,
            'limit_by' => RecurringInvoice::NONE,
            'currency_id' => $this->currencyId,
            'exchange_rate' => 1,
            'tax_per_item' => 'NO',
            'discount_per_item' => 'NO',
            'tax_included' => false,
            'discount_type' => 'fixed',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => $subTotal,
            'tax' => $tax,
            'total' => $total,
            'due_amount' => $total,
            'template_name' => 'invoice1',
            'notes' => $this->invoiceNotes[0] ?? null,
        ]);

        foreach ($items as $item) {
            InvoiceItem::create([
                'item_id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'price' => $item->price,
                'quantity' => 1,
                'total' => $item->price,
                'discount_type' => 'fixed',
                'discount' => 0,
                'discount_val' => 0,
                'tax' => 0,
                'recurring_invoice_id' => $recurring->id,
                'company_id' => $this->companyId,
                'exchange_rate' => 1,
                'base_price' => $item->price,
                'base_discount_val' => 0,
                'base_tax' => 0,
                'base_total' => $item->price,
            ]);
        }

        $this->applyDocumentTax($recurring, $taxType, $tax, 'recurring_invoice_id');
    }

    /**
     * Attach a document-level tax row.
     *
     * The service layer trusts whatever `amount` it is given rather than
     * recomputing it, so the caller owns the arithmetic and has to keep the
     * document's own tax/total columns in step. Every row must point at a real
     * TaxType: TaxResource dereferences it without a null check.
     */
    private function applyDocumentTax(object $document, TaxType $taxType, int $amount, string $foreignKey): void
    {
        Tax::create([
            'tax_type_id' => $taxType->id,
            $foreignKey => $document->id,
            'company_id' => $this->companyId,
            'name' => $taxType->name,
            'calculation_type' => 'percentage',
            'percent' => $taxType->percent,
            'amount' => $amount,
            'compound_tax' => false,
            'exchange_rate' => 1,
            'base_amount' => $amount,
            'currency_id' => $this->currencyId,
        ]);
    }

    /**
     * The tax type to apply to a document, or null for an untaxed one.
     *
     * Most documents are taxed, but not all: a demo where every row looks the
     * same shows less than one with a zero-rated example in it.
     */
    private function taxTypeForDocument(int $sequence): ?TaxType
    {
        if ($this->taxTypes === [] || $sequence % 5 === 0) {
            return null;
        }

        return $this->taxTypes[0];
    }

    private function seedExpenses(): void
    {
        // 15 expenses spread across categories and months
        // Format: [category_index, amount_cents, age_weeks_min, age_weeks_max, notes]
        $expenses = [
            [0, 12900,  0,  2, 'Figma — team plan'],       // Software
            [0, 49900,  2,  4, 'Notion — business plan'],
            [0, 89500,  6,  8, 'GitHub — team'],
            [1, 124000, 1,  3, 'Client site visit — flight'],  // Travel
            [1, 34500,  2,  4, 'Uber rides — SF'],
            [1, 258000, 8, 12, 'Conference ticket + hotel'],
            [2, 50000,  0,  2, 'LinkedIn Ads'],            // Marketing
            [2, 120000, 4,  6, 'Trade show booth'],
            [3, 8750,   1,  3, 'Printer paper + toner'],   // Office Supplies
            [3, 79900,  6,  8, 'Ergonomic chair'],
            [4, 19900,  0,  1, 'Office internet'],         // Utilities
            [4, 8500,   0,  1, 'Mobile phone plan'],
            [4, 45000,  4,  6, 'Coworking space'],
            [5, 450000, 2,  4, 'Freelance designer — website redesign'],  // Contractors
            [5, 280000, 6, 10, 'Contract developer — API integration'],
        ];

        foreach ($expenses as [$catIndex, $amount, $minWeeks, $maxWeeks, $notes]) {
            $weeksAgo = random_int($minWeeks, $maxWeeks);
            $expenseDate = Carbon::now()->subWeeks($weeksAgo)->subDays(random_int(0, 6))->startOfDay();

            $expense = Expense::create([
                'expense_date' => $expenseDate->toDateString(),
                'amount' => $amount,
                'base_amount' => $amount,
                'exchange_rate' => 1,
                'notes' => $notes,
                'expense_category_id' => $this->expenseCategories[$catIndex]->id,
                'currency_id' => $this->currencyId,
                'company_id' => $this->companyId,
                'creator_id' => $this->user->id,
            ]);

            $expense->created_at = $expenseDate;
            $expense->updated_at = $expenseDate;
            $expense->save();
        }
    }
}
