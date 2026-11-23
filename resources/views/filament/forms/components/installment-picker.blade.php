@php
    use App\Support\PaymentPreview;

    $groups = PaymentPreview::groups($get('customer_id'));
    $selected = array_map('strval', (array) $getState());
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div {{ $getExtraAttributeBag()->class(['cali-installment-picker']) }}>
        @foreach ($groups as $group)
            @php
                $first = $group['installments'][0];
                $others = array_slice($group['installments'], 1);
                $hasSelectedOther = collect($others)->contains(fn (array $installment): bool => in_array((string) $installment['id'], $selected, true));
            @endphp

            <div class="cali-installment-sale" wire:key="installment-sale-{{ $group['sale_id'] }}">
                <div class="cali-installment-sale-title">
                    <strong>{{ $group['number'] }}</strong>
                    @if ($group['products'] !== '')
                        <span class="cali-installment-sale-products">· {{ $group['products'] }}</span>
                    @endif
                </div>

                @include('filament.forms.components.installment-picker-row', ['installment' => $first, 'statePath' => $getStatePath(), 'field' => $field])

                @if ($others !== [])
                    <details class="cali-installment-more" @if ($hasSelectedOther) open @endif>
                        <summary>
                            {{ count($others) === 1 ? '1 cuota más' : count($others).' cuotas más' }}
                        </summary>

                        @foreach ($others as $installment)
                            @include('filament.forms.components.installment-picker-row', ['installment' => $installment, 'statePath' => $getStatePath(), 'field' => $field])
                        @endforeach
                    </details>
                @endif
            </div>
        @endforeach
    </div>
</x-dynamic-component>
