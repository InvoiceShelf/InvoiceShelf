<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            // Find the first user and their company to associate data with.
            $firstUser = User::first();
            if (! $firstUser) {
                $this->command->error('No users found. Please create a user before running this seeder.');

                return;
            }

            $company = $firstUser->companies()->first();
            if (! $company) {
                $this->command->error('The first user is not associated with any company.');

                return;
            }

            $this->command->info("Seeding data for Company #{$company->id} ({$company->name})...");

            // Create a base set of customers and items
            $customers = Customer::factory()->count(20)->forCompany($company)->create();
            $items = Item::factory()->count(30)->forCompany($company)->create();

            // Create a rich history of invoices, payments, and expenses
            for ($i = 0; $i < 200; $i++) {
                $invoiceDate = Carbon::now()->subDays(rand(0, 365));
                $dueDate = $invoiceDate->copy()->addDays(rand(15, 60));
                $customer = $customers->random();

                /** @var Invoice $invoice */
                $invoice = Invoice::factory()
                    ->forCompany($company)
                    ->forCustomer($customer)
                    ->create([
                        'invoice_date' => $invoiceDate,
                        'due_date' => $dueDate,
                        'creator_id' => $firstUser->id,
                    ]);

                // Add items to the invoice
                $invoiceItemsCount = rand(1, 5);
                $invoiceTotal = 0;
                for ($j = 0; $j < $invoiceItemsCount; $j++) {
                    $item = $items->random();
                    $quantity = rand(1, 10);
                    $price = $item->price;
                    $total = $quantity * $price;
                    $invoiceTotal += $total;

                    $invoice->items()->create([
                        'name' => $item->name,
                        'item_id' => $item->id,
                        'company_id' => $company->id,
                        'quantity' => $quantity,
                        'price' => $price,
                        'total' => $total,
                        'base_total' => $total, // Assuming same currency for simplicity
                        'discount_type' => 'fixed',
                        'discount_val' => 0,
                        'tax' => 0,
                    ]);
                }

                $invoice->total = $invoiceTotal;
                $invoice->due_amount = $invoiceTotal;
                $invoice->base_total = $invoiceTotal;
                $invoice->base_due_amount = $invoiceTotal;
                $invoice->save();

                // Create payments based on invoice status
                if ($dueDate->isPast()) {
                    // Overdue or Paid
                    if (rand(0, 1) === 1) { // 50% chance of being paid
                        $paymentDate = $dueDate->copy()->subDays(rand(1, 10));
                        Payment::factory()->forCompany($company)->forCustomer($customer)->create([
                            'invoice_id' => $invoice->id,
                            'amount' => $invoiceTotal,
                            'base_amount' => $invoiceTotal,
                            'payment_date' => $paymentDate,
                        ]);
                        $invoice->paid_status = Invoice::STATUS_PAID;
                        $invoice->due_amount = 0;
                        $invoice->base_due_amount = 0;
                        $invoice->save();
                    } else {
                        // Overdue, maybe partially paid
                        if (rand(0, 3) === 1) { // 25% chance of being partially paid
                            $partialAmount = round($invoiceTotal / rand(2, 4));
                            $paymentDate = $dueDate->copy()->subDays(rand(1, 10));
                            Payment::factory()->forCompany($company)->forCustomer($customer)->create([
                                'invoice_id' => $invoice->id,
                                'amount' => $partialAmount,
                                'base_amount' => $partialAmount,
                                'payment_date' => $paymentDate,
                            ]);
                            $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
                            $invoice->due_amount = $invoiceTotal - $partialAmount;
                            $invoice->base_due_amount = $invoiceTotal - $partialAmount;
                            $invoice->save();
                        }
                    }
                }
            }
            
            // Seed expenses over the last year
            for ($i=0; $i < 150; $i++) { 
                Expense::factory()->forCompany($company)->create([
                    'expense_date' => Carbon::now()->subDays(rand(0, 365))
                ]);
            }

            // Seed some estimates
            Estimate::factory()->count(50)->forCompany($company)->create();

            $this->command->info('Demo data created successfully!');
        });
    }
} 