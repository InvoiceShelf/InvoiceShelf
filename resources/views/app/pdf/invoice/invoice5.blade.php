<!DOCTYPE html>
<html>

<head>
    <title>Invoice</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

    <style type="text/css">
        /* -- Base -- */
        body {
            font-family: "DejaVu Sans";
        }

        html {
            margin: 0px;
            padding: 0px;
        }
        table {
            border-collapse: collapse;
            font-size:16px;
        }

        hr {
            color:rgba(0, 0, 0, 0.2);
            left: 0px;
            right: -70px;
            width: 100%;
        }

        .header-left {
            padding-bottom: 45px;
            padding-left: 30px;
            display:inline-block;
            width:50%;
        }
        .header-table {
            width: 100%;
        }
        .text-center { text-align:center; }
        .table-border th,.table-border td { border:1px solid #000; padding:4px; font-size:12px;}
        .header-logo {
            height: 80px;
            text-transform: capitalize;
            color: #817AE3;
        }

        .header-logo p {
            height:40px;
        }
        .header-right {
            display:inline-block;
            right:0;
            padding: 0px 30px 0px 0px;
            float: right;
            width:300px;
        }
        .inv-flex{
            display:flex;
        }
        .inv-data{
            text-align:right;
            margin-right:120px;
        }
        .inv-value{
            text-align:left;
            margin-left:160px;
        }
        .header {
            font-size: 20px;
            color: rgba(0, 0, 0, 0.7);
        }

        .TextColor1 {
            font-size: 16px;
            color: rgba(0, 0, 0, 0.5);
        }

        @page {
            margin-top: 60px !important;
        }
        .wrapper {
            display: block;
            padding-top: 20px;
            padding-bottom: 20px;
        }

        .address {
            display: inline-block;
            padding-top: 20px
        }

        .bill-add {
            display: block;
            float:left;
            width:40%;
            padding: 0 0 0 30px;
        }
        .company {
            padding-left: 30px;
            display: inline;
            float:left;
            width:30%;
        }

        .company h1 {
            font-style: normal;
            font-size: 18px;
            line-height: 22px;
            letter-spacing: 0.05em;
        }

        .company-add {
            text-align: right;
            font-style: normal;
            font-size: 14px;
            line-height: 15px;
            color: #595959;
            margin: 0px;
        }

        /* -------------------------- */
        /* shipping style */
        .ship-to {
            padding-top: 5px;
            font-style: normal;
            font-size: 12px;
            line-height: 18px;
            margin-bottom: 0px;
        }

        .ship-user-name {
            padding: 0px;
            font-style: normal;

            font-size: 15px;
            line-height: 22px;
            margin: 0px;
        }

        .ship-user-address {
            font-style: normal;
            font-size: 10px;
            line-height: 15px;
            color: #595959;
            margin: 0px;
            width: 160px;
        }

        .ship-user-phone {
            font-style: normal;
            font-size: 10px;
            line-height: 15px;
            color: #595959;
            margin: 0px;
        }

        /* -------------------------- */
        /* billing style */
        .bill-to {
            padding-top: 5px;
            font-style: normal;
            font-size: 12px;
            line-height: 18px;
            margin-bottom: 0px;
        }

        .bill-user-name {
            padding: 0px;
            font-style: normal;
            font-size: 15px;
            line-height: 22px;
            margin: 0px;
        }

        .bill-user-address {
            font-style: normal;
            font-size: 10px;
            line-height: 15px;
            color: #595959;
            margin:0px;
            width: 160px;
        }

        .bill-user-phone {
            font-style: normal;
            font-size: 10px;
            line-height: 15px;
            color: #595959;
            margin: 0px;
        }


        .job-add {
            display: inline;
            float: right;
            padding: 20px 30px 0 0;
        }
        .amount-due {
            background-color: #f2f2f2;
        }

        .textRight {
            text-align: right;
        }

        .textLeft {
            text-align: left;
        }

        .textStyle1 {
            font-style: normal;
            font-size: 12px;
            line-height: 18px;
        }

        .textStyle2 {
            font-style: normal;
            font-size: 12px;
            line-height: 18px;
            text-align: right;
        }
        .main-table-header td {
            padding: 10px;
        }
        .main-table-header {
            border-bottom: 1px solid red;
        }
        tr.main-table-header th {
            font-style: normal;
            font-size: 12px;
            line-height: 18px;
        }
        tr.item-details td {
            font-style: normal;
            font-size: 12px;
            line-height: 18px;
        }
        .table2 {
            border-bottom: 1px solid #EAF1FB;
            padding: 0 30px 0 30px;
            page-break-before: avoid;
            page-break-after: auto;
        }

        .table2 hr {
            height:0.1px;
        }

        .ItemTableHeader {
            font-size: 13.5px;
            text-align: center;
            color: rgba(0, 0, 0, 0.85);
            padding: 5px;
        }

        .items {
            font-size: 13px;
            color: rgba(0, 0, 0, 0.6);
            text-align: center;
            padding: 5px;
        }

        .note-header {
            font-size: 13px;
            color: rgba(0, 0, 0, 0.6);
        }

        .note-text {
            font-size: 10px;
            color: rgba(0, 0, 0, 0.6);
        }

        .padd8 {
            padding-top: 8px;
            padding-bottom: 8px;
        }

        .padd2 {
            padding-top: 2px;
            padding-bottom: 2px;
        }

        .table3 {
            border: 1px solid #EAF1FB;
            border-top: none;
            box-sizing: border-box;
            width: 630px;
            page-break-inside: avoid;
            page-break-before: auto;
            page-break-after: auto;
        }

        .text-per-item-table3 {
            border: 1px solid #EAF1FB;
            border-top: none;
            padding-right: 30px;
            box-sizing: border-box;
            width: 260px;
            /* height: 100px; */
            position: relative;
            right: -25px;
        }

        .total-display-container {
            padding: 0 25px;
        }

        .total-display-table {
            border-top: none;
            box-sizing: border-box;
            page-break-inside: avoid;
            page-break-before: auto;
            page-break-after: auto;
            margin-left: 500px;
            margin-top: 20px;
        }

        .total-table-attribute-label {
            font-size: 13px;
            color: #55547A;
            text-align: left;
            padding-left: 10px;
        }

        .total-table-attribute-value {
            font-weight: bold;
            text-align: right;
            font-size: 13px;
            color: #040405;
            padding-right: 10px;
            padding-top: 2px;
            padding-bottom: 2px;
        }

        .total-border-left {
            border: 1px solid #E8E8E8 !important;
            border-right: 0px !important;
            padding-top: 0px;
            padding: 8px !important;
        }

        .total-border-right {
            border: 1px solid #E8E8E8 !important;
            border-left: 0px !important;
            padding-top: 0px;
            padding: 8px !important;
        }

        td.invoice-total1 {
            text-align:left;
            padding: 15px 0 15px 10px;
            font-size:12px;
            line-height: 18px;
            color: #55547A;
            border-bottom:1px solid #E8E8E8;
            border-top:1px solid #E8E8E8;
            border-left:1px solid #E8E8E8;
        }

        td.invoice-total2 {
            text-align: right;
            font-size:12px;
            line-height: 18px;
            padding: 15px 10px 15px 0;
            color: #5851DB;
            border-bottom:1px solid #E8E8E8;
            border-top:1px solid #E8E8E8;
            border-right:1px solid #E8E8E8;
        }

        .inv-item {
            border-color: #d9d9d9;
        }

        .no-border {
            border: none;
        }
        .desc {
            text-align: justify;
            font-size: 10px;
            margin-bottom: 15px;
            margin-top:7px;
            color:rgba(0, 0, 0, 0.85);
        }
        .company-details h1 {
            margin:0;
            font-style: normal;
            font-size: 15px;
            line-height: 22px;
            letter-spacing: 0.05em;
            text-align: right;
            max-width: 460px;
        }
        .company-details h4 {
            margin:0;
            font-style: normal;
            font-size: 18px;
            line-height: 25px;
            text-align: right;
        }
        .company-details h3 {
            margin-bottom:1px;
            margin-top:0;
        }
        tr.total td {
            border-bottom:1px solid #E8E8E8;
            border-top:1px solid #E8E8E8;
        }

        .notes {
            font-style: normal;
            font-size: 12px;
            color: #595959;
            margin-top: 15px;
            margin-left: 30px;
            text-align: left;
            page-break-inside: avoid;
        }

        .notes-label {
            font-style: normal;
            font-size: 15px;
            line-height: 22px;
            letter-spacing: 0.05em;
            color: #040405;
            width: 108px;
            height: 19.87px;
            padding-bottom: 10px;
        }

    </style>
</head>
<body>
<div class="header-table" style="height: 180px; padding-top:-50px;">
    <table width="100%">
        <tr>
            <td class="header-left">
                <img class="header-logo" src="{{ $logo }}" alt="Company Logo">
                <p>
                    <a href="https://www.esolutions.gr" style="color:#3f73c0; text-decoration: none;">https://www.esolutions.gr</a><br>
                    <a href="mailto:info@esolutions.gr" style="color:#3f73c0; text-decoration: none;">info@esolutions.gr</a><br>
                </p>
            </td>
            <td class="header-right company-details">
                <h1><span style="color:#ec1d78;">e</span><span style="color:#3f73c0">Solutions</span>&nbsp;<span style="color:#ec1d78;">Softhouse</span>&nbsp;Limited </h1>
                <p class="company-add">Reg Number: ΗΕ 379350</p>
                <p class="company-add">VAT: 10379350Ζ</p>
                <p class="company-add">Samou 5, Tremithousa 8270</p>
                <p class="company-add">CY-8270, Paphos, Cyprus</p>
                <p class="company-add">+357.220-30234</p>
            </td>
        </tr>
    </table>
</div>
<hr style="border: 0.620315px solid #E8E8E8;">
<div class="header-table table-border">
    <table width="100%" class="text-center" style="padding-left:30px; padding-right:30px;">
        <thead>
        <tr>
            <th bgcolor="#9BC2E6" style="font-size:12px;">Doc Type</th>
            <th bgcolor="#9BC2E6" style="font-size:12px;">Ref</th>
            <th bgcolor="#9BC2E6" style="font-size:12px;">Number</th>
            <th bgcolor="#9BC2E6" style="font-size:12px;">Date</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td class="text-center" style="font-size:12px;">Invoice</td>
            <td class="text-center" style="font-size:12px;"></td>
            <td class="text-center" style="font-size:12px;">{!! str_replace('AP-','',$invoice->invoice_number) !!}</td>
            <td class="text-center" style="font-size:12px;">{{$invoice->formattedInvoiceDate}}</td>
        </tr>
        </tbody>
    </table>
</div>
<div class="header-table table-border">
    <table width="100%" style="padding-left:30px; padding-right:30px;">
        <thead>
        <tr>
            <th colspan="2" bgcolor="#9BC2E6">Bill To</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <th>Name</th>
            <td>{{$invoice->customer->billingAddress->name}}</td>
        </tr>
        <tr>
            <th>Address</th>
            <td>
                {!! nl2br(htmlspecialchars($invoice->customer->billingAddress->address_street_1)) !!}
                {!! nl2br(htmlspecialchars($invoice->customer->billingAddress->address_street_2)) !!}
                {{$invoice->customer->billingAddress->zip}}
            </td>
        </tr>
        @if($invoice->customer->billingAddress->country && $invoice->customer->billingAddress->country->name)
        <tr>
            <th>Country</th>
            <td>{{$invoice->customer->billingAddress->country->name}}</td>
        </tr>
        @endif
        <tr>
            <th>State</th>
            <td>{!! nl2br(htmlspecialchars($invoice->customer->billingAddress->state)) !!}</td>
        </tr>
        <tr>
            <th>VAT / GST </th>
            <td>{!! nl2br(htmlspecialchars($invoice->customer->fields[1]->string_answer)) !!}</td>
        </tr>
        @if($invoice->customer->billingAddress->phone)
            <tr>
                <th>Phone</th>
                <td>{{$invoice->customer->billingAddress->phone}}</td>
            </tr>
        @endif
        </tbody>
    </table>
</div>
<hr style="border: 0.620315px solid #E8E8E8;">
<div class="wrapper">
    <table width="100%" class="table2 table-border" cellspacing="0" border="0">
        <tr class="main-table-header" bgcolor="#9BC2E6">
            <th width="2%" class="ItemTableHeader" style="text-align: center; color: #55547A; padding-right: 10px">#</th>
            <th width="50%" class="ItemTableHeader" style="text-align: left; color: #55547A; padding-left: 4px">Items</th>
            <th class="ItemTableHeader" style="text-align: right; color: #55547A; padding-right: 20px">Quantity</th>
            <th class="ItemTableHeader" style="text-align: right; color: #55547A; padding-right: 20px">Price</th>
            @if($invoice->discount_per_item === 'YES')
                <th class="ItemTableHeader" style="text-align: right; color: #55547A; padding-left: 10px">Discount</th>
            @endif
            <th class="ItemTableHeader" style="text-align: right; color: #55547A;">Total</th>
        </tr>
        @php
            $index = 1
        @endphp
        @foreach ($invoice->items as $item)
            <tr class="item-details">
                <td class="inv-item items" style="text-align: center; color: #040405; padding-right: 10px; vertical-align: top;">
                    {{$index}}
                </td>
                <td class="inv-item items" style="text-align: left; color: #040405;padding-left: 4px">
                    <span>{{ $item->name }}</span><br>
                    <span style="text-align: left; color: #595959; font-size: 9px; line-height: 12px;">{!! nl2br(htmlspecialchars($item->description)) !!}</span>
                </td>
                <td class="inv-item items" style="text-align: right; color: #040405; padding-right: 10px">
                    {{$item->quantity}}
                </td>
                <td class="inv-item items" style="text-align: right; color: #040405; padding-right: 20px">
                    {!! format_money_pdf($item->price, $invoice->customer->currency) !!}
                </td>
                @if($invoice->discount_per_item === 'YES')
                    <td class="inv-item items" style="text-align: right; color: #040405; padding-left: 10px">
                        @if($item->discount_type === 'fixed')
                            {!! format_money_pdf($item->discount_val, $invoice->customer->currency) !!}
                        @endif
                        @if($item->discount_type === 'percentage')
                            {{$item->discount}}%
                        @endif
                    </td>
                @endif
            	<td class="inv-item items" style="text-align: right; color: #040405;">
                    {!! format_money_pdf($item->total, $invoice->customer->currency) !!}
                </td>
            </tr>
            @php
                $index += 1
            @endphp
        @endforeach
    </table>

    <hr class="items-table-hr"/>
</div>

<div class="total-display-container">
    <table width="333px" cellspacing="0px" style="margin-left:400px; margin-top: 10px" border="0" class="total-display-table @if(count($invoice->items) > 12) page-break @endif">
        <tr>
            <td class="no-border" style="color: #55547A; padding-left:10px;  font-size:12px;">Subtotal</td>
            <td class="no-border items padd2" style="padding-right:10px; text-align: right;  font-size:12px; color: #040405;">{!! format_money_pdf($invoice->sub_total, $invoice->customer->currency) !!}</td>
        </tr>

        @foreach ($invoice->taxes as $tax)
            <tr>
                <td class="no-border" style="padding-left:10px; text-align:left; font-size:12px;  color: #55547A;">
                    {{$tax->name.' ('.$tax->percent.'%)'}}
                </td>
                <td class="no-border items padd2" style="padding-right:10px;  text-align: right; font-size:12px;  color: #040405">
                    {!! format_money_pdf($tax->amount, $invoice->customer->currency) !!}
                </td>
            </tr>
        @endforeach

        @if ($invoice->discount_per_item === 'NO')
            <tr>
                <td class="no-border" style="padding-left:10px; text-align:left; font-size:12px; color: #55547A;">
                    @if($invoice->discount_type === 'fixed')
                        Discount
                    @endif
                    @if($invoice->discount_type === 'percentage')
                        Discount ({{$invoice->discount}}%)
                    @endif
                </td>
                <td class="no-border items padd2" style="padding-right:10px; text-align: right; font-size:12px;  color: #040405">
                    @if($invoice->discount_type === 'fixed')
                        {!! format_money_pdf($invoice->discount_val, $invoice->customer->currency) !!}
                    @endif
                    @if($invoice->discount_type === 'percentage')
                        {!! format_money_pdf($invoice->discount_val, $invoice->customer->currency) !!}
                    @endif
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding:3px 0px"></td>
            <td style="padding:3px 0px"></td>
        </tr>
		@if(isset($invoice->fields[0]->string_answer))
        <tr>
            <td class="no-border total-border-left" style="padding-left:10px; padding-bottom:10px; text-align:left; padding-top:10px; font-size:12px;  color: #55547A;">
                <label class="total-bottom">Total (USD)</label>
            </td>
            <td class="no-border total-border-right items padd8" style="padding-right:10px; text-align: right; font-size:12px;  padding-top:10px; color: #5851DB;">
                ${{ number_format($invoice->fields[0]->string_answer,2) }}
            </td>
        </tr>
        @endif

        <tr>
            <td class="no-border total-border-left" style="padding-left:10px; padding-bottom:10px; text-align:left; padding-top:10px; font-size:12px;  color: #55547A;">
                <label class="total-bottom">Total</label>
            </td>
            <td class="no-border total-border-right items padd8" style="padding-right:10px; text-align: right; font-size:12px;  padding-top:10px; color: #5851DB;">
                {!! format_money_pdf($invoice->total, $invoice->customer->currency)!!}
            </td>
        </tr>
    </table>
</div>

@if ($invoice->paid_status != 'PAID')
    <hr style="border: 0.620315px solid #E8E8E8;">
    <div class="header-table table-border margin:0 auto;">
        <table width="600" cellspacing="0px" style="margin-left:100px; margin-top: 10px" border="0" class="table3">
            <tbody>
            <tr>
                <th  bgcolor='9BC2E6' colspan='2'>BANK PAYMENT</th>
            </tr>
            <tr>
                <th>IBAN</th>
                <td>BE27 9673 4438 0173</td>
            </tr>
            <tr>
                <th>SHIFT/BIC</th>
                <td>TRWIBEB1XXX</td>
            </tr>
            <tr>
                <th>ACCOUNT HOLDER</th>
                <td>eSolutions Softhouse Limited</td>
            </tr>
            <tr>
                <th>REASON</th>
                <td>{!! str_replace('-','',$invoice->invoice_number) !!}</td>
            </tr>
            <tr>
                <th colspan="2" bgcolor="#9BC2E6">PAY ONLINE USING PAYPAL</th>
            </tr>
            <tr>
                <th>PayPal</th>
                <td>info@esolutions.gr</td>
            </tr>
            <tr>
                <th>PAY Online</th>
                <td><a href="https://paypal.me/eSolutionsSofthouse/{{ number_format($invoice->total / 100,2) }}" target="_blank">Click here</a></td>
            </tr>
            </tbody>
        </table>
    </div>
    @endif





    </div>
</body>
</html>
