@extends('layouts.app')

@section('content')
<div class="flex-1 p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div><h1 class="text-3xl font-black text-white uppercase tracking-tighter">Sales History</h1>@if(auth()->user()->isCashier())<p class="text-sm text-on-surface-variant mt-1">Showing only sales from your register shifts.</p>@endif</div>
            <div class="flex gap-4">
                <div class="px-4 py-2 bg-active-surface rounded-xl border border-outline flex items-center gap-2">
                    <span class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Total Orders:</span>
                    <span class="text-sm font-black text-primary">{{ $orders->total() }}</span>
                </div>
            </div>
        </div>

        <div class="bg-surface border border-outline rounded-2xl overflow-hidden shadow-2xl">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-active-surface/50 border-b border-outline">
                        <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Order ID</th>
                        <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Sale Type / Customer</th>
                        <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Time</th>
                        <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Items</th>
                        <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest text-right">Amount</th>
                        <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest text-center">Status</th>
                        @if(!auth()->user()->isCashier())<th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest text-center">FBR</th>@endif
                        <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline/5">
                    @forelse($orders as $order)
                    <tr class="hover:bg-active-surface/20 transition-colors">
                        <td class="px-6 py-4">
                            <span class="text-sm font-black text-white">#{{ $order->order_number }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black text-primary uppercase tracking-tighter">{{ $order->order_type ?? 'COUNTER' }}</span>
                                @if($order->customer_name)
                                <span class="text-[10px] text-on-surface-variant">{{ $order->customer_name }} {{ $order->customer_phone ? '/ '.$order->customer_phone : '' }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[11px] text-on-surface-variant font-medium">{{ $order->created_at->format('d M, h:i A') }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex -space-x-2 overflow-hidden">
                                @foreach($order->items->take(3) as $item)
                                <div class="inline-block h-6 w-6 rounded-full ring-2 ring-surface bg-active-surface overflow-hidden text-center text-[10px] font-bold text-primary leading-6">
                                    @if($item->item?->image_path)
                                        <img src="{{ asset($item->item->image_path) }}" class="h-full w-full object-cover">
                                    @else
                                        {{ strtoupper(substr($item->item?->name ?? 'P', 0, 1)) }}
                                    @endif
                                </div>
                                @endforeach
                                @if($order->items->count() > 3)
                                <div class="flex items-center justify-center h-6 w-6 rounded-full ring-2 ring-surface bg-active-surface text-[8px] font-black text-on-surface-variant">
                                    +{{ $order->items->count() - 3 }}
                                </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-label-numeric text-sm font-black text-primary">{{ $settings->currency_symbol ?? 'Rs.' }} {{ number_format($order->grand_total, 2) }}</span>
                        </td>
                        @if(!auth()->user()->isCashier())<td class="px-6 py-4 text-center">
                            <span class="px-2.5 py-1 rounded-md bg-primary/10 text-primary text-[10px] font-black uppercase tracking-tighter">
                                {{ $order->status === 'cancelled' ? 'Refunded / voided' : $order->status }}
                            </span>
                        </td>@endif
                        <td class="px-6 py-4 text-center">
                            @php
                                $fbrClass = match($order->fbr_status) {
                                    'submitted' => 'bg-primary/10 text-primary',
                                    'pending' => 'bg-amber-500/10 text-amber-400',
                                    'failed', 'blocked' => 'bg-error/10 text-error',
                                    default => 'bg-active-surface text-on-surface-variant',
                                };
                            @endphp
                            <span title="{{ $order->fbr_error ?: $order->fbr_invoice_number }}" class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase {{ $fbrClass }}">
                                {{ str_replace('_', ' ', $order->fbr_status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2"><button
                                onclick="printOrderReceipt({{ json_encode([
                                    'token' => substr($order->order_number, -4),
                                    'orderType' => $order->order_type ?? 'COUNTER',
                                    'date' => $order->created_at->toIso8601String(),
                                    'status' => $order->status,
                                    'items' => $order->items->map(fn($i) => ['name' => $i->item->name, 'quantity' => $i->quantity, 'price' => (float)$i->price, 'uom' => $i->item?->uom]),
                                    'subtotal' => (float)$order->subtotal,
                                    'taxTotal' => (float)$order->tax,
                                    'grandTotal' => (float)$order->grand_total,
                                    'customerName' => $order->customer_name,
                                    'customerPhone' => $order->customer_phone,
                                    'discount' => (float)$order->discount,
                                    'paymentMethod' => $order->payment_method ?? 'cash',
                                    'amountTendered' => (float)$order->amount_tendered,
                                    'changeAmount' => (float)$order->change_amount,
                                    'transactionReference' => $order->transaction_reference,
                                    'fbrInvoiceNumber' => $order->fbr_invoice_number,
                                    'fbrQr' => \App\Services\FBRIntegrationService::qrDataUri($order->fbr_invoice_number),
                                    'currencySymbol' => $settings->currency_symbol ?? 'Rs.',
                                ]) }})"
                                class="w-8 h-8 rounded-lg bg-active-surface hover:bg-primary hover:text-on-primary transition-all flex items-center justify-center border border-outline"
                            >
                                <span class="material-symbols-rounded text-sm">print</span>
                            </button>
                            @if(auth()->user()->hasAnyRole(['admin', 'manager']) && $order->status !== 'cancelled')
                                <button onclick="refundOrder('{{ $order->id }}', '{{ $order->payment_method }}')" title="Refund or void this sale" class="w-8 h-8 rounded-lg bg-error/10 text-error hover:bg-error hover:text-white transition-all flex items-center justify-center border border-error/30"><span class="material-symbols-rounded text-sm">undo</span></button>
                            @endif</div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center opacity-20">
                                <span class="material-symbols-rounded text-6xl">receipt_long</span>
                                <p class="mt-4 font-black uppercase tracking-widest">No orders found</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="mt-8">
            {{ $orders->links() }}
        </div>
    </div>
</div>
@endsection

<script>
    function printOrderReceipt(data) {
        if (typeof printReceipt === 'function') {
            printReceipt(data);
        } else {
            console.error('printReceipt function not found in layout');
        }
    }

    async function refundOrder(id, paymentMethod) {
        const reason = prompt('Why is this sale being refunded or voided?');
        if (!reason?.trim()) return;
        const restock = confirm('Are ALL returned goods unopened/fresh and physically back at the counter?\n\nOK = return them to saleable stock\nCancel = keep them out of stock as loss/waste');
        let refundReference = '';
        if (paymentMethod !== 'cash') {
            refundReference = prompt('First refund on the bank terminal/provider. Enter its refund RRN/reference:') || '';
            if (!refundReference.trim()) return alert('A bank refund reference is required.');
        } else if (!confirm('Confirm the cash has been handed back from the CURRENT register.')) {
            return;
        }
        if (!confirm('Final confirmation: refund this full sale and ' + (restock ? 'restore sellable stock?' : 'keep returned goods out of stock?'))) return;
        const response = await fetch('{{ url('/api/orders') }}/' + id + '/cancel', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ reason: reason.trim(), restock, refund_reference: refundReference.trim() }),
        });
        const result = await response.json();
        if (!response.ok) return alert(result.message || 'Sale could not be refunded.');
        window.location.reload();
    }
</script>
