<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Application\QuoteService;
use App\Domains\Sales\Http\Requests\ChangeQuoteStatusRequest;
use App\Domains\Sales\Http\Requests\DeleteQuotesRequest;
use App\Domains\Sales\Http\Requests\QuotesRequest;
use App\Domains\Sales\Http\Requests\SendQuotesRequest;
use App\Domains\Sales\Http\Resources\InvoiceResource;
use App\Domains\Sales\Http\Resources\QuoteResource;
use App\Domains\Sales\Jobs\GenerateQuotePdfJob;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\Quote;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Arr;

/**
 * Company-scoped quote endpoints: listing, the write surface, bulk removal,
 * mailing, and the two document conversions.
 */
class QuotesController extends Controller
{
    public function __construct(private readonly QuoteService $quoteService) {}

    /**
     * Paginated quotes of the active company, joined to their customer so the
     * list can be filtered and sorted by customer name.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Quote::class);

        $filters = $request->all();
        $perPage = $request->has('limit') ? $request->input('limit') : 10;

        $page = Quote::query()
            ->whereCompany()
            ->join('customers', fn ($join) => $join->on('customers.id', '=', 'quotes.customer_id'))
            ->where(fn ($query) => $query->applyFilters(Arr::except($filters, ['orderByField', 'orderBy'])))
            ->when($request->filled('orderByField') || $request->filled('orderBy'), fn ($query) => $query->whereOrder($request->input('orderByField') ?: 'sequence_number', $request->input('orderBy') ?: 'desc'))
            ->select(['quotes.*', 'customers.name'])
            ->orderByDesc('created_at')
            ->paginateData($perPage);

        return QuoteResource::collection($page)->additional([
            'meta' => [
                'quote_total_count' => Quote::query()->whereCompany()->count(),
            ],
        ]);
    }

    /**
     * Persist a draft quote and queue the
     * PDF render.
     */
    public function store(QuotesRequest $request)
    {
        $this->authorize('create', Quote::class);

        $quote = $this->quoteService->create(...$this->writeArguments($request));

        GenerateQuotePdfJob::dispatch($quote);

        return QuoteResource::make($quote);
    }

    public function show(Request $request, Quote $quote)
    {
        $this->authorize('view', $quote);

        return QuoteResource::make($quote);
    }

    /**
     * Overwrite a quote — lines and taxes are replaced wholesale — and
     * re-render its PDF.
     */
    public function update(QuotesRequest $request, Quote $quote)
    {
        $this->authorize('update', $quote);

        $quote = $this->quoteService->update($quote, ...$this->writeArguments($request));

        GenerateQuotePdfJob::dispatch($quote, true);

        return QuoteResource::make($quote);
    }

    /**
     * Bulk removal. Ids outside the active company are silently skipped.
     */
    public function delete(DeleteQuotesRequest $request)
    {
        $this->authorize('delete multiple quotes');

        $ids = Quote::query()
            ->whereCompany()
            ->whereIn('id', $request->input('ids'))
            ->pluck('id');

        foreach (Quote::whereIn('id', $ids)->get() as $quote) {
            $this->quoteService->delete($quote);
        }

        return response()->json(['success' => true]);
    }

    public function send(SendQuotesRequest $request, Quote $quote)
    {
        $this->authorize('send quote', $quote);

        return response()->json(
            $this->quoteService->send($quote, $request->all())
        );
    }

    /**
     * Render the mail body the customer would receive, without sending it.
     */
    public function sendPreview(SendQuotesRequest $request, Quote $quote)
    {
        $this->authorize('send quote', $quote);

        $data = $this->quoteService->sendQuoteData($quote, $request->all());
        $data['url'] = $quote->quotePdfUrl;

        $renderer = new Markdown(view(), config('mail.markdown'));

        return $renderer->render('emails.send.quote', ['data' => $data]);
    }

    public function clone(Request $request, Quote $quote)
    {
        $this->authorize('view', $quote);
        $this->authorize('create', Quote::class);

        return QuoteResource::make($this->quoteService->clone($quote));
    }

    /**
     * Reading the source quote is checked on top of the invoice-create
     * ability so the conversion cannot reach across companies.
     */
    public function convertToInvoice(Request $request, Quote $quote)
    {
        $this->authorize('view', $quote);
        $this->authorize('create', Invoice::class);

        return InvoiceResource::make($this->quoteService->convertToInvoice($quote));
    }

    public function changeStatus(ChangeQuoteStatusRequest $request, Quote $quote)
    {
        $this->authorize('send quote', $quote);

        $this->quoteService->changeStatus($quote, $request->input('status'));

        return response()->json(['success' => true]);
    }

    /**
     * The arguments create() and update() share, keyed by parameter name.
     *
     * @return array<string, mixed>
     */
    private function writeArguments(QuotesRequest $request): array
    {
        $fields = $request->input('customFields');

        return [
            'attributes' => $request->getQuotePayload(),
            'items' => $request->input('items'),
            'taxes' => $request->has('taxes') ? $request->input('taxes') : null,
            'customFields' => is_iterable($fields) ? $fields : null,
        ];
    }
}
