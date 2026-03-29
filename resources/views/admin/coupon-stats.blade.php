<x-filament-panels::page>
    @php
        $fmt = new NumberFormatter(auth()->user()->language, \NumberFormatter::CURRENCY);
        $coupon = $this->getRecord();
        $pct = $this->maxRedemptions > 0
            ? min(100, round(($this->totalUses / $this->maxRedemptions) * 100))
            : null;
    @endphp

    {{-- ── Coupon identity badge ──────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3 -mt-2 mb-2">
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 ring-1 ring-primary-200 dark:ring-primary-700">
            <x-filament::icon icon="tabler-receipt-tax" class="w-3.5 h-3.5"/>
            {{ $coupon->code }}
        </span>
        @if($coupon->amount_off)
            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300 ring-1 ring-success-200 dark:ring-success-700">
                {{ $fmt->formatCurrency($coupon->amount_off, $this->currency) }} off
            </span>
        @elseif($coupon->percent_off)
            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300 ring-1 ring-success-200 dark:ring-success-700">
                {{ $coupon->percent_off }}% off
            </span>
        @endif
        @if($coupon->redeem_by)
            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold bg-warning-100 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300 ring-1 ring-warning-200 dark:ring-warning-700">
                <x-filament::icon icon="tabler-clock" class="w-3.5 h-3.5"/>
                Expires {{ $coupon->redeem_by->format('M j, Y') }}
            </span>
        @endif
    </div>

    {{-- ── KPI Cards ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">

        @php
            $kpis = [
                [
                    'label' => 'Total Uses',
                    'value' => number_format($this->totalUses),
                    'icon'  => 'tabler-ticket',
                    'color' => 'text-primary-600 dark:text-primary-400',
                    'bg'    => 'bg-primary-50 dark:bg-primary-900/30',
                ],
                [
                    'label' => 'Revenue Saved',
                    'value' => $fmt->formatCurrency($this->revenueSaved, $this->currency),
                    'icon'  => 'tabler-cash-banknote',
                    'color' => 'text-success-600 dark:text-success-400',
                    'bg'    => 'bg-success-50 dark:bg-success-900/30',
                ],
                [
                    'label' => 'This Month',
                    'value' => number_format($this->usesThisMonth),
                    'icon'  => 'tabler-calendar-stats',
                    'color' => 'text-info-600 dark:text-info-400',
                    'bg'    => 'bg-info-50 dark:bg-info-900/30',
                ],
                [
                    'label' => $this->maxRedemptions > 0 ? 'Remaining' : 'No Limit',
                    'value' => $this->remaining !== null ? number_format($this->remaining) : '∞',
                    'icon'  => 'tabler-percentage',
                    'color' => 'text-warning-600 dark:text-warning-400',
                    'bg'    => 'bg-warning-50 dark:bg-warning-900/30',
                ],
                [
                    'label' => 'Avg Discount',
                    'value' => $fmt->formatCurrency($this->avgDiscount, $this->currency),
                    'icon'  => 'tabler-arrow-badge-down',
                    'color' => 'text-danger-600 dark:text-danger-400',
                    'bg'    => 'bg-danger-50 dark:bg-danger-900/30',
                ],
            ];
        @endphp

        @foreach($kpis as $kpi)
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $kpi['label'] }}</span>
                    <span class="rounded-lg p-1.5 {{ $kpi['bg'] }}">
                        <x-filament::icon :icon="$kpi['icon']" class="w-4 h-4 {{ $kpi['color'] }}"/>
                    </span>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $kpi['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- ── Redemption progress bar (only when max_redemptions set) ────────── --}}
    @if($this->maxRedemptions > 0)
        <x-filament::section>
            <x-slot name="heading">Redemption Progress</x-slot>
            <div class="flex items-center gap-4">
                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                    <div
                        class="h-3 rounded-full transition-all duration-700 {{ $pct >= 90 ? 'bg-danger-500' : ($pct >= 60 ? 'bg-warning-500' : 'bg-success-500') }}"
                        style="width: {{ $pct }}%"
                    ></div>
                </div>
                <span class="shrink-0 text-sm font-semibold text-gray-700 dark:text-gray-300 tabular-nums">
                    {{ number_format($this->totalUses) }} / {{ number_format($this->maxRedemptions) }}
                    <span class="font-normal text-gray-400">({{ $pct }}%)</span>
                </span>
            </div>
        </x-filament::section>
    @endif

    {{-- ── Charts row ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Line chart: uses over time --}}
        <div class="lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">Uses — Last 30 Days</x-slot>
                @if($this->totalUses > 0)
                    <div class="h-56">
                        <canvas id="billing-usage-chart"></canvas>
                    </div>
                @else
                    <p class="py-8 text-center text-sm text-gray-400">No uses recorded yet.</p>
                @endif
            </x-filament::section>
        </div>

        {{-- Donut chart: product breakdown --}}
        <div>
            <x-filament::section>
                <x-slot name="heading">Products</x-slot>
                @if($this->totalUses > 0)
                    <div class="h-56 flex items-center justify-center">
                        <canvas id="billing-product-chart"></canvas>
                    </div>
                @else
                    <p class="py-8 text-center text-sm text-gray-400">No data yet.</p>
                @endif
            </x-filament::section>
        </div>
    </div>

    {{-- ── Status breakdown + Recent orders ─────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Status breakdown (donut + legend) --}}
        <div>
            <x-filament::section class="h-full">
                <x-slot name="heading">Order Statuses</x-slot>
                @if($this->totalUses > 0)
                    <div class="h-40 flex items-center justify-center">
                        <canvas id="billing-status-chart"></canvas>
                    </div>
                    <div class="mt-4 space-y-2" id="billing-status-legend"></div>
                @else
                    <p class="py-8 text-center text-sm text-gray-400">No data yet.</p>
                @endif
            </x-filament::section>
        </div>

        {{-- Recent orders --}}
        <div class="lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">Recent Orders</x-slot>
                @if(count($this->recentOrders))
                    <div class="overflow-x-auto -mx-2">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-white/10 text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-3 py-2 text-left">#</th>
                                    <th class="px-3 py-2 text-left">Customer</th>
                                    <th class="px-3 py-2 text-left">Product</th>
                                    <th class="px-3 py-2 text-right">Price</th>
                                    <th class="px-3 py-2 text-right">Saved</th>
                                    <th class="px-3 py-2 text-left">Status</th>
                                    <th class="px-3 py-2 text-left">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                                @foreach($this->recentOrders as $row)
                                    @php
                                        $badge = match($row['color']) {
                                            'success' => 'bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300',
                                            'warning' => 'bg-warning-100 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300',
                                            'danger'  => 'bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300',
                                            default   => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                        <td class="px-3 py-2.5 text-gray-400 font-mono text-xs">{{ str_pad($row['id'], 4, '0', STR_PAD_LEFT) }}</td>
                                        <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-white">{{ $row['customer'] }}</td>
                                        <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400 max-w-[160px] truncate">{{ $row['product'] }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $row['price'] }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-success-600 dark:text-success-400 font-medium">−{{ $row['discount'] }}</td>
                                        <td class="px-3 py-2.5">
                                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ $row['status'] }}</span>
                                        </td>
                                        <td class="px-3 py-2.5 text-gray-400 text-xs whitespace-nowrap">{{ $row['date'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="py-8 text-center text-sm text-gray-400">No orders have used this coupon yet.</p>
                @endif
            </x-filament::section>
        </div>
    </div>
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
                    backgroundColor: 'rgba(99,102,241,0.12)',
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

    // ── Product doughnut chart ────────────────────────────────────────────────
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
                    borderColor: isDark() ? '#111827' : '#ffffff',
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor(),
                            boxWidth: 10,
                            padding: 10,
                            font: { size: 11 },
                        },
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

    // ── Status doughnut chart ─────────────────────────────────────────────────
    const statusEl = document.getElementById('billing-status-chart');
    if (statusEl) {
        const status = @json(json_decode($this->statusChartJson));
        const chart = new Chart(statusEl, {
            type: 'doughnut',
            data: {
                labels: status.labels,
                datasets: [{
                    data: status.data,
                    backgroundColor: status.colors,
                    borderWidth: 2,
                    borderColor: isDark() ? '#111827' : '#ffffff',
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { display: false } },
            },
        });

        // Custom legend
        const legendEl = document.getElementById('billing-status-legend');
        if (legendEl) {
            const total = status.data.reduce((a, b) => a + b, 0);
            legendEl.innerHTML = status.labels.map((label, i) => `
                <div class="flex items-center justify-between gap-2 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" style="background:${status.colors[i]}"></span>
                        <span class="text-gray-700 dark:text-gray-300">${label}</span>
                    </div>
                    <span class="text-gray-400 tabular-nums">${status.data[i]} <span class="text-gray-300 dark:text-gray-600">(${Math.round(status.data[i]/total*100)}%)</span></span>
                </div>
            `).join('');
        }
    }
</script>
@endscript
