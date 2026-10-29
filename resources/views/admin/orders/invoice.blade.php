<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>

    <style>
        @page {
            margin: 30px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #222;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header {
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
        }

        .header td {
            padding: 8px 0 14px;
            vertical-align: top;
        }

        h1 {
            margin: 0;
            font-size: 25px;
        }

        h2 {
            margin: 18px 0 8px;
            font-size: 14px;
        }

        .right {
            text-align: right;
        }

        .muted {
            color: #666;
        }

        th {
            background: #f2f2f2;
            text-align: left;
        }

        th,
        .items td {
            padding: 8px 6px;
            border-bottom: 1px solid #ddd;
        }

        .numeric {
            text-align: right;
            white-space: nowrap;
        }

        .totals {
            width: 52%;
            margin-left: auto;
            margin-top: 16px;
        }

        .totals td {
            padding: 5px 6px;
        }

        .grand-total td {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 13px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
        }

        .address {
            vertical-align: top;
            width: 50%;
            padding-right: 12px;
        }
    </style>
</head>

<body>

    {{-- Invoice header --}}
    <table class="header">
        <tr>
            <td>
                <h1>{{ config('app.name', 'Swaad Taste Of Punjab') }}</h1>
                <div class="muted">INVOICE</div>
            </td>

            <td class="right">
                <strong>Invoice / Order No:</strong>
                {{ $order->order_number }}
                <br>

                <strong>Order Date:</strong>
                {{ $order->created_at ? $order->created_at->format('d M Y, h:i A') : '—' }}
                <br>

                <strong>Payment Status:</strong>
                {{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'pending')) }}
            </td>
        </tr>
    </table>

    {{-- Customer and payment details --}}
    <table>
        <tr>
            <td class="address">
                <h2>Bill / Ship To</h2>

                <strong>
                    {{ $order->customer_name ?: $order->recipient_name ?? 'Customer' }}
                </strong>
                <br>

                @if (!empty($order->customer_email))
                    {{ $order->customer_email }}
                    <br>
                @elseif ($order->user && !empty($order->user->email))
                    {{ $order->user->email }}
                    <br>
                @endif

                @if (!empty($order->recipient_phone))
                    {{ $order->recipient_phone }}
                    <br>
                @endif

                @if (!empty($order->delivery_address_line_1))
                    {{ $order->delivery_address_line_1 }}
                    <br>
                @endif

                @if (!empty($order->delivery_address_line_2))
                    {{ $order->delivery_address_line_2 }}
                    <br>
                @endif

                @if (!empty($order->landmark))
                    {{ $order->landmark }}
                    <br>
                @endif

                @if (!empty($order->city))
                    {{ $order->city }}
                @endif

                @if (!empty($order->state))
                    , {{ $order->state }}
                @endif

                @if (!empty($order->postal_code))
                    {{ $order->postal_code }}
                @endif

                @if (!empty($order->country))
                    <br>{{ $order->country }}
                @endif
            </td>


        </tr>
    </table>

    {{-- Order items --}}
    <h2>Order Items</h2>

    <table class="items">
        <thead>
            <tr>
                <th>Product</th>
                <th class="numeric">Qty</th>
                <th class="numeric">Unit Price</th>
                <th class="numeric">Line Total</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($order->items as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}

                        @if (!empty($item->variant_name))
                            — {{ $item->variant_name }}
                        @endif
                    </td>

                    <td class="numeric">
                        {{ $item->quantity }}
                    </td>

                    <td class="numeric">
                        {{ $order->currency ?? 'INR' }}
                        {{ number_format((float) $item->unit_price, 2) }}
                    </td>

                    <td class="numeric">
                        {{ $order->currency ?? 'INR' }}
                        {{ number_format((float) $item->line_total, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No order items found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Stored order totals: do not recalculate historical totals --}}
    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="numeric">
                {{ $order->currency ?? 'INR' }}
                {{ number_format((float) $order->subtotal, 2) }}
            </td>
        </tr>

        <tr>
            <td>Shipping</td>
            <td class="numeric">
                {{ $order->currency ?? 'INR' }}
                {{ number_format((float) $order->shipping_fee, 2) }}
            </td>
        </tr>

        <tr>
            <td>GST</td>
            <td class="numeric">
                5%
            </td>
        </tr>

        @if ((float) $order->discount_amount > 0)
            <tr>
                <td>
                    @if (!empty($order->coupon_code))
                        Discount ({{ $order->coupon_code }})
                    @else
                        Discount
                    @endif
                </td>

                <td class="numeric">
                    − {{ $order->currency ?? 'INR' }}
                    {{ number_format((float) $order->discount_amount, 2) }}
                </td>
            </tr>
        @endif

        <tr class="grand-total">
            <td>Total Payable</td>
            <td class="numeric">
                {{ $order->currency ?? 'INR' }}
                {{ number_format((float) $order->total_amount, 2) }}
            </td>
        </tr>
    </table>

    <div class="footer">
        Thank you for shopping with {{ config('app.name', 'SWAAD') }}.
        <br>
        This invoice was generated electronically.
    </div>

</body>

</html>
