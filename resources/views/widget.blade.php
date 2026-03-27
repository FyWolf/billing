<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

    {{-- Header --}}
    <div class="fi-section-header flex flex-col gap-y-1 px-6 py-5">
        <h3 class="fi-section-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
            {{ $this->product->getLabel() }}
        </h3>
        @if($this->product->description)
            <p class="fi-section-description text-sm text-gray-500 dark:text-gray-400">
                {{ $this->product->description }}
            </p>
        @endif
    </div>

    <div class="fi-section-content-ctn border-t border-gray-100 dark:border-white/5">
        <div class="fi-section-content p-6">

            {{-- Spec entries (CPU, RAM, disk) --}}
            {{ $this->content }}

            {{-- Coupon code --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Coupon Code
                </label>
                <input
                    type="text"
                    wire:model.defer="couponCode"
                    placeholder="Enter coupon code (optional)"
                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-950 shadow-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-gray-500 dark:focus:ring-primary-500"
                />
            </div>

            {{-- Order buttons --}}
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($this->product->prices as $price)
                    <x-filament::button
                        wire:click="placeOrder({{ $price->id }})"
                        wire:loading.attr="disabled"
                        wire:target="placeOrder({{ $price->id }})"
                    >
                        {{ $price->getLabel() }}{{ $price->hasTrial() ? ' (' . $price->trial_days . '-day free trial)' : '' }}
                    </x-filament::button>
                @endforeach
            </div>

        </div>
    </div>

</div>
