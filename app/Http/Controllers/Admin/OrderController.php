<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderStatusEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', ['total' => Order::count(), 'today' => Order::whereDate('created_at', today())->count(), 'pendingPayments' => Order::where('payment_status', 'pending')->count(), 'paid' => Order::where('payment_status', 'paid')->count(), 'awaitingShipment' => Order::where('payment_status', 'paid')->whereIn('order_status', ['confirmed', 'processing', 'packed'])->count(), 'delivered' => Order::where('order_status', 'delivered')->count()]);
    }
    public function index(Request $r)
    {
        $q = Order::query();
        if ($s = $r->query('search')) $q->where(function ($w) use ($s) {
            $w->where('order_number', 'like', "%$s%")->orWhere('recipient_name', 'like', "%$s%")->orWhere('recipient_phone', 'like', "%$s%")->orWhere('shipping_id', 'like', "%$s%");
        });
        foreach (['payment_status', 'order_status'] as $f) if ($r->filled($f)) $q->where($f, $r->query($f));
        if ($r->filled('from')) $q->whereDate('created_at', '>=', $r->query('from'));
        if ($r->filled('to')) $q->whereDate('created_at', '<=', $r->query('to'));
        return view('admin.orders.index', ['orders' => $q->latest()->paginate(25)->withQueryString()]);
    }
    public function show(Order $order)
    {
        $order->load(['items', 'payments', 'user']);
        return view('admin.orders.show', compact('order'));
    }
    public function downloadInvoice(Order $order)
    {
        $order->load(['items', 'payments', 'user']);
        $payment = $order->payments->sortByDesc('created_at')->first();
        $pdf = Pdf::loadView('admin.orders.invoice', compact('order', 'payment'))->setPaper('a4');

        return $pdf->download('invoice-'.$order->order_number.'.pdf');
    }

    public function update(Request $r, Order $order)
    {
        $d = $r->validate(['shipping_provider' => 'nullable|string|max:100', 'shipping_id' => 'nullable|string|max:150', 'tracking_url' => 'nullable|url|max:2000', 'order_status' => 'required|in:confirmed,processing,packed,shipped,out_for_delivery,delivered,cancelled,returned', 'internal_notes' => 'nullable|string|max:5000']);
        $allowed = ['pending_payment' => ['cancelled'], 'confirmed' => ['processing', 'packed', 'cancelled'], 'processing' => ['packed', 'cancelled'], 'packed' => ['shipped', 'cancelled'], 'shipped' => ['out_for_delivery', 'delivered', 'returned'], 'out_for_delivery' => ['delivered', 'returned'], 'delivered' => ['returned'], 'cancelled' => [], 'returned' => []];
        if ($d['order_status'] !== $order->order_status && !in_array($d['order_status'], $allowed[$order->order_status] ?? [], true)) throw ValidationException::withMessages(['order_status' => 'Invalid status transition.']);
        if (in_array($d['order_status'], ['confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered'], true) && $order->payment_status !== 'paid') throw ValidationException::withMessages(['order_status' => 'Only paid orders can enter fulfillment.']);
        if ($d['order_status'] === 'shipped' && !($d['shipping_id'] ?? $order->shipping_id)) throw ValidationException::withMessages(['shipping_id' => 'Add a shipping/tracking ID before marking shipped.']);
        $statusChanged = $d['order_status'] !== $order->order_status;
        DB::transaction(function () use ($order, $d) {
            $changes = $d;
            if ($d['order_status'] === 'shipped' && !$order->shipped_at) $changes['shipped_at'] = now();
            if ($d['order_status'] === 'delivered' && !$order->delivered_at) $changes['delivered_at'] = now();
            $order->update($changes);
        });
        if ($statusChanged && in_array($d['order_status'], ['shipped', 'delivered'], true)) {
            app(OrderStatusEmailService::class)->sendIfNeeded($order, $d['order_status']);
        }
        return redirect()->route('admin.orders.show', $order)->with('status', 'Order updated.');
    }
}
