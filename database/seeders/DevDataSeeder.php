<?php

namespace Database\Seeders;

use App\Facades\Hashids;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use App\Models\RecurringInvoice;
use App\Models\Setting;
use App\Models\TaxType;
use App\Models\Unit;
use App\Models\User;
use App\Services\SerialNumberFormatter;
use App\Space\InstallUtils;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade;

/**
 * Seeds the database with realistic fake data for development and manual testing.
 *
 * Usage:
 *   php artisan db:seed --class=DevDataSeeder
 *
 * This creates:
 *   - An admin user (admin@invoiceshelf.com / password: password)
 *   - Two additional staff users
 *   - One company with all default data (payment methods, units, roles)
 *   - Tax types (VAT, GST)
 *   - 15 catalogue items
 *   - 10 customers
 *   - 20 invoices across all statuses, each with 1–3 line items
 *   - 15 estimates across all statuses, each with 1–3 line items
 *   - 10 payments linked to paid/partially-paid invoices
 *   - 12 expenses across several categories
 *   - 3 recurring invoices
 */
class DevDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Admin user & company ───────────────────────────────────────────

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@invoiceshelf.com',
            'role' => 'super admin',
            'password' => 'password',
        ]);

        $company = Company::create([
            'name' => 'Acme Corp',
            'owner_id' => $admin->id,
            'slug' => 'acme-corp',
        ]);

        $company->unique_hash = Hashids::connection(Company::class)->encode($company->id);
        $company->save();
        $company->setupDefaultData();   // roles, payment methods, units, default settings

        $admin->companies()->attach($company->id);
        BouncerFacade::scope()->to($company->id);
        $admin->assign('super admin');

        $admin->setSettings([
            'language' => 'en',
        ]);

        CompanySetting::setSettings([
            'currency' => 4,   // USD
            'time_zone' => 'UTC',
            'language' => 'en',
            'fiscal_year' => '1-12',
            'tax_per_item' => 'NO',
            'discount_per_item' => 'NO',
        ], $company->id);

        Setting::setSetting('profile_complete', 'COMPLETED');
        InstallUtils::setCurrentVersion();

        $companyId = $company->id;

        // ── 2. Extra staff users ──────────────────────────────────────────────

        foreach ([
            ['name' => 'Jane Smith',  'email' => 'jane@invoiceshelf.com'],
            ['name' => 'Bob Johnson', 'email' => 'bob@invoiceshelf.com'],
        ] as $data) {
            $staffUser = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => 'admin',
                'password' => 'password',
            ]);
            $staffUser->companies()->attach($companyId);
            BouncerFacade::scope()->to($companyId);
            $staffUser->assign('admin');
        }

        // ── 3. Tax types ──────────────────────────────────────────────────────

        $vat = TaxType::create([
            'name' => 'VAT',
            'calculation_type' => 'percentage',
            'company_id' => $companyId,
            'percent' => 20,
            'description' => 'Value Added Tax (20%)',
            'compound_tax' => 0,
            'collective_tax' => 0,
        ]);

        $gst = TaxType::create([
            'name' => 'GST',
            'calculation_type' => 'percentage',
            'company_id' => $companyId,
            'percent' => 10,
            'description' => 'Goods and Services Tax (10%)',
            'compound_tax' => 0,
            'collective_tax' => 0,
        ]);

        // ── 4. Units & catalogue items ────────────────────────────────────────

        $unit = Unit::where('company_id', $companyId)->first()
            ?? Unit::factory()->create(['company_id' => $companyId]);

        $itemData = [
            ['name' => 'Web Design',           'price' => 150000, 'description' => 'Custom website design'],
            ['name' => 'Logo Design',           'price' => 50000,  'description' => 'Brand logo design'],
            ['name' => 'SEO Audit',             'price' => 80000,  'description' => 'Full site SEO audit'],
            ['name' => 'Monthly Hosting',       'price' => 2000,   'description' => 'Shared hosting plan'],
            ['name' => 'Content Writing',       'price' => 10000,  'description' => 'Per 1 000 words'],
            ['name' => 'Social Media Package',  'price' => 60000,  'description' => 'Monthly social management'],
            ['name' => 'Email Marketing',       'price' => 35000,  'description' => 'Campaign design & send'],
            ['name' => 'CRM Integration',       'price' => 120000, 'description' => 'Third-party CRM setup'],
            ['name' => 'Mobile App Dev',        'price' => 500000, 'description' => 'iOS/Android application'],
            ['name' => 'E-Commerce Setup',      'price' => 200000, 'description' => 'Full shop configuration'],
            ['name' => 'Domain Registration',   'price' => 1500,   'description' => 'Annual domain fee'],
            ['name' => 'SSL Certificate',       'price' => 5000,   'description' => 'Annual SSL certificate'],
            ['name' => 'Google Ads Management', 'price' => 45000,  'description' => 'PPC campaign management'],
            ['name' => 'Photography Session',   'price' => 30000,  'description' => 'Half-day studio shoot'],
            ['name' => 'Video Production',      'price' => 250000, 'description' => 'Corporate promo video'],
        ];

        $items = collect($itemData)->map(fn ($d) => Item::create([
            'name' => $d['name'],
            'description' => $d['description'],
            'price' => $d['price'],
            'company_id' => $companyId,
            'unit_id' => $unit->id,
            'creator_id' => $admin->id,
            'currency_id' => 1,
            'tax_per_item' => false,
        ]));

        // ── 5. Customers ──────────────────────────────────────────────────────

        $customers = Customer::factory()->count(10)->create(['company_id' => $companyId]);

        // ── 6. Invoices ───────────────────────────────────────────────────────

        $invoiceStatuses = [
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_SENT,
            Invoice::STATUS_SENT,
            Invoice::STATUS_VIEWED,
            Invoice::STATUS_VIEWED,
            Invoice::STATUS_COMPLETED,
            Invoice::STATUS_COMPLETED,
            Invoice::STATUS_UNPAID,
            Invoice::STATUS_UNPAID,
            Invoice::STATUS_UNPAID,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
            Invoice::STATUS_PAID,
            Invoice::STATUS_PAID,
            Invoice::STATUS_PAID,
            Invoice::STATUS_PAID,
            Invoice::STATUS_PAID,
            Invoice::STATUS_PAID,
        ];

        $invoices = collect($invoiceStatuses)->map(function (string $status, int $index) use ($companyId, $customers, $items) {
            $customer = $customers->random();
            $lineItems = $items->random(rand(1, 3));
            $subTotal = $lineItems->sum('price');
            $tax = (int) ($subTotal * 0.10);
            $total = $subTotal + $tax;
            $paidStatus = match ($status) {
                Invoice::STATUS_PAID => Invoice::STATUS_PAID,
                Invoice::STATUS_PARTIALLY_PAID => Invoice::STATUS_PARTIALLY_PAID,
                default => Invoice::STATUS_UNPAID,
            };
            $dueAmount = match ($paidStatus) {
                Invoice::STATUS_PAID => 0,
                Invoice::STATUS_PARTIALLY_PAID => (int) ($total / 2),
                default => $total,
            };

            $seq = (new SerialNumberFormatter)
                ->setModel(new Invoice)
                ->setCompany($companyId)
                ->setNextNumbers();

            $invoice = Invoice::create([
                'invoice_number' => $seq->getNextNumber(),
                'sequence_number' => $seq->nextSequenceNumber,
                'customer_sequence_number' => $seq->nextCustomerSequenceNumber,
                'reference_number' => 'REF-'.str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'invoice_date' => now()->subDays(rand(1, 120))->toDateString(),
                'due_date' => now()->addDays(rand(1, 30))->toDateString(),
                'status' => $status,
                'paid_status' => $paidStatus,
                'template_name' => 'invoice1',
                'sub_total' => $subTotal,
                'tax' => $tax,
                'total' => $total,
                'due_amount' => $dueAmount,
                'discount' => 0,
                'discount_val' => 0,
                'discount_type' => 'fixed',
                'tax_per_item' => 'NO',
                'tax_included' => false,
                'discount_per_item' => 'NO',
                'notes' => 'Thank you for your business.',
                'unique_hash' => str_random(60),
                'company_id' => $companyId,
                'customer_id' => $customer->id,
                'currency_id' => 1,
                'exchange_rate' => 1,
                'base_sub_total' => $subTotal,
                'base_tax' => $tax,
                'base_total' => $total,
                'base_discount_val' => 0,
                'base_due_amount' => $dueAmount,
            ]);

            foreach ($lineItems as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->price,
                    'quantity' => 1,
                    'total' => $item->price,
                    'tax' => 0,
                    'discount' => 0,
                    'discount_val' => 0,
                    'discount_type' => 'fixed',
                    'company_id' => $companyId,
                    'exchange_rate' => 1,
                    'base_price' => $item->price,
                    'base_total' => $item->price,
                    'base_discount_val' => 0,
                    'base_tax' => 0,
                ]);
            }

            return $invoice;
        });

        // ── 7. Estimates ──────────────────────────────────────────────────────

        $estimateStatuses = [
            Estimate::STATUS_DRAFT,
            Estimate::STATUS_DRAFT,
            Estimate::STATUS_DRAFT,
            Estimate::STATUS_SENT,
            Estimate::STATUS_SENT,
            Estimate::STATUS_VIEWED,
            Estimate::STATUS_VIEWED,
            Estimate::STATUS_ACCEPTED,
            Estimate::STATUS_ACCEPTED,
            Estimate::STATUS_ACCEPTED,
            Estimate::STATUS_REJECTED,
            Estimate::STATUS_REJECTED,
            Estimate::STATUS_EXPIRED,
            Estimate::STATUS_EXPIRED,
            Estimate::STATUS_EXPIRED,
        ];

        collect($estimateStatuses)->each(function (string $status, int $index) use ($companyId, $customers, $items) {
            $customer = $customers->random();
            $lineItems = $items->random(rand(1, 3));
            $subTotal = $lineItems->sum('price');
            $tax = (int) ($subTotal * 0.10);
            $total = $subTotal + $tax;

            $seq = (new SerialNumberFormatter)
                ->setModel(new Estimate)
                ->setCompany($companyId)
                ->setNextNumbers();

            $estimate = Estimate::create([
                'estimate_number' => $seq->getNextNumber(),
                'sequence_number' => $seq->nextSequenceNumber,
                'customer_sequence_number' => $seq->nextCustomerSequenceNumber,
                'reference_number' => 'EREF-'.str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'estimate_date' => now()->subDays(rand(1, 90))->toDateString(),
                'expiry_date' => now()->addDays(rand(15, 60))->toDateString(),
                'status' => $status,
                'template_name' => 'estimate1',
                'sub_total' => $subTotal,
                'tax' => $tax,
                'total' => $total,
                'discount' => 0,
                'discount_val' => 0,
                'discount_type' => 'fixed',
                'tax_per_item' => 'NO',
                'tax_included' => false,
                'discount_per_item' => 'NO',
                'notes' => 'This estimate is valid for 30 days.',
                'unique_hash' => str_random(60),
                'company_id' => $companyId,
                'customer_id' => $customer->id,
                'currency_id' => 1,
                'exchange_rate' => 1,
                'base_sub_total' => $subTotal,
                'base_tax' => $tax,
                'base_total' => $total,
                'base_discount_val' => 0,
            ]);

            foreach ($lineItems as $item) {
                EstimateItem::create([
                    'estimate_id' => $estimate->id,
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->price,
                    'quantity' => 1,
                    'total' => $item->price,
                    'tax' => 0,
                    'discount' => 0,
                    'discount_val' => 0,
                    'discount_type' => 'fixed',
                    'company_id' => $companyId,
                    'exchange_rate' => 1,
                    'base_price' => $item->price,
                    'base_total' => $item->price,
                    'base_discount_val' => 0,
                    'base_tax' => 0,
                ]);
            }
        });

        // ── 8. Payments (linked to paid/partially-paid invoices) ──────────────

        $paymentMethod = DB::table('payment_methods')
            ->where('company_id', $companyId)
            ->value('id');

        $paidInvoices = $invoices->filter(fn ($inv) => in_array($inv->paid_status, [
            Invoice::STATUS_PAID,
            Invoice::STATUS_PARTIALLY_PAID,
        ]));

        $paidInvoices->take(10)->each(function (Invoice $invoice) use ($companyId, $paymentMethod) {
            $amount = $invoice->paid_status === Invoice::STATUS_PAID
                ? $invoice->total
                : (int) ($invoice->total / 2);

            $seq = (new SerialNumberFormatter)
                ->setModel(new Payment)
                ->setCompany($companyId)
                ->setNextNumbers();

            Payment::create([
                'payment_number' => $seq->getNextNumber(),
                'sequence_number' => $seq->nextSequenceNumber,
                'customer_sequence_number' => $seq->nextCustomerSequenceNumber,
                'payment_date' => now()->subDays(rand(1, 60))->toDateString(),
                'amount' => $amount,
                'base_amount' => $amount,
                'notes' => 'Payment received. Thank you!',
                'unique_hash' => str_random(60),
                'company_id' => $companyId,
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'payment_method_id' => $paymentMethod,
                'currency_id' => 1,
                'exchange_rate' => 1,
            ]);
        });

        // ── 9. Expense categories & expenses ──────────────────────────────────

        $categoryNames = ['Travel', 'Office Supplies', 'Software Subscriptions', 'Marketing', 'Utilities'];

        $categories = collect($categoryNames)->map(fn ($name) => ExpenseCategory::create([
            'name' => $name,
            'company_id' => $companyId,
            'description' => "Expenses for {$name}",
        ]));

        $expenseDescriptions = [
            'Flight tickets to client meeting',
            'Hotel stay for conference',
            'Printer paper and ink cartridges',
            'Pens, notebooks, and folders',
            'Adobe Creative Cloud annual plan',
            'Slack Business subscription',
            'Google Workspace plan',
            'Facebook ads campaign',
            'LinkedIn sponsored posts',
            'Electricity bill',
            'Internet service bill',
            'Postage and shipping fees',
        ];

        collect($expenseDescriptions)->each(function (string $notes, int $index) use ($companyId, $customers, $categories) {
            Expense::create([
                'expense_date' => now()->subDays(rand(1, 90))->toDateString(),
                'expense_category_id' => $categories->random()->id,
                'expense_number' => 'EXP-'.str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'company_id' => $companyId,
                'amount' => rand(500, 100000),
                'base_amount' => rand(500, 100000),
                'notes' => $notes,
                'attachment_receipt' => null,
                'customer_id' => $customers->random()->id,
                'currency_id' => 1,
                'exchange_rate' => 1,
            ]);
        });

        // ── 10. Recurring invoices ────────────────────────────────────────────

        $recurringStatuses = ['ACTIVE', 'ON_HOLD', 'COMPLETED'];

        foreach ($recurringStatuses as $rStatus) {
            $customer = $customers->random();
            $lineItems = $items->random(2);
            $subTotal = $lineItems->sum('price');
            $total = (int) ($subTotal * 1.10);

            RecurringInvoice::create([
                'starts_at' => now()->subMonths(rand(1, 6))->toDateTimeString(),
                'send_automatically' => false,
                'status' => $rStatus,
                'tax_per_item' => 'NO',
                'tax_included' => false,
                'discount_per_item' => 'NO',
                'sub_total' => $subTotal,
                'total' => $total,
                'tax' => $total - $subTotal,
                'due_amount' => $total,
                'discount' => 0,
                'discount_val' => 0,
                'company_id' => $companyId,
                'customer_id' => $customer->id,
                'frequency' => '0 0 1 * *',   // monthly, 1st of month
                'limit_by' => 'NONE',
                'limit_count' => null,
                'limit_date' => null,
                'exchange_rate' => 1,
                'template_name' => 'invoice1',
            ]);
        }
    }
}
