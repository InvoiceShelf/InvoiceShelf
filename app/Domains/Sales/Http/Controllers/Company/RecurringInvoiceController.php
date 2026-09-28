<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Application\RecurringInvoiceService;
use App\Domains\Sales\Http\Requests\RecurringInvoiceActionRequest;
use App\Domains\Sales\Http\Requests\RecurringInvoiceRequest;
use App\Domains\Sales\Http\Resources\RecurringInvoiceResource;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

/**
 * The admin surface for standing orders — the schedules that mint invoices on
 * a cron expression.
 *
 * Each row is an invoice held in template form, so the write endpoints hand
 * the service the same three parcels a document does: the row's own columns,
 * the line items, and the document-level taxes.
 */
class RecurringInvoiceController extends Controller
{
    public function __construct(
        private readonly RecurringInvoiceService $recurringInvoiceService,
    ) {}

    /**
     * Page through the company's schedules.
     *
     * Alongside the page itself the payload carries how many schedules the
     * company holds in total, which the listing screen shows even when a
     * filter has narrowed the rows down to a handful.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', RecurringInvoice::class);

        $perPage = $request->has('limit') ? $request->input('limit') : 10;

        $schedules = RecurringInvoice::whereCompany()
            ->applyFilters($request->all())
            ->paginateData($perPage);

        $companyTotal = RecurringInvoice::whereCompany()->count();

        return RecurringInvoiceResource::collection($schedules)
            ->additional(['meta' => [
                'recurring_invoice_total_count' => $companyTotal,
            ]]);
    }

    /**
     * Set up a new schedule from the submitted template.
     */
    public function store(RecurringInvoiceRequest $request)
    {
        $this->authorize('create', RecurringInvoice::class);

        $schedule = $this->recurringInvoiceService->create(
            attributes: $request->getRecurringInvoicePayload(),
            items: $request->input('items'),
            taxes: $request->has('taxes') ? $request->input('taxes') : null,
            customFields: $this->customFields($request),
        );

        return new RecurringInvoiceResource($schedule);
    }

    /**
     * Show one schedule.
     */
    public function show(RecurringInvoice $recurringInvoice)
    {
        $this->authorize('view', $recurringInvoice);

        return new RecurringInvoiceResource($recurringInvoice);
    }

    /**
     * Restate a schedule, template and all.
     *
     * Items and taxes are replaced wholesale rather than reconciled, so the
     * submission is the schedule's new contents in full.
     */
    public function update(RecurringInvoiceRequest $request, RecurringInvoice $recurringInvoice)
    {
        $this->authorize('update', $recurringInvoice);

        $this->recurringInvoiceService->update(
            recurringInvoice: $recurringInvoice,
            attributes: $request->getRecurringInvoicePayload(),
            items: $request->input('items'),
            taxes: $request->has('taxes') ? $request->input('taxes') : null,
            customFields: $this->customFields($request),
        );

        return new RecurringInvoiceResource($recurringInvoice);
    }

    /**
     * Pause a schedule, or resume one from today.
     */
    public function action(RecurringInvoiceActionRequest $request, RecurringInvoice $recurringInvoice)
    {
        $this->authorize('update', $recurringInvoice);

        $schedule = $this->recurringInvoiceService->act($recurringInvoice, $request->validated('action'));

        return new RecurringInvoiceResource($schedule);
    }

    /**
     * Drop several schedules at once.
     *
     * The submitted ids are narrowed to the acting company before anything is
     * removed, so ids belonging elsewhere are quietly passed over. Invoices
     * already minted by a dropped schedule survive it — they are merely cut
     * loose from the parent.
     */
    public function delete(Request $request)
    {
        $this->authorize('delete multiple recurring invoices');

        $ids = RecurringInvoice::whereCompany()
            ->whereIn('id', $request->input('ids'))
            ->pluck('id');

        $this->recurringInvoiceService->delete($ids);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * The submitted custom-field values, or nothing when the payload carries
     * something the service cannot walk.
     */
    private function customFields(RecurringInvoiceRequest $request): ?iterable
    {
        $values = $request->input('customFields');

        return is_iterable($values) ? $values : null;
    }
}
