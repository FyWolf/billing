<x-filament-panels::page>
    @php
        $fmt = new NumberFormatter(auth()->user()->language, \NumberFormatter::CURRENCY);
        $coupon = $this->getRecord();
        $pct = $this->maxRedemptions > 0
            ? min(100, round(($this->totalUses / $this->maxRedemptions) * 100))
            : null;
    @endphp

    {{-- ── Coupon identity + KPIs in one compact section ─────────────────── --}}
    <x-filament::section>
        {{-- Pills row --}}
        <div class="flex flex-wrap items-center gap-2 mb-4 pb-4 border-b border-gray-100 dark:border-white/10">
            <span class="rounded-md bg-gray-100 dark:bg-white/10 px-2.5 py-1 text-xs font-mono font-semibold text-gray-700 dark:text-gray-200">
                {{ $coupon->code }}
            </span>
            @if($coupon->amount_off)
                <span class="rounded-md bg-green-50 dark:bg-green-900/30 px-2.5 py-1 text-xs font-semibold text-green-700 dark:text-green-300">
                    {{ $fmt->formatCurrency($coupon->amount_off, $this->currency) }} off
                </span>
            @elseif($coupon->percent_off)
                <span class="rounded-md bg-green-50 dark:bg-green-900/30 px-2.5 py-1 text-xs font-semibold text-green-700 dark:text-green-300">
                    {{ $coupon->percent_off }}% off
                </span>
            @endif
            @if($coupon->redeem_by)
                <span class="rounded-md bg-amber-50 dark:bg-amber-900/30 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                    Expires {{ $coupon->redeem_by->format('M j, Y') }}
                </span>
            @endif
            @if($this->maxRedemptions > 0 && $pct !== null)
                <span class="rounded-md {{ $pct >= 90 ? 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300' : 'bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-300' }} px-2.5 py-1 text-xs font-semibold">
                    {{ $pct }}% redeemed
                </span>
            @endif
        </div>

        {{-- KPI stat strip --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-x-6 gap-y-4">
            @php
                $kpis = [
                    ['label' => 'Total Uses',     'value' => number_format($this->totalUses),                                             'sub' => null],
                    ['label' => 'Revenue Saved',  'value' => $fmt->formatCurrency($this->revenueSaved, $this->currency),                  'sub' => null],
                    ['label' => 'This Month',     'value' => number_format($this->usesThisMonth),                                         'sub' => null],
                    ['label' => 'Avg Discount',   'value' => $fmt->formatCurrency($this->avgDiscount, $this->currency),                   'sub' => 'per order'],
                    ['label' => $this->maxRedemptions > 0 ? 'Remaining Uses' : 'Redemption Cap',
                     'value' => $this->remaining !== null ? number_format($this->remaining) : '∞',
                     'sub'   => $this->maxRedemptions > 0 ? 'of ' . number_format($this->maxRedemptions) : 'no limit'],
                ];
            @endphp
            @foreach($kpis as $kpi)
                <div>
                    <div class="text-[11px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-0.5">
                        {{ $kpi['label'] }}
                    </div>
                    <div class="text-xl font-bold text-gray-900 dark:text-white leading-tight">
                        {{ $kpi['value'] }}
                    </div>
                    @if($kpi['sub'])
                        <div class="text-xs text-gray-400 mt-0.5">{{ $kpi['sub'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Redemption progress bar --}}
        @if($this->maxRedemptions > 0)
            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <div class="flex-1 h-2 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700">
                        <div
                            class="h-2 rounded-full {{ $pct >= 90 ? 'bg-red-500' : ($pct >= 60 ? 'bg-amber-500' : 'bg-green-500') }}"
                            style="width: {{ $pct }}%"
                        ></div>
                    </div>
                    <span class="shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                        {{ number_format($this->totalUses) }} / {{ number_format($this->maxRedemptions) }}
                    </span>
                </div>
            </div>
        @endif
    </x-filament::section>

    {{-- ── Line chart — full width ─────────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Uses — Last 30 Days</x-slot>
        @if($this->totalUses > 0)
            <div class="h-52">
                <canvas id="billing-usage-chart"></canvas>
            </div>
        @else
            <p class="py-10 text-center text-sm text-gray-400">No uses recorded yet.</p>
        @endif
    </x-filament::section>

    {{-- ── Products + Order Statuses side by side ────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <x-filament::section>
            <x-slot name="heading">Products</x-slot>
            @if($this->totalUses > 0)
                <div class="h-52 flex items-center justify-center">
                    <canvas id="billing-product-chart"></canvas>
                </div>
            @else
                <p class="py-10 text-center text-sm text-gray-400">No data yet.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Order Statuses</x-slot>
            @if($this->totalUses > 0)
                <div class="h-36 flex items-center justify-center">
                    <canvas id="billing-status-chart"></canvas>
                </div>
                <div class="mt-3 space-y-1.5" id="billing-status-legend"></div>
            @else
                <p class="py-10 text-center text-sm text-gray-400">No data yet.</p>
            @endif
        </x-filament::section>
    </div>

    {{-- ── Recent orders ───────────────────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Recent Orders</x-slot>
        @if(count($this->recentOrders))
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/10 text-[11px] uppercase tracking-wide text-gray-400">
                            <th class="px-3 py-2 text-left font-medium">#</th>
                            <th class="px-3 py-2 text-left font-medium">Customer</th>
                            <th class="px-3 py-2 text-left font-medium">Product</th>
                            <th class="px-3 py-2 text-right font-medium">Price</th>
                            <th class="px-3 py-2 text-right font-medium">Saved</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            <th class="px-3 py-2 text-left font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                        @foreach($this->recentOrders as $row)
                            @php
                                $badge = match($row['color']) {
                                    'success' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                                    'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                    'danger'  => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                    default   => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                <td class="px-3 py-2.5 font-mono text-xs text-gray-400">{{ str_pad($row['id'], 4, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-white">{{ $row['customer'] }}</td>
                                <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400 max-w-[160px] truncate">{{ $row['product'] }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $row['price'] }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-medium text-green-600 dark:text-green-400">−{{ $row['discount'] }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ $row['status'] }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-gray-400 whitespace-nowrap">{{ $row['date'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="py-10 text-center text-sm text-gray-400">No orders have used this coupon yet.</p>
        @endif
    </x-filament::section>

</x-filament-panels::page>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endassets

@script
<script>
    const isDark = () => document.documentElement.classList.contains('dark');
    const gridColor = () => isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
    const textColor = () => isDark() ? '#9ca3af' : '#6b7280';

    // ── Usage line chart ──────────────────────────────────────────────────────
    const usageEl = document.getElementById('billing-usage-chart');
    if (usageEl) {
        const usage = @json(json_decode($this->usageChartJson));
        new Chart(usageEl, {
            type: 'line',
            data: {
                labels: usage.labels,
                datasets: [{
                    label: 'Uses',
                    data: usage.data,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.10)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 3,
                    pointBackgroundColor: '#6366f1',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y} use${ctx.parsed.y !== 1 ? 's' : ''}`,
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: textColor(), maxTicksLimit: 10, maxRotation: 0 },
                        grid:  { color: gridColor() },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: textColor(), precision: 0 },
                        grid:  { color: gridColor() },
                    },
                },
            },
        });
    }

    // ── Product doughnut ──────────────────────────────────────────────────────
    const productEl = document.getElementById('billing-product-chart');
    if (productEl) {
        const product = @json(json_decode($this->productChartJson));
        new Chart(productEl, {
            type: 'doughnut',
            data: {
                labels: product.labels,
                datasets: [{
                    data: product.data,
                    backgroundColor: product.colors,
                    borderWidth: 2,
                    borderColor: isDark() ? '#1f2937' : '#ffffff',
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: textColor(), boxWidth: 10, padding: 8, font: { size: 11 } },
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed} order${ctx.parsed !== 1 ? 's' : ''}`,
                        },
                    },
                },
            },
        });
    }

    // ── Status doughnut ───────────────────────────────────────────────────────
    const statusEl = document.getElementById('billing-status-chart');
    if (statusEl) {
        const status = @json(json_decode($this->statusChartJson));
        new Chart(statusEl, {
            type: 'doughnut',
            data: {
                labels: status.labels,
                datasets: [{
                    data: status.data,
                    backgroundColor: status.colors,
                    borderWidth: 2,
                    borderColor: isDark() ? '#1f2937' : '#ffffff',
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { display: false } },
            },
        });

        const legendEl = document.getElementById('billing-status-legend');
        if (legendEl) {
            const total = status.data.reduce((a, b) => a + b, 0);
            legendEl.innerHTML = status.labels.map((label, i) => `
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:12px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="width:8px;height:8px;border-radius:50%;background:${status.colors[i]};flex-shrink:0;display:inline-block;"></span>
                        <span style="color:${isDark()?'#d1d5db':'#374151'}">${label}</span>
                    </div>
                    <span style="color:#9ca3af;font-variant-numeric:tabular-nums;">${status.data[i]} <span style="opacity:.5;">(${Math.round(status.data[i]/total*100)}%)</span></span>
                </div>
            `).join('');
        }
    }
</script>
@endscript
