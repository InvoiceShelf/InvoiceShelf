<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Snapshot - {{ $company->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.4;
            color: #374151;
            background-color: #f9fafb;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .header .company-name {
            font-size: 18px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .header .generated-at {
            font-size: 14px;
            color: #9ca3af;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 2fr 1.5fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .live-badge {
            background-color: #f3f4f6;
            color: #6b7280;
            font-size: 12px;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .summary-metrics {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .metric {
            padding-bottom: 12px;
            border-bottom: 1px dashed #e5e7eb;
        }
        
        .metric:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .metric-label {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .metric-value {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .metric-value.highlight {
            font-size: 20px;
            font-weight: bold;
        }
        
        .chart-container {
            text-align: center;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chart-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
        }
        
        .chart-placeholder {
            color: #9ca3af;
            font-style: italic;
            background-color: #f9fafb;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 40px;
            width: 100%;
        }
        
        .full-width-card {
            grid-column: 1 / -1;
            margin-top: 20px;
        }
        
        .status-legend {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        
        .status-item {
            text-align: center;
            flex: 1;
            padding: 0 16px;
        }
        
        .status-item:not(:last-child) {
            border-right: 1px solid #e5e7eb;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-dot.paid { background-color: #7c3aed; }
        .status-dot.pending { background-color: #a78bfa; }
        .status-dot.overdue { background-color: #e5e7eb; }
        
        .status-label {
            font-size: 14px;
            color: #6b7280;
        }
        
        .status-count {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .invoice-count {
            background-color: #f3f4f6;
            color: #6b7280;
            font-size: 12px;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .filter-info {
            margin-bottom: 20px;
            padding: 16px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .filter-badge {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }
        
        .filter-icon {
            margin-right: 8px;
        }
        
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .filter-chip {
            background-color: #e5e7eb;
            color: #374151;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 16px;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .invoice-table th {
            background-color: #f9fafb;
            color: #6b7280;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .invoice-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        
        .invoice-table tbody tr:hover {
            background-color: #f9fafb;
        }
        
        .invoice-number {
            font-weight: 600;
            color: #7c3aed;
        }
        
        .invoice-date {
            color: #6b7280;
        }
        
        .customer-info {
            max-width: 200px;
        }
        
        .customer-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 2px;
        }
        
        .contact-name {
            font-size: 12px;
            color: #6b7280;
        }
        
        .amount {
            font-weight: 600;
            color: #1f2937;
            text-align: right;
        }
        
        .amount-due {
            text-align: right;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-badge.status-paid { background-color: #d1fae5; color: #065f46; }
        .status-badge.status-sent { background-color: #dbeafe; color: #1e40af; }
        .status-badge.status-draft { background-color: #f3f4f6; color: #374151; }
        .status-badge.status-viewed { background-color: #fef3c7; color: #92400e; }
        .status-badge.status-overdue { background-color: #fee2e2; color: #991b1b; }
        .status-badge.status-due { background-color: #fef3c7; color: #92400e; }
        .status-badge.status-completed { background-color: #d1fae5; color: #065f46; }
        .status-badge.status-unpaid { background-color: #fee2e2; color: #991b1b; }
        .status-badge.status-partially_paid { background-color: #fef3c7; color: #92400e; }
        
        .paid-status {
            font-size: 11px;
            font-weight: 500;
            margin-top: 4px;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .paid-status.status-paid { background-color: #d1fae5; color: #065f46; }
        .paid-status.status-unpaid { background-color: #fee2e2; color: #991b1b; }
        .paid-status.status-partially_paid { background-color: #fef3c7; color: #92400e; }
        
        .table-footer {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        @media print {
            body { 
                background-color: white; 
                font-size: 12px;
            }
            .container { 
                max-width: none; 
                padding: 10px;
            }
            .card { 
                box-shadow: none; 
                border: 1px solid #e5e7eb;
                page-break-inside: avoid;
            }
            .header h1 { font-size: 24px; }
            .card-title { font-size: 16px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 Dashboard Snapshot</h1>
            <div class="company-name">{{ $company->name }}</div>
            <div class="generated-at">Generated on {{ $generatedAt }}</div>
        </div>

        <!-- Main Dashboard Grid -->
        @if(isset($selectedSections) && in_array('dashboard', $selectedSections))
        <div class="dashboard-grid">
            <!-- Summary Card -->
            <div class="card">
                <div class="card-title">
                    Summary
                    <span class="live-badge">Live</span>
                </div>
                <div class="summary-metrics">
                    <div class="metric">
                        <div class="metric-label">Total invoices</div>
                        <div class="metric-value">{{ $dashboardData['stats']['totalInvoiceCount'] ?? 0 }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-label">Sales</div>
                        <div class="metric-value">${{ number_format(($dashboardData['totalSales'] ?? 0) / 100, 2) }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-label">Receipts</div>
                        <div class="metric-value">${{ number_format(($dashboardData['totalReceipts'] ?? 0) / 100, 2) }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-label">Expenses</div>
                        <div class="metric-value">${{ number_format(($dashboardData['totalExpenses'] ?? 0) / 100, 2) }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-label">Total income</div>
                        <div class="metric-value highlight">${{ number_format(($dashboardData['totalNetIncome'] ?? 0) / 100, 2) }}</div>
                    </div>
                </div>
            </div>

            <!-- Outstanding Invoices Chart -->
            <div class="card">
                <div class="card-title">Top 5 Outstanding Invoices</div>
                <div class="chart-container">
                    @if(!empty($chartImages['outstandingInvoices']))
                        <img src="{{ $chartImages['outstandingInvoices'] }}" alt="Outstanding Invoices Chart" class="chart-image">
                    @else
                        <div class="chart-placeholder">Chart not available</div>
                    @endif
                </div>
            </div>

            <!-- Status Distribution Chart -->
            <div class="card">
                <div class="card-title">Status Distribution</div>
                <div class="chart-container">
                    @if(!empty($chartImages['statusDistribution']))
                        <img src="{{ $chartImages['statusDistribution'] }}" alt="Status Distribution Chart" class="chart-image">
                    @else
                        <div class="chart-placeholder">Chart not available</div>
                    @endif
                </div>
                
                <!-- Status Legend -->
                <div class="status-legend">
                    <div class="status-item">
                        <div class="status-indicator">
                            <div class="status-dot paid"></div>
                            <span class="status-label">Paid</span>
                        </div>
                        <div class="status-count">{{ $dashboardData['statusDistribution']['paid'] ?? 0 }}</div>
                    </div>
                    <div class="status-item">
                        <div class="status-indicator">
                            <div class="status-dot pending"></div>
                            <span class="status-label">Pending</span>
                        </div>
                        <div class="status-count">{{ $dashboardData['statusDistribution']['pending'] ?? 0 }}</div>
                    </div>
                    <div class="status-item">
                        <div class="status-indicator">
                            <div class="status-dot overdue"></div>
                            <span class="status-label">Overdue</span>
                        </div>
                        <div class="status-count">{{ $dashboardData['statusDistribution']['overdue'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Predictive Cash Flow Chart - Full Width -->
        @if(isset($selectedSections) && in_array('cashflow', $selectedSections))
        <div class="card full-width-card">
            <div class="card-title">Predictive Cash Flow Analysis</div>
            <div class="chart-container">
                @if(!empty($chartImages['predictiveCashFlow']))
                    <img src="{{ $chartImages['predictiveCashFlow'] }}" alt="Predictive Cash Flow Chart" class="chart-image">
                @else
                    <div class="chart-placeholder">Chart not available</div>
                @endif
            </div>
        </div>
        @endif

        <!-- Recent Invoices Table - Full Width -->
        @if(isset($selectedSections) && in_array('invoices', $selectedSections) && !empty($tableData['invoices']) && count($tableData['invoices']) > 0)
        <div class="card full-width-card">
            <div class="card-title">
                Recent Invoices
                @if(!empty($tableData['totalCount']))
                    <span class="invoice-count">{{ $tableData['totalCount'] }} {{ $tableData['totalCount'] === 1 ? 'invoice' : 'invoices' }}</span>
                @endif
            </div>
            
            <!-- Active Filters Display -->
            @if(!empty($tableData['hasActiveFilters']) && $tableData['hasActiveFilters'])
            <div class="filter-info">
                <div class="filter-badge">
                    <span class="filter-icon">🔍</span>
                    <span>{{ $tableData['activeFilterCount'] ?? 0 }} filter{{ ($tableData['activeFilterCount'] ?? 0) !== 1 ? 's' : '' }} applied</span>
                </div>
                
                <div class="active-filters">
                    @if(!empty($tableData['filters']['status']))
                        <span class="filter-chip">Status: {{ $tableData['filters']['status'] }}</span>
                    @endif
                    @if(!empty($tableData['filters']['search']))
                        <span class="filter-chip">Search: "{{ $tableData['filters']['search'] }}"</span>
                    @endif
                    @if(!empty($tableData['filters']['from_date']) || !empty($tableData['filters']['to_date']))
                        <span class="filter-chip">
                            Date: 
                            @if(!empty($tableData['filters']['from_date']) && !empty($tableData['filters']['to_date']))
                                {{ \Carbon\Carbon::parse($tableData['filters']['from_date'])->format('M j, Y') }} - {{ \Carbon\Carbon::parse($tableData['filters']['to_date'])->format('M j, Y') }}
                            @elseif(!empty($tableData['filters']['from_date']))
                                From {{ \Carbon\Carbon::parse($tableData['filters']['from_date'])->format('M j, Y') }}
                            @else
                                Until {{ \Carbon\Carbon::parse($tableData['filters']['to_date'])->format('M j, Y') }}
                            @endif
                        </span>
                    @endif
                </div>
            </div>
            @endif

            <!-- Table -->
            <div class="table-container">
                <table class="invoice-table">
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
                        @foreach($tableData['invoices'] as $invoice)
                        <tr>
                            <td class="invoice-number">{{ $invoice['invoice_number'] ?? 'N/A' }}</td>
                            <td>
                                <span class="status-badge status-{{ strtolower($invoice['status'] ?? 'unknown') }}">
                                    {{ $invoice['status'] ?? 'Unknown' }}
                                </span>
                            </td>
                            <td class="invoice-date">
                                @if(!empty($invoice['invoice_date']))
                                    {{ \Carbon\Carbon::parse($invoice['invoice_date'])->format('M j, Y') }}
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="customer-info">
                                <div class="customer-name">{{ $invoice['customer']['name'] ?? 'Unknown Customer' }}</div>
                                @if(!empty($invoice['customer']['contact_name']))
                                    <div class="contact-name">{{ $invoice['customer']['contact_name'] }}</div>
                                @endif
                            </td>
                            <td class="amount">${{ number_format(($invoice['total'] ?? 0) / 100, 2) }}</td>
                            <td class="amount-due">
                                <div class="amount">${{ number_format(($invoice['due_amount'] ?? 0) / 100, 2) }}</div>
                                @if(!empty($invoice['paid_status']))
                                    <div class="paid-status status-{{ strtolower($invoice['paid_status']) }}">
                                        {{ ucfirst(str_replace('_', ' ', $invoice['paid_status'])) }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            @if(!empty($tableData['filteredCount']) && !empty($tableData['totalCount']) && $tableData['filteredCount'] !== $tableData['totalCount'])
            <div class="table-footer">
                Showing {{ $tableData['filteredCount'] }} of {{ $tableData['totalCount'] }} invoices
            </div>
            @endif
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>This dashboard snapshot was generated automatically and reflects the current state of your data.</p>
            <p>For the most up-to-date information, please visit your live dashboard.</p>
        </div>
    </div>
</body>
</html> 