<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;

/**
 * Nombres en español para mostrar el registro de actividad.
 */
class ActivityLabels
{
    /**
     * @var array<class-string, string>
     */
    private const SUBJECTS = [
        Customer::class => 'Cliente',
        Supplier::class => 'Proveedor',
        Product::class => 'Producto',
        FinancingPlan::class => 'Plan de financiación',
        User::class => 'Usuario',
        Sale::class => 'Venta',
        PurchaseOrder::class => 'Orden de compra',
        Purchase::class => 'Compra',
        Payment::class => 'Cobro',
    ];

    /**
     * @var array<string, string>
     */
    private const ATTRIBUTES = [
        'name' => 'Nombre',
        'email' => 'Email',
        'password' => 'Contraseña',
        'role' => 'Rol',
        'active' => 'Activo',
        'document_number' => 'DNI / CUIT',
        'phone' => 'Teléfono',
        'address' => 'Dirección',
        'city' => 'Localidad',
        'notes' => 'Notas',
        'cuit' => 'CUIT',
        'sku' => 'Código',
        'brand' => 'Marca',
        'description' => 'Descripción',
        'supplier_id' => 'Proveedor habitual (ID)',
        'last_cost' => 'Último costo',
        'freight' => 'Flete',
        'installments_count' => 'Cantidad de cuotas',
        'total_cost' => 'Costo total',
        'total_freight' => 'Flete total',
        'google_id' => 'ID de Google',
        'reason' => 'Motivo',
        'total' => 'Total',
        'amount' => 'Monto',
        'method' => 'Medio de pago',
        'down_payment' => 'Anticipo',
        'invoice_number' => 'Número de factura',
        'items' => 'Ítems',
        'installments' => 'Cuotas imputadas',
        'seller_id' => 'Vendedor (ID)',
    ];

    /**
     * "Cliente", "Venta"… o el nombre de la clase si no está mapeada.
     */
    public static function subject(?string $type): string
    {
        return $type === null ? '' : (self::SUBJECTS[$type] ?? class_basename($type));
    }

    public static function attribute(string $attribute): string
    {
        return self::ATTRIBUTES[$attribute] ?? $attribute;
    }

    /**
     * @return array<class-string, string>
     */
    public static function subjects(): array
    {
        return self::SUBJECTS;
    }
}
