<?php

namespace Tests\Feature;

use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Inline invoice editing — PUT /invoices/{invoice} (admin only).
 *
 * DatabaseTransactions rolls back each test. Invoices are built directly (not
 * through SaleService) so the thermal printer is never touched. Editing itself
 * never prints, so no printer mock is needed.
 */
class InvoiceEditTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin    = User::where('role', 'admin')->firstOrFail();
        $this->customer = Customer::create([
            'name'   => 'Cliente Edit ' . uniqid(),
            'active' => true,
        ]);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────

    /**
     * @param array<int,array{name:string,unit:string,qty:float|int,price:float|int,product_id?:int|null}> $lines
     */
    private function createInvoice(array $lines, string $paid = '0', array $opts = []): Invoice
    {
        $subtotal = '0';
        foreach ($lines as $l) {
            $subtotal = bcadd($subtotal, bcmul((string) $l['qty'], (string) $l['price'], 2), 2);
        }
        $delivery = (string) ($opts['delivery_fee'] ?? '0');
        $total    = bcadd($subtotal, $delivery, 2); // fixtures use 50-COP multiples
        $balance  = bcsub($total, $paid, 2);
        $status   = bccomp($balance, '0', 2) === 0 ? 'PAID'
                  : (bccomp($paid, '0', 2) === 0 ? 'PENDING' : 'PARTIAL');

        $seq = DB::selectOne("SELECT nextval('invoice_consecutive_seq') AS val");

        $invoice = Invoice::create([
            'customer_id'        => $opts['customer_id'] ?? $this->customer->id,
            'created_by_user_id' => $this->admin->id,
            'consecutive'        => str_pad($seq->val, 7, '0', STR_PAD_LEFT),
            'consecutive_int'    => (int) $seq->val,
            'invoice_date'       => $opts['invoice_date'] ?? now()->toDateString(),
            'subtotal'           => $subtotal,
            'delivery_fee'       => $delivery,
            'total'              => $total,
            'paid_amount'        => $paid,
            'balance'            => $balance,
            'status'             => $status,
            'requires_fe'        => $opts['requires_fe'] ?? false,
            'fe_status'          => $opts['fe_status'] ?? 'NONE',
        ]);

        foreach (array_values($lines) as $i => $l) {
            InvoiceItem::create([
                'invoice_id'            => $invoice->id,
                'product_id'            => $l['product_id'] ?? null,
                'product_name_snapshot' => $l['name'],
                'sale_unit_snapshot'    => $l['unit'],
                'quantity'              => $l['qty'],
                'unit_price'            => $l['price'],
                'line_total'            => bcmul((string) $l['qty'], (string) $l['price'], 2),
                'sort_order'            => $i,
            ]);
        }

        if (bccomp($paid, '0', 2) > 0) {
            Payment::create([
                'invoice_id'            => $invoice->id,
                'method'                => 'CASH',
                'amount'                => $paid,
                'paid_at'               => now(),
                'registered_by_user_id' => $this->admin->id,
            ]);
        }

        return $invoice->load('items');
    }

    /** Turn the invoice's current items into an editable payload. */
    private function itemsPayload(Invoice $invoice): array
    {
        return $invoice->items->map(fn($it) => [
            'product_id'   => $it->product_id,
            'product_name' => $it->product_name_snapshot,
            'sale_unit'    => $it->sale_unit_snapshot,
            'quantity'     => (float) $it->quantity,
            'unit_price'   => (float) $it->unit_price,
        ])->values()->all();
    }

    private function putEdit(Invoice $invoice, array $data)
    {
        return $this->actingAs($this->admin)->put("/invoices/{$invoice->id}", array_merge([
            'customer_id' => $this->customer->id,
        ], $data));
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_edit_quantity_recomputes_totals(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ]);

        $items = $this->itemsPayload($invoice);
        $items[0]['quantity'] = 2;

        $this->putEdit($invoice, ['items' => $items])->assertRedirect("/invoices/{$invoice->id}");

        $invoice->refresh();
        $this->assertEquals('40000.00', $invoice->subtotal);
        $this->assertEquals('40000.00', $invoice->total);
        $this->assertEquals('40000.00', $invoice->balance);
        $this->assertSame('PENDING', $invoice->status);
        $this->assertEquals('2.000', $invoice->items->first()->quantity);
        $this->assertEquals('40000.00', $invoice->items->first()->line_total);
    }

    public function test_edit_price_recomputes_totals(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Chorizo', 'unit' => 'UNIT', 'qty' => 2, 'price' => 5000],
        ]);

        $items = $this->itemsPayload($invoice);
        $items[0]['unit_price'] = 6000;

        $this->putEdit($invoice, ['items' => $items])->assertRedirect();

        $invoice->refresh();
        $this->assertEquals('12000.00', $invoice->total);
        $this->assertEquals('6000.00', $invoice->items->first()->unit_price);
    }

    public function test_edit_date(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Costilla', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ]);

        $this->putEdit($invoice, [
            'items'        => $this->itemsPayload($invoice),
            'invoice_date' => '2026-01-15',
        ])->assertRedirect();

        $this->assertEquals('2026-01-15', $invoice->refresh()->invoice_date->format('Y-m-d'));
    }

    public function test_change_product_updates_snapshot(): void
    {
        $productA = Product::create(['name' => 'Prod A ' . uniqid(), 'sale_unit' => 'KG', 'base_price' => 20000, 'active' => true]);
        $productB = Product::create(['name' => 'Prod B ' . uniqid(), 'sale_unit' => 'KG', 'base_price' => 30000, 'active' => true]);

        $invoice = $this->createInvoice([
            ['name' => $productA->name, 'unit' => 'KG', 'qty' => 1, 'price' => 20000, 'product_id' => $productA->id],
        ]);

        $this->putEdit($invoice, ['items' => [[
            'product_id'   => $productB->id,
            'product_name' => $productB->name,
            'sale_unit'    => 'KG',
            'quantity'     => 1,
            'unit_price'   => 30000,
        ]]])->assertRedirect();

        $invoice->refresh();
        $item = $invoice->items->first();
        $this->assertSame($productB->id, $item->product_id);
        $this->assertSame($productB->name, $item->product_name_snapshot);
        $this->assertEquals('30000.00', $invoice->total);
    }

    public function test_add_line(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ]);

        $items   = $this->itemsPayload($invoice);
        $items[] = ['product_name' => 'Bolsa', 'sale_unit' => 'UNIT', 'quantity' => 2, 'unit_price' => 500];

        $this->putEdit($invoice, ['items' => $items])->assertRedirect();

        $invoice->refresh();
        $this->assertCount(2, $invoice->items);
        $this->assertEquals('21000.00', $invoice->total);
    }

    public function test_remove_line(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Lomo',    'unit' => 'KG',   'qty' => 1, 'price' => 20000],
            ['name' => 'Chorizo', 'unit' => 'UNIT', 'qty' => 2, 'price' => 5000],
        ]);

        $items = $this->itemsPayload($invoice);
        unset($items[1]);

        $this->putEdit($invoice, ['items' => array_values($items)])->assertRedirect();

        $invoice->refresh();
        $this->assertCount(1, $invoice->items);
        $this->assertEquals('20000.00', $invoice->total);
    }

    public function test_delivery_fee_changes_total(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ]);

        $this->putEdit($invoice, [
            'items'        => $this->itemsPayload($invoice),
            'delivery_fee' => 5000,
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertEquals('5000.00', $invoice->delivery_fee);
        $this->assertEquals('25000.00', $invoice->total);
    }

    public function test_change_customer(): void
    {
        $other   = Customer::create(['name' => 'Otro Cliente ' . uniqid(), 'active' => true]);
        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ]);

        $this->putEdit($invoice, [
            'customer_id' => $other->id,
            'items'       => $this->itemsPayload($invoice),
        ])->assertRedirect();

        $this->assertSame($other->id, $invoice->refresh()->customer_id);
    }

    public function test_reducing_total_below_paid_refunds_to_credit(): void
    {
        $this->customer->update(['credit_balance' => '0']);
        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ], paid: '20000'); // fully paid

        $this->assertSame('PAID', $invoice->status);

        $items = $this->itemsPayload($invoice);
        $items[0]['quantity'] = 0.5; // 0.5 kg × 20.000 = 10.000

        $this->putEdit($invoice, ['items' => $items])->assertRedirect();

        $invoice->refresh();
        $this->customer->refresh();

        $this->assertEquals('10000.00', $invoice->total);
        $this->assertEquals('10000.00', $invoice->paid_amount);
        $this->assertEquals('0.00', $invoice->balance);
        $this->assertSame('PAID', $invoice->status);
        $this->assertEquals('10000.00', $this->customer->credit_balance);

        $move = CreditMovement::where('invoice_id', $invoice->id)
            ->where('type', 'REFUND_FROM_EDIT')->first();
        $this->assertNotNull($move);
        $this->assertEquals('10000.00', $move->amount);
    }

    public function test_partial_payment_status_recomputed(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ], paid: '20000'); // PAID

        $items = $this->itemsPayload($invoice);
        $items[0]['quantity'] = 2; // total 40.000, paid stays 20.000 → PARTIAL

        $this->putEdit($invoice, ['items' => $items])->assertRedirect();

        $invoice->refresh();
        $this->assertEquals('40000.00', $invoice->total);
        $this->assertEquals('20000.00', $invoice->paid_amount);
        $this->assertEquals('20000.00', $invoice->balance);
        $this->assertSame('PARTIAL', $invoice->status);
    }

    public function test_invalid_quantity_rejected(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ]);

        $items = $this->itemsPayload($invoice);
        $items[0]['quantity'] = 0;

        $this->putEdit($invoice, ['items' => $items])->assertSessionHasErrors('items.0.quantity');
        $this->assertEquals('20000.00', $invoice->refresh()->total); // unchanged
    }

    public function test_invalid_price_rejected(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ]);

        $items = $this->itemsPayload($invoice);
        $items[0]['unit_price'] = -100;

        $this->putEdit($invoice, ['items' => $items])->assertSessionHasErrors('items.0.unit_price');
    }

    public function test_empty_items_rejected(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ]);

        $this->putEdit($invoice, ['items' => []])->assertSessionHasErrors('items');
        $this->assertCount(1, $invoice->refresh()->items);
    }

    public function test_fe_issued_invoice_cannot_be_edited(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ], opts: ['requires_fe' => true, 'fe_status' => 'ISSUED']);

        $items = $this->itemsPayload($invoice);
        $items[0]['quantity'] = 5;

        $this->putEdit($invoice, ['items' => $items])->assertSessionHasErrors('edit');
        $this->assertEquals('20000.00', $invoice->refresh()->total); // unchanged
    }

    public function test_non_admin_cannot_edit(): void
    {
        $cashier = User::firstOrCreate(
            ['email' => 'cashier_edit@minegocio.test'],
            ['name' => 'Cashier Edit', 'password' => bcrypt('password'), 'role' => 'cashier', 'active' => true]
        );

        $invoice = $this->createInvoice([
            ['name' => 'Lomo', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ]);

        $this->actingAs($cashier)
            ->put("/invoices/{$invoice->id}", [
                'customer_id' => $this->customer->id,
                'items'       => $this->itemsPayload($invoice),
            ])
            ->assertStatus(403);
    }

    public function test_show_renders_editor_for_admin_and_keeps_readonly_intact(): void
    {
        $invoice = $this->createInvoice([
            ['name' => 'Producto Detalle', 'unit' => 'KG', 'qty' => 1, 'price' => 20000],
        ]);

        $html = $this->actingAs($this->admin)
            ->get("/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->getContent();

        // Read-only content still present…
        $this->assertStringContainsString('Factura #' . $invoice->consecutive, $html);
        $this->assertStringContainsString('Producto Detalle', $html);
        // …and the admin inline editor is wired up.
        $this->assertStringContainsString('invoiceEditor()', $html);
        $this->assertStringContainsString('Guardar cambios', $html);
    }
}
