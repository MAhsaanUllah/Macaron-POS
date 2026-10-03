@extends('layouts.app')

@section('content')
<div class="flex-1 p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-black text-white uppercase tracking-tighter">Reports & Analytics</h1>
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('reports.index') }}" class="flex items-center gap-2 bg-surface border border-outline rounded-xl px-3 py-1.5">
                    <span class="material-symbols-rounded text-on-surface-variant text-sm">calendar_today</span>
                    <input type="date" name="date_from" value="{{ $dateFrom }}"
                           class="bg-transparent border-0 text-[9px] font-bold text-white uppercase tracking-widest focus:ring-0 p-0 w-28"
                           onchange="this.form.submit()">
                    <span class="text-on-surface-variant text-[8px] font-black uppercase">to</span>
                    <input type="date" name="date_to" value="{{ $dateTo }}"
                           class="bg-transparent border-0 text-[9px] font-bold text-white uppercase tracking-widest focus:ring-0 p-0 w-28"
                           onchange="this.form.submit()">
                </form>
                <button onclick="window.print()" class="px-6 py-2.5 bg-active-surface text-on-surface text-[10px] font-black rounded-xl border border-outline hover:bg-outline transition-all uppercase tracking-widest no-print">Print Report</button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
                <h3 class="text-on-surface-variant text-[10px] font-black uppercase tracking-widest mb-4">Today's Sales</h3>
                <div class="flex items-end gap-2">
                    <span class="text-3xl font-black text-primary">{{ $settings->currency_symbol }} {{ number_format($todaySales, 2) }}</span>
                </div>
            </div>

            <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
                <h3 class="text-on-surface-variant text-[10px] font-black uppercase tracking-widest mb-4">Today's Orders</h3>
                <div class="flex items-end gap-2">
                    <span class="text-3xl font-black text-white">{{ $todayOrders }}</span>
                    <span class="text-[10px] font-bold text-primary mb-1 uppercase tracking-widest">Transactions</span>
                </div>
            </div>

            <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
                <h3 class="text-on-surface-variant text-[10px] font-black uppercase tracking-widest mb-4">All Time Revenue</h3>
                <div class="flex items-end gap-2">
                    <span class="text-3xl font-black text-white">{{ $settings->currency_symbol }} {{ number_format($totalSales, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- P&L Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-surface border border-outline rounded-2xl p-5 shadow-xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-20 h-20 bg-primary/5 rounded-full -mr-6 -mt-6"></div>
                <h3 class="text-on-surface-variant text-[8px] font-black uppercase tracking-widest mb-3">Gross Sales</h3>
                <p class="text-2xl font-black text-primary tracking-tight">{{ $settings->currency_symbol }}{{ number_format($grossSales, 2) }}</p>
                <p class="text-[8px] text-on-surface-variant font-bold uppercase tracking-widest mt-1">{{ $dateFrom }} to {{ $dateTo }}</p>
            </div>
            <div class="bg-surface border border-outline rounded-2xl p-5 shadow-xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-20 h-20 bg-rose-500/5 rounded-full -mr-6 -mt-6"></div>
                <h3 class="text-on-surface-variant text-[8px] font-black uppercase tracking-widest mb-3">Material Cost</h3>
                <p class="text-2xl font-black text-rose-400 tracking-tight">{{ $settings->currency_symbol }}{{ number_format($materialCost, 2) }}</p>
                <p class="text-[8px] text-rose-400/50 font-bold uppercase tracking-widest mt-1">{{ $grossSales > 0 ? round(($materialCost / $grossSales) * 100, 1) : 0 }}% of revenue</p>
            </div>
            <div class="bg-surface border border-outline rounded-2xl p-5 shadow-xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-20 h-20 bg-amber-500/5 rounded-full -mr-6 -mt-6"></div>
                <h3 class="text-on-surface-variant text-[8px] font-black uppercase tracking-widest mb-3">Op. Expenses</h3>
                <p class="text-2xl font-black text-amber-400 tracking-tight">{{ $settings->currency_symbol }}{{ number_format($opEx, 2) }}</p>
                <p class="text-[8px] text-amber-400/50 font-bold uppercase tracking-widest mt-1">{{ $grossSales > 0 ? round(($opEx / $grossSales) * 100, 1) : 0 }}% of revenue</p>
            </div>
            <div class="bg-surface border border-outline rounded-2xl p-5 shadow-xl relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-20 h-20 bg-primary/5 rounded-full -mr-6 -mt-6 transition-transform group-hover:scale-110 {{ $netProfit >= 0 ? 'bg-primary/5' : 'bg-rose-500/5' }}"></div>
                <h3 class="text-on-surface-variant text-[8px] font-black uppercase tracking-widest mb-3">Net Profit</h3>
                <p class="text-2xl font-black tracking-tight {{ $netProfit >= 0 ? 'text-primary' : 'text-rose-400' }}">
                    {{ $settings->currency_symbol }}{{ number_format($netProfit, 2) }}
                </p>
                <p class="text-[8px] font-bold uppercase tracking-widest mt-1 {{ $netProfit >= 0 ? 'text-primary/50' : 'text-rose-400/50' }}">
                    {{ $grossSales > 0 ? round(($netProfit / $grossSales) * 100, 1) : 0 }}% margin
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- P&L Stacked Bar Chart -->
            <div class="lg:col-span-2 bg-surface border border-outline rounded-2xl p-8 shadow-xl min-h-[400px]">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-black text-white uppercase tracking-widest">Profit & Loss</h2>
                        <p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest mt-1">Daily revenue vs. costs vs. profit</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-sm bg-primary"></div>
                            <span class="text-[8px] font-bold text-on-surface-variant uppercase tracking-widest">Revenue</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-sm bg-rose-500"></div>
                            <span class="text-[8px] font-bold text-on-surface-variant uppercase tracking-widest">Costs</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 rounded-sm bg-amber-400"></div>
                            <span class="text-[8px] font-bold text-on-surface-variant uppercase tracking-widest">Profit</span>
                        </div>
                    </div>
                </div>
                <div class="relative h-64">
                    <canvas id="pnlChart"></canvas>
                </div>
            </div>

            <!-- Top Selling Items -->
            <div class="bg-surface border border-outline rounded-2xl p-8 shadow-xl">
                <h2 class="text-lg font-black text-white uppercase tracking-widest mb-6">Top Sellers</h2>
                <div class="space-y-6">
                    @forelse($topItems as $top)
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-active-surface border border-outline overflow-hidden flex-shrink-0 flex items-center justify-center font-black text-sm text-primary">
                            @if($top->item?->image_path)
                                <img src="{{ asset($top->item->image_path) }}" class="w-full h-full object-cover">
                            @else
                                {{ strtoupper(substr($top->item?->name ?? 'P', 0, 1)) }}
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-black text-white truncate uppercase tracking-tighter">{{ $top->item->name }}</h4>
                            <p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest">{{ $top->total_qty }} sold</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-black text-primary">{{ $settings->currency_symbol }}{{ number_format($top->total_revenue, 0) }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-xs text-on-surface-variant text-center py-10 uppercase tracking-widest">No data available</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-surface border border-outline rounded-2xl p-8 shadow-xl">
            <h3 class="text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-6">Recent Activity Log</h3>
            <div class="space-y-4">
                @forelse($recentOrders as $order)
                <div class="flex items-center justify-between p-4 rounded-xl border border-outline hover:bg-active-surface/20 transition-all group">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                            <span class="material-symbols-rounded text-primary text-xl">receipt</span>
                        </div>
                        <div>
                            <p class="text-xs font-black text-white uppercase tracking-widest">Order #{{ $order->order_number }}</p>
                            <p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest mt-0.5">{{ $order->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-black text-primary">{{ $settings->currency_symbol }}{{ number_format($order->grand_total, 2) }}</p>
                        <span class="text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded bg-active-surface border border-outline text-on-surface-variant">{{ $order->status }}</span>
                    </div>
                </div>
                @empty
                <div class="flex items-center gap-4 p-4 rounded-xl border border-outline border-dashed opacity-50">
                    <span class="text-[10px] font-black text-on-surface-variant uppercase">No recent activity detected.</span>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Audit Trail Logs -->
        <div class="bg-surface border border-outline rounded-2xl p-8 shadow-xl mt-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-amber-400 text-[10px] font-black uppercase tracking-[0.2em]">Security Audit Trail</h3>
                <span class="text-[8px] font-black uppercase px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">{{ $auditLogs->count() }} events</span>
            </div>
            <div class="space-y-3">
                @forelse($auditLogs as $log)
                @php
                    $iconMap = [
                        'discount_exceeded_threshold' => 'percent',
                        'order_status_changed' => 'swap_horiz',
                        'cart_item_removed' => 'remove_shopping_cart',
                    ];
                    $icon = $iconMap[$log->action_performed] ?? 'shield';
                    $bgMap = [
                        'discount_exceeded_threshold' => 'bg-amber-500/10',
                        'order_status_changed' => 'bg-blue-500/10',
                        'cart_item_removed' => 'bg-red-500/10',
                    ];
                    $bg = $bgMap[$log->action_performed] ?? 'bg-primary/10';
                    $colorMap = [
                        'discount_exceeded_threshold' => 'text-amber-400',
                        'order_status_changed' => 'text-blue-400',
                        'cart_item_removed' => 'text-red-400',
                    ];
                    $color = $colorMap[$log->action_performed] ?? 'text-primary';
                    $roleColorMap = [
                        'manager' => 'text-amber-400',
                        'admin' => 'text-primary',
                    ];
                    $roleColor = $roleColorMap[$log->user?->role] ?? 'text-on-surface-variant';
                @endphp
                <div class="flex items-start gap-3 p-3 rounded-xl border border-outline hover:bg-active-surface/10 transition-all group">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5 {{ $bg }}">
                        <span class="material-symbols-rounded text-sm {{ $color }}">{{ $icon }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-black text-white leading-snug">{{ $log->description }}</p>
                        <div class="flex items-center gap-3 mt-1">
                            <span class="text-[8px] font-bold text-on-surface-variant uppercase tracking-widest">{{ $log->created_at->format('h:i A') }}</span>
                            <span class="text-[8px] font-bold text-on-surface-variant">•</span>
                            <span class="text-[8px] font-bold uppercase tracking-widest {{ $roleColor }}">{{ $log->user?->name ?? 'System' }}
                                <span class="text-on-surface-variant">({{ $log->user?->role ?? 'system' }})</span>
                            </span>
                            <span class="text-[8px] font-bold text-on-surface-variant">•</span>
                            <span class="text-[8px] font-bold text-on-surface-variant font-mono">{{ $log->ip_address ?? 'N/A' }}</span>
                        </div>
                    </div>
                    @if($log->order_id)
                    <a href="#" class="text-[8px] font-black text-primary uppercase tracking-widest flex-shrink-0 hover:underline">View #{{ $log->order_id }}</a>
                    @endif
                </div>
                @empty
                <div class="flex items-center gap-4 p-4 rounded-xl border border-outline border-dashed opacity-50">
                    <span class="material-symbols-rounded text-sm text-on-surface-variant">verified</span>
                    <span class="text-[10px] font-black text-on-surface-variant uppercase">No security events recorded yet.</span>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hourly Sales Trend
        const ctx = document.getElementById('salesChart');
        if (ctx) {
            new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: Array.from({length: 24}, (_, i) => `${i}:00`),
                    datasets: [{
                        label: 'Sales Revenue',
                        data: @json($chartData),
                        borderColor: '#F4B942',
                        backgroundColor: 'rgba(78, 222, 163, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#F4B942',
                        pointBorderColor: '#1a1a1a',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1a1a1a',
                            titleFont: { family: 'Inter', weight: '900', size: 12 },
                            bodyFont: { family: 'Inter', weight: '700', size: 11 },
                            padding: 12,
                            cornerRadius: 12,
                            borderColor: '#333',
                            borderWidth: 1,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Revenue: {{ $settings->currency_symbol }}' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.3)',
                                font: { size: 10, weight: '700' },
                                callback: function(value) { return '{{ $settings->currency_symbol }}' + value; }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.3)',
                                font: { size: 10, weight: '700' },
                                maxRotation: 0, autoSkip: true, maxTicksLimit: 8
                            }
                        }
                    }
                }
            });
        }

        // P&L Stacked Bar Chart
        const pnlCtx = document.getElementById('pnlChart');
        if (pnlCtx) {
            new Chart(pnlCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: @json($dateLabels),
                    datasets: [
                        {
                            label: 'Costs',
                            data: @json($costSeries),
                            backgroundColor: '#f43f5e',
                            borderColor: '#f43f5e',
                            borderWidth: 1,
                            borderRadius: 3,
                            order: 2
                        },
                        {
                            label: 'Profit',
                            data: @json($profitSeries),
                            backgroundColor: '#fbbf24',
                            borderColor: '#fbbf24',
                            borderWidth: 1,
                            borderRadius: 3,
                            order: 1
                        },
                        {
                            label: 'Revenue',
                            data: @json($revenueSeries),
                            backgroundColor: '#F4B942',
                            borderColor: '#F4B942',
                            borderWidth: 1,
                            borderRadius: 3,
                            order: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1a1a1a',
                            titleFont: { family: 'Inter', weight: '900', size: 12 },
                            bodyFont: { family: 'Inter', weight: '700', size: 11 },
                            padding: 12,
                            cornerRadius: 12,
                            borderColor: '#333',
                            borderWidth: 1,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': {{ $settings->currency_symbol }}' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.3)',
                                font: { size: 9, weight: '700' },
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 10
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.3)',
                                font: { size: 10, weight: '700' },
                                callback: function(value) { return '{{ $settings->currency_symbol }}' + value; }
                            }
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
