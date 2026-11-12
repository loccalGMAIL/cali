<x-filament-panels::page>
    {{ $this->form }}

    {{ $this->table }}

    @if (view()->exists($summary))
        <div class="cali-report-card">
            @include($summary, ['report' => $report, 'filters' => $filters])
        </div>
    @endif
</x-filament-panels::page>
