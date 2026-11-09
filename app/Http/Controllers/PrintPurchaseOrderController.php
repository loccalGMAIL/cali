<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Versión imprimible de la orden de compra, para llevarle al proveedor.
 */
class PrintPurchaseOrderController extends Controller
{
    public function __invoke(PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('view', $purchaseOrder);

        return view('purchase-orders.print', [
            'order' => $purchaseOrder->load(['supplier', 'user']),
            'lines' => $purchaseOrder->linesByProduct(),
        ]);
    }
}
