<x-filament::section :heading="$this->product->getLabel()" :description="$this->product->description ?? null">

    @if($this->product->image)
        <img
            src="{{ \Illuminate\Support\Facades\Storage::url($this->product->image) }}"
            alt="{{ $this->product->getLabel() }}"
            style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 0.5rem; margin-bottom: 1rem;"
        />
    @endif

    @php
        $specs = array_filter([
            $this->product->cores ? ($this->product->cores . ($this->product->cores === 1 ? ' Core' : ' Cores')) : null,
            $this->product->memory ? ($this->formatSize($this->product->memory) . ' RAM') : null,
            $this->product->disk   ? ($this->formatSize($this->product->disk)   . ' Disk') : null,
            $this->product->backup_limit   ? ($this->product->backup_limit   . ' Backups')   : null,
            $this->product->database_limit ? ($this->product->database_limit . ' Databases') : null,
        ]);
    @endphp
    @if($specs)
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
            @foreach($specs as $spec)
                <span style="font-size: 0.8rem; padding: 0.2rem 0.6rem; border-radius: 999px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: inherit;">
                    {{ $spec }}
                </span>
            @endforeach
        </div>
    @endif

    <div style="margin-top: 1rem;">
        <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
            <div style="flex: 1;">
                <x-filament::input.wrapper label="Coupon Code">
                    <x-filament::input
                        type="text"
                        wire:model="couponCode"
                        wire:keydown.enter="validateCoupon"
                        placeholder="Enter coupon code (optional)"
                    />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button
                wire:click="validateCoupon"
                wire:loading.attr="disabled"
                wire:target="validateCoupon"
                color="gray"
                style="flex-shrink: 0;"
            >
                Apply
            </x-filament::button>
        </div>

        @if($this->couponValidation !== null)
            @if($this->couponValidation['valid'])
                <p style="margin-top: 0.4rem; font-size: 0.8rem; color: rgb(34 197 94); display: flex; align-items: center; gap: 0.3rem;">
                    <svg style="width: 0.9rem; height: 0.9rem; flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                    {{ $this->couponValidation['message'] }}
                </p>
            @else
                <p style="margin-top: 0.4rem; font-size: 0.8rem; color: rgb(239 68 68); display: flex; align-items: center; gap: 0.3rem;">
                    <svg style="width: 0.9rem; height: 0.9rem; flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                    {{ $this->couponValidation['message'] }}
                </p>
            @endif
        @endif
    </div>

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
