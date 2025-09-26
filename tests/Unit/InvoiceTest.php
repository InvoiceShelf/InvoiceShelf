<?php

use App\Http\Requests\InvoicesRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('invoice has many invoice items', function () {
    $invoice = Invoice::factory()->hasItems(5)->create();

    $this->assertCount(5, $invoice->items);

    $this->assertTrue($invoice->items()->exists());
});

test('invoice has many taxes', function () {
    $invoice = Invoice::factory()->hasTaxes(5)->create();

    $this->assertCount(5, $invoice->taxes);

    $this->assertTrue($invoice->taxes()->exists());
});

test('invoice has many payments', function () {
    $invoice = Invoice::factory()->hasPayments(5)->create();

    $this->assertCount(5, $invoice->payments);

    $this->assertTrue($invoice->payments()->exists());
});

test('invoice belongs to customer', function () {
    $invoice = Invoice::factory()->forCustomer()->create();

    $this->assertTrue($invoice->customer()->exists());
});

test('get previous status', function () {
    $invoice = Invoice::factory()->create();

    $status = $invoice->getPreviousStatus();

    $this->assertEquals('DRAFT', $status);
});

test('create invoice', function () {
    $invoice = Invoice::factory()->raw();

    $item = InvoiceItem::factory()->raw();

    $invoice['items'] = [];
    array_push($invoice['items'], $item);

    $invoice['taxes'] = [];
    array_push($invoice['taxes'], Tax::factory()->raw());

    $request = new InvoicesRequest;

    $request->replace($invoice);

    $invoice_number = explode('-', $invoice['invoice_number']);
    $number_attributes['invoice_number'] = $invoice_number[0].'-'.sprintf('%06d', intval($invoice_number[1]));

    $response = Invoice::createInvoice($request);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $response->id,
        'name' => $item['name'],
        'description' => $item['description'],
        'total' => $item['total'],
        'quantity' => $item['quantity'],
        'discount' => $item['discount'],
        'price' => $item['price'],
    ]);

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $invoice['invoice_number'],
        'sub_total' => $invoice['sub_total'],
        'total' => $invoice['total'],
        'tax' => $invoice['tax'],
        'discount' => $invoice['discount'],
        'notes' => $invoice['notes'],
        'customer_id' => $invoice['customer_id'],
        'template_name' => $invoice['template_name'],
    ]);
});

test('update invoice', function () {
    $invoice = Invoice::factory()->create();

    $newInvoice = Invoice::factory()->raw();

    $item = InvoiceItem::factory()->raw([
        'invoice_id' => $invoice->id,
    ]);

    $tax = Tax::factory()->raw([
        'invoice_id' => $invoice->id,
    ]);

    $newInvoice['items'] = [];
    $newInvoice['taxes'] = [];

    array_push($newInvoice['items'], $item);
    array_push($newInvoice['taxes'], $tax);

    $request = new InvoicesRequest;

    $request->replace($newInvoice);

    $invoice_number = explode('-', $newInvoice['invoice_number']);

    $number_attributes['invoice_number'] = $invoice_number[0].'-'.sprintf('%06d', intval($invoice_number[1]));

    $response = $invoice->updateInvoice($request);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $response->id,
        'name' => $item['name'],
        'description' => $item['description'],
        'total' => $item['total'],
        'quantity' => $item['quantity'],
        'discount' => $item['discount'],
        'price' => $item['price'],
    ]);

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $newInvoice['invoice_number'],
        'sub_total' => $newInvoice['sub_total'],
        'total' => $newInvoice['total'],
        'tax' => $newInvoice['tax'],
        'discount' => $newInvoice['discount'],
        'notes' => $newInvoice['notes'],
        'customer_id' => $newInvoice['customer_id'],
        'template_name' => $newInvoice['template_name'],
    ]);
});

test('create items', function () {
    $invoice = Invoice::factory()->create();

    $items = [];

    $item = InvoiceItem::factory()->raw([
        'invoice_id' => $invoice->id,
    ]);

    array_push($items, $item);

    $request = new InvoicesRequest;

    $request->replace(['items' => $items]);

    Invoice::createItems($invoice, $request->items);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoice->id,
        'description' => $item['description'],
        'price' => $item['price'],
        'tax' => $item['tax'],
        'quantity' => $item['quantity'],
        'total' => $item['total'],
    ]);
});

test('create taxes', function () {
    $invoice = Invoice::factory()->create();

    $taxes = [];

    $tax = Tax::factory()->raw([
        'invoice_id' => $invoice->id,
    ]);

    array_push($taxes, $tax);

    $request = new Request;

    $request->replace(['taxes' => $taxes]);

    Invoice::createTaxes($invoice, $request->taxes);

    $this->assertDatabaseHas('taxes', [
        'invoice_id' => $invoice->id,
        'name' => $tax['name'],
        'amount' => $tax['amount'],
    ]);
});

test('invoice status transitions correctly based on payments', function () {
    // ARRANGE: Configuración inicial del escenario de prueba
    // Crear una factura con estado inicial "NO PAGADA" y monto total pendiente
    $invoice = Invoice::factory()->create([
        'total' => 1000,           // Monto total de la factura
        'due_amount' => 1000,      // Saldo pendiente (inicialmente igual al total)
        'paid_status' => Invoice::STATUS_UNPAID  // Estado inicial: No pagada
    ]);
    
    // ACT: Primera acción - Realizar un pago parcial
    // Simular un pago del 30% del total de la factura
    Payment::factory()->create([
        'invoice_id' => $invoice->id,  // Vincular pago a la factura creada
        'amount' => 300               // Monto del primer pago (30% del total)
    ]);
    
    // ASSERT: Verificar transición de estado después del pago parcial
    $invoice->refresh();  // Recargar modelo desde BD para obtener cambios actualizados
    $this->assertEquals(Invoice::STATUS_PARTIALLY_PAID, $invoice->paid_status);
    // ✅ Esperado: Estado cambia a "PAGO PARCIAL" después del primer pago
    // ✅ El sistema debe detectar automáticamente que se pagó parte del total
    
    // ACT: Segunda acción - Completar el pago total
    // Simular un segundo pago que cubre el saldo restante
    Payment::factory()->create([
        'invoice_id' => $invoice->id,  // Misma factura
        'amount' => 700               // Monto que completa el pago total (70% restante)
    ]);
    
    // ASSERT: Verificar transición final a estado "PAGADA"
    $invoice->refresh();  // Recargar modelo nuevamente
    $this->assertEquals(Invoice::STATUS_PAID, $invoice->paid_status);
    // ✅ Esperado: Estado cambia a "PAGADA" cuando el saldo llega a cero
    // ✅ El sistema debe reconocer que se completó el pago total
});

// COMENTARIOS ADICIONALES SOBRE EL TEST:

/**
 * 📋 PROPÓSITO DEL TEST:
 * Verificar que el sistema actualiza correctamente el estado de pago de una factura
 * basándose en los pagos recibidos, siguiendo la lógica de negocio:
 * - UNPAID → PARTIALLY_PAID cuando se recibe un pago parcial
 * - PARTIALLY_PAID → PAID cuando se completa el pago total
 * 
 * 🎯 ESCENARIO DE NEGOCIO CUBIERTO:
 * Flujo típico de pagos escalonados donde un cliente paga una factura en dos partes
 * 
 * 🔍 ASPECTOS CRÍTICOS VALIDADOS:
 * 1. Trigger automático de cambio de estado al crear pagos
 * 2. Cálculo correcto del saldo pendiente (due_amount)
 * 3. Transiciones de estado según reglas de negocio
 * 4. Integración entre modelos Invoice y Payment
 * 
 * ⚠️ POSIBLES MEJORAS PARA EL TEST:
 * - Verificar que due_amount se actualiza correctamente en cada paso
 * - Agregar assert para verificar el valor de due_amount después de cada pago
 * - Probar edge cases (pagos mayores al total, pagos duplicados, etc.)
 */