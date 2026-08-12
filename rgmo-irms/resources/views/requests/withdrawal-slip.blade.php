<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Withdrawal Slip {{ $request->ris_no ?: 'RQ-'.$request->id }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 13mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e9ecef;
            color: #000;
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.15;
        }

        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background: #173d2b;
            font-family: Arial, sans-serif;
        }

        .print-toolbar a,
        .print-toolbar button {
            border: 1px solid rgba(255, 255, 255, .72);
            border-radius: 5px;
            padding: 9px 16px;
            background: #fff;
            color: #173d2b;
            cursor: pointer;
            font: 600 14px/1 Arial, sans-serif;
            text-decoration: none;
        }

        .slip {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 12mm 13mm;
            background: #fff;
            box-shadow: 0 3px 18px rgba(0, 0, 0, .18);
        }

        .office-header {
            margin-bottom: 24px;
            text-align: center;
        }

        .office-header p,
        .office-header h1 {
            margin: 0;
        }

        .office-header .university,
        .office-header .office,
        .office-header h1 {
            font-weight: 700;
        }

        .office-header .university {
            font-size: 13pt;
        }

        .office-header .office {
            margin-top: 2px;
            font-size: 12.5pt;
        }

        .office-header h1 {
            margin-top: 25px;
            font-size: 13pt;
        }

        .slip-meta {
            margin-bottom: 3px;
        }

        .meta-row {
            display: table;
            width: 100%;
            margin-bottom: 3px;
        }

        .meta-row > div {
            display: table-cell;
        }

        .meta-row > div:last-child {
            text-align: right;
        }

        .meta-value {
            font-weight: 700;
        }

        .field-line {
            display: inline-block;
            min-width: 145px;
            border-bottom: 1px solid #000;
            line-height: 1;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: middle;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .items-table th {
            height: 32px;
            padding: 2px 4px;
            font-size: 9pt;
            line-height: 1;
            text-align: center;
        }

        .items-table td {
            min-height: 26px;
            font-family: Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.12;
        }

        .items-table tbody tr {
            height: 27px;
        }

        .items-table .number,
        .items-table .money {
            text-align: right;
        }

        .items-table .center {
            text-align: center;
        }

        .items-table .total-row th,
        .items-table .total-row td {
            height: 27px;
            font-weight: 700;
        }

        .purpose {
            border: 1px solid #000;
            border-top: 0;
            padding: 4px 7px;
        }

        .signatures {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12mm 27px;
            margin-top: 10px;
        }

        .signature-block {
            width: 50%;
            min-height: 82px;
            vertical-align: top;
        }

        .signature-space {
            min-height: 31px;
        }

        .signature-name,
        .signature-title {
            display: block;
            width: 100%;
            margin: 0;
            padding: 1px 3px;
            border: 1px dashed transparent;
            border-radius: 2px;
            background: transparent;
            color: #000;
            font-family: "Times New Roman", Times, serif;
            text-align: center;
        }

        .signature-name {
            text-transform: uppercase;
        }

        .signature-title {
            font-size: 11pt;
        }

        input.signature-name,
        input.signature-title {
            border-color: #9ca3af;
        }

        input.signature-name:focus,
        input.signature-title:focus {
            border-color: #173d2b;
            outline: 2px solid rgba(23, 61, 43, .18);
        }

        @media print {
            body {
                background: #fff;
            }

            .print-toolbar {
                display: none !important;
            }

            .slip {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .items-table thead {
                display: table-header-group;
            }

            .items-table tr,
            .signatures,
            .signature-block {
                break-inside: avoid;
            }

            input.signature-name,
            input.signature-title {
                border-color: transparent;
                outline: 0;
            }
        }

        .pdf-output {
            background: #fff;
        }

        .pdf-output .slip {
            width: auto;
            min-height: 0;
            margin: 0;
            padding: 0;
            box-shadow: none;
        }

        @media screen and (max-width: 850px) {
            .slip {
                margin: 0;
                transform-origin: top left;
            }
        }
    </style>
</head>
<body class="{{ ($isPdf ?? false) ? 'pdf-output' : '' }}">
    @php
        $slipNumber = $request->ris_no ?: 'RQ-'.str_pad((string) $request->id, 4, '0', STR_PAD_LEFT);
        $slipDate = $request->fulfilled_at ?? $request->approved_at ?? $request->requested_date ?? $request->created_at;
        $department = $request->responsible_center ?: ($request->project?->name ?? $request->user?->department ?? '');
        $minimumRows = max(0, 7 - $request->items->count());
        $totalAmount = $request->items->sum(fn ($line) => $line->quantity * (float) ($line->item?->price ?? 0));
    @endphp

    @unless($isPdf ?? false)
        <form method="GET" action="{{ route('requests.withdrawal-slip.download', $request) }}">
    @endunless

    @unless($isPdf ?? false)
        <div class="print-toolbar" aria-label="Withdrawal slip actions">
            <button type="button" onclick="window.print()">Print Withdrawal Slip</button>
            @if(in_array($request->status, ['approved', 'completed'], true))
                <button type="submit">Download PDF</button>
            @endif
            <a href="{{ route('requests.show', $request) }}">Back to Request</a>
        </div>
    @endunless

    <main class="slip">
        <header class="office-header">
            <p>Republic of the Philippines</p>
            <p class="university">CENTRAL MINDANAO UNIVERSITY</p>
            <p>University Town, Musuan, Bukidnon</p>
            <p class="office">OFFICE OF RESOURCE GENERATION AND MANAGEMENT</p>
            <h1>WITHDRAWAL SLIP</h1>
        </header>

        <section class="slip-meta" aria-label="Withdrawal slip details">
            <div class="meta-row">
                <div><strong>No.</strong> <span class="meta-value">{{ $slipNumber }}</span></div>
                <div><strong>Date:</strong> <span class="meta-value">{{ $slipDate?->format('F j, Y') }}</span></div>
            </div>
            <div><strong>Department/College/Project/Unit:</strong> <span class="meta-value">{{ $department }}</span></div>
            <div>
                <strong>P.O. Number:</strong> <span class="field-line">&nbsp;</span>
                <strong>P. R. No.:</strong> <span class="field-line">&nbsp;</span>
            </div>
        </section>

        <table class="items-table">
            <colgroup>
                <col width="7%">
                <col width="7%">
                <col width="10%">
                <col width="43%">
                <col width="10%">
                <col width="12%">
                <col width="11%">
            </colgroup>
            <thead>
                <tr>
                    <th width="7%">ITEM<br>NO.</th>
                    <th width="7%">QTY</th>
                    <th width="10%">UNIT</th>
                    <th width="43%">PARTICULAR</th>
                    <th width="10%">UNIT<br>PRICE</th>
                    <th width="12%">TOTAL<br>AMOUNT</th>
                    <th width="11%">REMARKS</th>
                </tr>
            </thead>
            <tbody>
                @foreach($request->items as $index => $line)
                    @php
                        $unitPrice = (float) ($line->item?->price ?? 0);
                    @endphp
                    <tr>
                        <td class="center">{{ $index + 1 }}</td>
                        <td class="center">{{ number_format($line->quantity) }}</td>
                        <td>{{ $line->item?->unit ?? '' }}</td>
                        <td>{{ $line->item?->name ?? 'Unavailable inventory item' }}</td>
                        <td class="money">{{ number_format($unitPrice, 2) }}</td>
                        <td class="money">{{ number_format($line->quantity * $unitPrice, 2) }}</td>
                        <td></td>
                    </tr>
                @endforeach
                @for($row = 0; $row < $minimumRows; $row++)
                    <tr aria-hidden="true">
                        <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td>
                    </tr>
                @endfor
                <tr class="total-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <th scope="row">TOTAL</th>
                    <td></td>
                    <td class="money">{{ number_format($totalAmount, 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div class="purpose"><strong>Purposes:</strong> {{ $request->purpose }}</div>

        <table class="signatures" aria-label="Withdrawal slip signatures">
            <tr>
                <td class="signature-block">
                    <div>Requested by:</div>
                    <div class="signature-space"></div>
                    @if($isPdf ?? false)
                        <p class="signature-name">{{ $signatureValues['requested_by'] }}</p>
                        <p class="signature-title">{{ $signatureValues['requested_by_title'] }}</p>
                    @else
                        <input type="text" class="signature-name" name="requested_by" value="{{ $signatureValues['requested_by'] }}" aria-label="Requested by name">
                        <input type="text" class="signature-title" name="requested_by_title" value="{{ $signatureValues['requested_by_title'] }}" aria-label="Requested by title">
                    @endif
                </td>
                <td class="signature-block">
                    <div>Issued by:</div>
                    <div class="signature-space"></div>
                    @if($isPdf ?? false)
                        <p class="signature-name">{{ $signatureValues['issued_by'] ?: ' ' }}</p>
                        <p class="signature-title">{{ $signatureValues['issued_by_title'] ?: ' ' }}</p>
                    @else
                        <input type="text" class="signature-name" name="issued_by" value="{{ $signatureValues['issued_by'] }}" placeholder="Enter name" aria-label="Issued by name">
                        <input type="text" class="signature-title" name="issued_by_title" value="{{ $signatureValues['issued_by_title'] }}" aria-label="Issued by title">
                    @endif
                </td>
            </tr>
            <tr>
                <td class="signature-block">
                    <div>Noted by:</div>
                    <div class="signature-space"></div>
                    @if($isPdf ?? false)
                        <p class="signature-name">{{ $signatureValues['noted_by'] ?: ' ' }}</p>
                        <p class="signature-title">{{ $signatureValues['noted_by_title'] ?: ' ' }}</p>
                    @else
                        <input type="text" class="signature-name" name="noted_by" value="{{ $signatureValues['noted_by'] }}" placeholder="Enter name" aria-label="Noted by name">
                        <input type="text" class="signature-title" name="noted_by_title" value="{{ $signatureValues['noted_by_title'] }}" placeholder="Enter title" aria-label="Noted by title">
                    @endif
                </td>
                <td class="signature-block">
                    <div>Received by:</div>
                    <div class="signature-space"></div>
                    @if($isPdf ?? false)
                        <p class="signature-name">{{ $signatureValues['received_by'] ?: ' ' }}</p>
                        <p class="signature-title">{{ $signatureValues['received_by_title'] ?: ' ' }}</p>
                    @else
                        <input type="text" class="signature-name" name="received_by" value="{{ $signatureValues['received_by'] }}" placeholder="Enter name" aria-label="Received by name">
                        <input type="text" class="signature-title" name="received_by_title" value="{{ $signatureValues['received_by_title'] }}" placeholder="Enter title" aria-label="Received by title">
                    @endif
                </td>
            </tr>
        </table>
    </main>
    @unless($isPdf ?? false)
        </form>
    @endunless
</body>
</html>
