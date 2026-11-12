<x-filament-panels::page>
    {{ $this->form }}

    <div class="cali-report-card">
        @include($partial, ['report' => $report, 'filters' => $filters, 'screen' => true])
    </div>
</x-filament-panels::page>
