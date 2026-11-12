<?php

namespace App\Http\Controllers;

use App\Services\Reports\MonthlyCollectionReport;
use App\Services\Reports\PaymentsReport;
use App\Services\Reports\PendingInstallmentsReport;
use App\Services\Reports\Report;
use App\Services\Reports\SalesReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Versión imprimible de un informe, con los filtros que llegan en el query string.
 * Los informes muestran deuda, costos y ganancia: son solo para administradores.
 */
class PrintReportController extends Controller
{
    /**
     * @var list<class-string<Report>>
     */
    private const REPORTS = [
        PendingInstallmentsReport::class,
        MonthlyCollectionReport::class,
        PaymentsReport::class,
        SalesReport::class,
    ];

    public function __invoke(Request $request, string $report): View
    {
        abort_unless($request->user()?->isAdmin() ?? false, 403);

        $class = collect(self::REPORTS)->first(fn (string $class): bool => $class::slug() === $report);

        abort_if($class === null, 404);

        /** @var Report $service */
        $service = app($class);
        $filters = $service->normalize($request->query());

        return view('reports.print', [
            'title' => $service->title(),
            'partial' => 'reports.partials.'.$class::slug(),
            'landscape' => $service instanceof PendingInstallmentsReport,
            'description' => $service->describe($filters),
            'filters' => $filters,
            'report' => $service->build($filters),
        ]);
    }
}
