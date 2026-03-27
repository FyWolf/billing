<x-filament-panels::page>
    <div style="max-width: 42rem; margin: 0 auto;">
        <div style="border-radius: 0.75rem; padding: 2rem; text-align: center;" class="bg-white dark:bg-gray-800 fi-section rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
            {{-- Success icon --}}
            <div style="width: 4rem; height: 4rem; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; border-radius: 9999px; background: rgb(220 252 231);">
                <svg style="width: 2.5rem; height: 2.5rem; color: rgb(22 163 74);" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
            </div>

            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;" class="text-gray-950 dark:text-white">
                Order Confirmed!
            </h2>

            <p style="margin-bottom: 2rem;" class="text-gray-500 dark:text-gray-400">
                Thank you for your purchase. Your order has been received and is being processed.
                A confirmation email with your invoice has been sent to your email address.
            </p>

            {{-- Order details --}}
            <div style="border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 2rem; text-align: left;" class="bg-white dark:bg-gray-900">
                <h3 style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;" class="text-gray-950 dark:text-white">
                    Order Summary
                </h3>

                <dl style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <dt style="font-size: 0.875rem;" class="text-gray-500 dark:text-gray-400">Order Number</dt>
                        <dd style="font-size: 0.875rem; font-weight: 500;" class="text-gray-950 dark:text-white">#{{ $order->id }}</dd>
                    </div>

                    <div style="display: flex; justify-content: space-between;">
                        <dt style="font-size: 0.875rem;" class="text-gray-500 dark:text-gray-400">Product</dt>
                        <dd style="font-size: 0.875rem; font-weight: 500;" class="text-gray-950 dark:text-white">{{ $order->productPrice->product->name }}</dd>
                    </div>

                    <div style="display: flex; justify-content: space-between;">
                        <dt style="font-size: 0.875rem;" class="text-gray-500 dark:text-gray-400">Plan</dt>
                        <dd style="font-size: 0.875rem; font-weight: 500;" class="text-gray-950 dark:text-white">{{ $order->productPrice->name }}</dd>
                    </div>

                    <div style="display: flex; justify-content: space-between;">
                        <dt style="font-size: 0.875rem;" class="text-gray-500 dark:text-gray-400">Amount</dt>
                        <dd style="font-size: 0.875rem; font-weight: 500;" class="text-gray-950 dark:text-white">{{ $order->productPrice->formatCost() }}</dd>
                    </div>

                    <div style="display: flex; justify-content: space-between;">
                        <dt style="font-size: 0.875rem;" class="text-gray-500 dark:text-gray-400">Billing Period</dt>
                        <dd style="font-size: 0.875rem; font-weight: 500;" class="text-gray-950 dark:text-white">
                            {{ $order->productPrice->interval_value }} {{ str_plural($order->productPrice->interval_type->getLabel(), $order->productPrice->interval_value) }}
                        </dd>
                    </div>

                    <div style="display: flex; justify-content: space-between;">
                        <dt style="font-size: 0.875rem;" class="text-gray-500 dark:text-gray-400">Status</dt>
                        <dd style="font-size: 0.875rem; font-weight: 500; color: rgb(22 163 74);">{{ ucfirst($order->status->getLabel()) }}</dd>
                    </div>

                    @if($order->payment_gateway)
                        <div style="display: flex; justify-content: space-between;">
                            <dt style="font-size: 0.875rem;" class="text-gray-500 dark:text-gray-400">Payment Method</dt>
                            <dd style="font-size: 0.875rem; font-weight: 500;" class="text-gray-950 dark:text-white">{{ ucfirst($order->payment_gateway) }}</dd>
                        </div>
                    @endif

                    @if($order->expires_at)
                        <div style="display: flex; justify-content: space-between;">
                            <dt style="font-size: 0.875rem;" class="text-gray-500 dark:text-gray-400">Expires</dt>
                            <dd style="font-size: 0.875rem; font-weight: 500;" class="text-gray-950 dark:text-white">{{ $order->expires_at->format('M d, Y') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Actions --}}
            <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                @if($order->server)
                    <a href="{{ \App\Filament\Server\Pages\Console::getUrl(panel: 'server', tenant: $order->server) }}"
                       style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 600; color: white; background: rgb(99 102 241); border-radius: 0.5rem; text-decoration: none;">
                        Go to Server Console
                    </a>
                @endif

                <a href="{{ \Fywolf\Billing\Filament\App\Resources\Orders\Pages\ListOrders::getUrl(panel: 'app') }}"
                   style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 600; border-radius: 0.5rem; text-decoration: none; border: 1px solid rgb(209 213 219);"
                   class="text-gray-700 dark:text-gray-300 dark:border-gray-600">
                    View My Orders
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>
