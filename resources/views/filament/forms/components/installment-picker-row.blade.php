<label class="cali-installment-row" wire:key="installment-{{ $installment['id'] }}">
    <input
        type="checkbox"
        value="{{ $installment['id'] }}"
        class="fi-checkbox-input"
        {{ $field->applyStateBindingModifiers('wire:model') }}="{{ $statePath }}"
    />
    <span>
        <span class="cali-installment-row-title">Cuota {{ $installment['number'] }} de {{ $installment['total'] }}</span>
        <span class="cali-installment-row-detail">{{ $installment['description'] }}</span>
    </span>
</label>
