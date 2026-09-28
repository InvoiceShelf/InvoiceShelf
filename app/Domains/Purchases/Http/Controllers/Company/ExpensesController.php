<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Purchases\Application\ExpenseService;
use App\Domains\Purchases\Contracts\ExpenseReceiptManager;
use App\Domains\Purchases\Data\PendingExpenseReceipt;
use App\Domains\Purchases\Http\Requests\DeleteExpensesRequest;
use App\Domains\Purchases\Http\Requests\ExpenseRequest;
use App\Domains\Purchases\Http\Requests\UploadExpenseReceiptRequest;
use App\Domains\Purchases\Http\Resources\ExpenseResource;
use App\Domains\Purchases\Models\Expense;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpensesController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly ExpenseReceiptManager $expenseReceiptManager,
    ) {}

    /**
     * Filtered, paginated expense list for the active company.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Expense::class);

        $filters = $request->all();
        $columns = ['expenses.*', 'expense_categories.name', 'customers.name as user_name'];

        $expenses = Expense::query()
            ->with(['category', 'creator', 'fields'])
            ->whereCompany()
            ->leftJoin('customers', 'expenses.customer_id', '=', 'customers.id')
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->applyFilters($filters)
            ->select($columns)
            ->paginateData($request->input('limit', 10));

        $total = Expense::whereCompany()->count();

        return ExpenseResource::collection($expenses)->additional([
            'meta' => ['expense_total_count' => $total],
        ]);
    }

    /**
     * Record a new expense, with its optional taxes, receipt and custom fields.
     */
    public function store(ExpenseRequest $request)
    {
        $this->authorize('create', Expense::class);

        $expense = $this->expenseService->create(
            attributes: $request->getExpensePayload(),
            taxes: $request->input('taxes'),
            receipt: $this->receipt($request),
            customFields: $this->customFields($request),
        );

        return new ExpenseResource($expense);
    }

    /**
     * Return a single expense together with its applied taxes.
     */
    public function show(Expense $expense)
    {
        $this->authorize('view', $expense);

        $expense->load('taxes.taxType');

        return new ExpenseResource($expense);
    }

    /**
     * Apply the submitted changes to an existing expense.
     */
    public function update(ExpenseRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $expense = $this->expenseService->update(
            expense: $expense,
            attributes: $request->getExpensePayload(),
            taxes: $request->input('taxes'),
            receipt: $this->receipt($request),
            removeReceipt: (bool) $request->input('is_attachment_receipt_removed'),
            customFields: $this->customFields($request),
        );

        return new ExpenseResource($expense);
    }

    /**
     * Drop every submitted expense that belongs to the active company.
     *
     * @return JsonResponse
     */
    public function delete(DeleteExpensesRequest $request)
    {
        $this->authorize('delete multiple expenses');

        $deletable = Expense::whereCompany()->whereIn('id', $request->ids)->pluck('id');

        $this->expenseService->delete($deletable->all(), (int) $request->header('company'));

        return response()->json(['success' => true]);
    }

    /**
     * Stream the stored receipt inline, if the expense has one.
     */
    public function showReceipt(Expense $expense)
    {
        $this->authorize('view', $expense);

        $receipt = $this->expenseReceiptManager->first($expense);

        if (! $receipt) {
            return respondJson('receipt_does_not_exist', 'Receipt does not exist.');
        }

        return response()->file($receipt->path);
    }

    /**
     * Store a base64 encoded receipt sent as a JSON blob.
     *
     * @return JsonResponse
     */
    public function uploadReceipt(UploadExpenseReceiptRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $payload = json_decode($request->attachment_receipt);

        if ($payload) {
            $this->expenseReceiptManager->attachBase64(
                $expense,
                $payload->data,
                $payload->name,
                $request->type === 'edit',
            );
        }

        return response()->json(['success' => 'Expense receipts uploaded successfully'], 200);
    }

    /**
     * Send the stored receipt back as a file download.
     */
    public function downloadReceipt(Expense $expense)
    {
        $this->authorize('view', $expense);

        $receipt = $this->expenseReceiptManager->first($expense);

        if (! $receipt) {
            return response()->json(['error' => 'receipt_not_found']);
        }

        $download = response()->download($receipt->path, $receipt->fileName);

        if (ob_get_contents()) {
            ob_end_clean();
        }

        return $download;
    }

    /** @return array<int, mixed>|null */
    private function customFields(ExpenseRequest $request): ?array
    {
        $submitted = $request->input('customFields');

        if (empty($submitted)) {
            return null;
        }

        $values = is_string($submitted) ? json_decode($submitted) : $submitted;

        return is_array($values) ? $values : null;
    }

    private function receipt(ExpenseRequest $request): ?PendingExpenseReceipt
    {
        $upload = $request->file('attachment_receipt');

        if (! $upload) {
            return null;
        }

        return new PendingExpenseReceipt($upload->getPathname(), $upload->getClientOriginalName());
    }
}
