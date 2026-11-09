<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('seller_id')->constrained('users');
            $table->date('date');
            $table->foreignId('financing_plan_id')->constrained();
            // Snapshot del plan: un cambio posterior en los planes no afecta ventas existentes.
            $table->unsignedSmallInteger('installments_count');
            // Total = costo + margen de los ítems. Lo financiado (total - anticipo) se divide en cuotas iguales.
            $table->decimal('total', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->decimal('down_payment', 12, 2)->default(0);
            $table->decimal('financed_amount', 12, 2);
            $table->date('first_due_date');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            // Una venta anulada no se borra: queda como historial con quién, cuándo y por qué.
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
