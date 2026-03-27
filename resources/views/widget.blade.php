<x-filament-widgets::widget>
    {{ $this->content }}

    <div class="px-6 pb-2 -mt-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Coupon Code
        </label>
        <input
            type="text"
            wire:model.defer="couponCode"
            placeholder="Enter coupon code (optional)"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400"
        />
    </div>

    <div class="px-6 pb-4">
        {{ $this->priceActions }}
    </div>
</x-filament-widgets::widget>
