<x-filament::section :heading="$this->product->getLabel()" :description="$this->product->description ?? null">

    {{-- Product image --}}
    @if($this->product->image)
        <img
            src="{{ \Illuminate\Support\Facades\Storage::url($this->product->image) }}"
            alt="{{ $this->product->getLabel() }}"
            style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 0.5rem; margin-bottom: 1rem;"
        />
    @endif

    {{-- Spec entries (CPU, RAM, disk) --}}
    {{ $this->content }}

    {{-- Coupon code --}}
    <div style="margin-top: 1rem;">
        <x-filament::input.wrapper label="Coupon Code">
            <x-filament::input
                type="text"
                wire:model="couponCode"
                placeholder="Enter coupon code (optional)"
            />
        </x-filament::input.wrapper>
    </div>

    {{-- Order buttons --}}
    <div style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
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

</x-filament::section>
