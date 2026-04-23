<x-filament::section :heading="$this->product->getLabel()" :description="$this->product->description ?? null">

    @if($this->product->image)
        <img
            src="{{ \Illuminate\Support\Facades\Storage::url($this->product->image) }}"
            alt="{{ $this->product->getLabel() }}"
            style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 0.5rem; margin-bottom: 1rem;"
        />
    @endif

    @php
        $availableExpansions = $this->product->packExpansions
            ->filter(fn ($pe) => $pe->is_enabled && $pe->expansion->isAvailable());
        $stockAvailable = $this->product->availableStock();
        $isAvailable    = $this->product->isAvailable();
    @endphp

    @if($availableExpansions->isNotEmpty())
        <div style="margin-top: 0.75rem;">
            <p style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(156 163 175); margin-bottom: 0.5rem;">Add-ons</p>
            @foreach($availableExpansions as $pe)
                @php $checked = in_array($pe->id, $this->selectedExpansionIds, true); @endphp
                <label
                    wire:click.prevent="toggleExpansion({{ $pe->id }})"
                    style="display: flex; align-items: flex-start; gap: 0.6rem; margin-bottom: 0.4rem; cursor: pointer; padding: 0.5rem 0.6rem; border-radius: 0.4rem; border: 1px solid {{ $checked ? 'rgb(99 102 241)' : 'rgba(255,255,255,0.08)' }}; background: {{ $checked ? 'rgba(99,102,241,0.08)' : 'transparent' }};"
                >
                    <input type="checkbox" {{ $checked ? 'checked' : '' }} style="margin-top: 0.15rem; flex-shrink: 0;" readonly />
                    <span style="font-size: 0.85rem; line-height: 1.4;">
                        <span style="font-weight: 600;">{{ $pe->expansion->name }}</span>
                        <span style="margin-left: 0.4rem; color: rgb(99 102 241);">{{ $pe->formatEffectivePrice() }}</span>
                        <br>
                        <span style="font-size: 0.75rem; color: rgb(156 163 175);">{{ $pe->expansion->boostSummary() }}</span>
                        @if($pe->expansion->stock !== null)
                            <span style="font-size: 0.7rem; color: rgb(156 163 175); margin-left: 0.3rem;">· {{ $pe->expansion->availableStock() }} left</span>
                        @endif
                    </span>
                </label>
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

    @if(!$this->product->is_enabled)
        <p style="margin-top: 1rem; font-size: 0.85rem; color: rgb(156 163 175);">
            This pack is currently unavailable.
        </p>
    @elseif($this->product->force_out_of_stock || ($stockAvailable !== null && $stockAvailable <= 0))
        <p style="margin-top: 1rem; font-size: 0.85rem; color: rgb(239 68 68);">
            Out of stock
        </p>
    @else
        @if($stockAvailable !== null)
            <p style="margin-top: 0.75rem; font-size: 0.8rem; color: rgb(156 163 175);">
                {{ $stockAvailable }} slot{{ $stockAvailable === 1 ? '' : 's' }} remaining
            </p>
        @endif

        <div style="margin-top: 1rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem;">
            @foreach($this->product->prices as $price)
                <div style="border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.1); padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
                    <p style="font-size: 0.9rem; font-weight: 700; margin: 0;" class="text-gray-950 dark:text-white">
                        {{ $price->name ?: 'Standard' }}
                    </p>

                    @php
                        $binary = (bool) config('panel.use_binary_prefix');
                        $specs = array_filter([
                            $price->cores ? ($price->cores . ($price->cores === 1 ? ' Core' : ' Cores')) : null,
                            $price->memory ? ($this->formatSize($price->memory) . ' RAM') : null,
                            $price->disk   ? ($this->formatSize($price->disk)   . ' Disk') : null,
                            $price->backup_limit   ? ($price->backup_limit   . ' Backups')   : null,
                            $price->database_limit ? ($price->database_limit . ' Databases') : null,
                        ]);
                    @endphp
                    @if($specs)
                        <div style="display: flex; flex-wrap: wrap; gap: 0.3rem;">
                            @foreach($specs as $spec)
                                <span style="font-size: 0.72rem; padding: 0.15rem 0.5rem; border-radius: 999px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: inherit;">
                                    {{ $spec }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <div style="margin-top: auto; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.07);">
                        <p style="font-size: 1rem; font-weight: 700; margin: 0 0 0.1rem;" class="text-gray-950 dark:text-white">
                            {{ $price->formatCost() }}
                            @if($price->renewable)
                                <span style="font-size: 0.7rem; font-weight: 400; color: rgb(156 163 175);">
                                    / {{ $price->interval_value > 1 ? $price->interval_value . ' ' : '' }}{{ $price->interval_type->getLabel() }}
                                </span>
                            @endif
                        </p>
                        @if($price->hasTrial())
                            <p style="font-size: 0.72rem; color: rgb(34 197 94); margin: 0 0 0.4rem;">
                                {{ $price->trial_days }}-day free trial
                            </p>
                        @endif

                        <x-filament::button
                            wire:click="placeOrder({{ $price->id }})"
                            wire:loading.attr="disabled"
                            wire:target="placeOrder({{ $price->id }})"
                            style="width: 100%;"
                            size="sm"
                        >
                            Get Started
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</x-filament::section>
