<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Export</title>
    <style>
        body { font-family: sans-serif; color: #333; }
        .container { width: 90%; margin: 0 auto; }
        .section { margin-bottom: 30px; border: 1px solid #eee; border-radius: 5px; padding: 15px; }
        .section-title { font-size: 20px; font-weight: bold; margin-bottom: 15px; color: #555; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f7f7f7; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { padding: 10px; }
        .card-title { font-weight: bold; margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Dashboard Report</h1>
        <p>Generated on: {{ date('Y-m-d H:i:s') }}</p>

        @if(isset($data['dashboard']))
            <div class="section">
                <div class="section-title">Dashboard Overview</div>
                <div class="grid-2">
                    <div class="card">
                        <div class="card-title">Summary</div>
                        @foreach($data['dashboard']['summary'] as $key => $value)
                            <div>{{ ucwords(str_replace('_', ' ', $key)) }}: <strong>{{ $value }}</strong></div>
                        @endforeach
                    </div>
                    <div class="card">
                        <div class="card-title">Status Distribution</div>
                        @foreach($data['dashboard']['distribution'] as $key => $value)
                            <div>{{ ucfirst($key) }}: <strong>{{ $value }}</strong></div>
                        @endforeach
                    </div>
                </div>
                <div class="card" style="margin-top: 20px;">
                    <div class="card-title">Top Outstanding Invoices</div>
                    <table class="table">
                        <thead><tr><th>Label</th><th>Value</th></tr></thead>
                        <tbody>
                            @foreach($data['dashboard']['outstanding'] as $item)
                                <tr><td>{{ $item['label'] }}</td><td>{{ $item['value'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(isset($data['cashflow']) && !empty($data['cashflow']))
            <div class="section">
                <div class="section-title">Cash Flow Analysis</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Invoice Totals</th>
                            <th>Expense Totals</th>
                            <th>Receipt Totals</th>
                            <th>Net Income</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['cashflow']['months'] as $index => $month)
                            <tr>
                                <td>{{ $month }}</td>
                                <td>{{ $data['cashflow']['invoice_totals'][$index] ?? 0 }}</td>
                                <td>{{ $data['cashflow']['expense_totals'][$index] ?? 0 }}</td>
                                <td>{{ $data['cashflow']['receipt_totals'][$index] ?? 0 }}</td>
                                <td>{{ $data['cashflow']['net_income_totals'][$index] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if(isset($data['invoices']) && !empty($data['invoices']))
            <div class="section">
                <div class="section-title">Recent Invoices</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Amount Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['invoices'] as $invoice)
                            <tr>
                                <td>{{ $invoice['invoice_number'] }}</td>
                                <td>{{ $invoice['status'] }}</td>
                                <td>{{ $invoice['invoice_date'] }}</td>
                                <td>{{ $invoice['customer']['name'] }}</td>
                                <td>{{ $invoice['total'] }}</td>
                                <td>{{ $invoice['due_amount'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</body>
</html> 