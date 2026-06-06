<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * JSON endpoints backing the supplier-invoice modal.
 * DatabaseTransactions rolls each test back, keeping the dev DB clean.
 */
class SupplierInvoiceModalTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::where('role', 'admin')->firstOrFail();
        Setting::set('module_suppliers_enabled', '1'); // gate open (rolled back after test)
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'Prov Modal ' . uniqid()]);
    }

    /** Create invoice with KG (grams→kg) + UNIT items returns 201 JSON and persists correctly. */
    public function test_create_invoice_with_kg_and_unit_items(): void
    {
        $s = $this->supplier();

        $res = $this->actingAs($this->user)->postJson("/suppliers/{$s->id}/invoices", [
            'invoice_number' => 'A-100',
            'invoice_date'   => '2026-01-10',
            'items' => [
                ['description' => 'Carne',  'sale_unit' => 'KG',   'quantity' => 1.5, 'unit_price' => 8000],  // 1.5kg*8000=12000
                ['description' => 'Bolsas', 'sale_unit' => 'UNIT', 'quantity' => 3,   'unit_price' => 2500],  // 3*2500=7500
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('invoice.total_amount', '19500.00')
            ->assertJsonPath('invoice.balance', '19500.00');

        $inv = SupplierInvoice::where('supplier_id', $s->id)->firstOrFail();
        $this->assertEquals('PENDING', $inv->status);
        $this->assertEquals('19500.00', $inv->total_amount);

        $kg = SupplierInvoiceItem::where('supplier_invoice_id', $inv->id)->where('sale_unit', 'KG')->firstOrFail();
        $this->assertEquals('1.500', $kg->quantity);
        $this->assertEquals('12000.00', $kg->line_total);
        $this->assertEquals('1.500 kg', $kg->formatted_quantity);

        $und = SupplierInvoiceItem::where('supplier_invoice_id', $inv->id)->where('sale_unit', 'UNIT')->firstOrFail();
        $this->assertEquals('3 und', $und->formatted_quantity);
        $this->assertEquals('7500.00', $und->line_total);
    }

    /** Total-only invoice (no items) works. */
    public function test_create_total_only_invoice(): void
    {
        $s = $this->supplier();

        $res = $this->actingAs($this->user)->postJson("/suppliers/{$s->id}/invoices", [
            'invoice_date' => '2026-01-10',
            'total_amount' => 50000,
        ]);

        $res->assertStatus(201)->assertJsonPath('invoice.balance', '50000.00');
    }

    /** Missing required date → 422 with field error (modal stays open). */
    public function test_validation_error_returns_422(): void
    {
        $s = $this->supplier();

        $res = $this->actingAs($this->user)->postJson("/suppliers/{$s->id}/invoices", [
            'items' => [['description' => 'X', 'sale_unit' => 'KG', 'quantity' => 1, 'unit_price' => 1000]],
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['invoice_date']);
    }

    /** No items and no total → 422 on total_amount. */
    public function test_missing_total_and_items_returns_422(): void
    {
        $s = $this->supplier();

        $res = $this->actingAs($this->user)->postJson("/suppliers/{$s->id}/invoices", [
            'invoice_date' => '2026-01-10',
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['total_amount']);
    }

    /** Supplier-level FIFO payment via JSON returns 201. */
    public function test_supplier_level_payment_json(): void
    {
        $s = $this->supplier();
        SupplierInvoice::create(['supplier_id' => $s->id, 'invoice_date' => '2026-01-01',
            'total_amount' => 10000, 'paid_amount' => 0, 'balance' => 10000, 'status' => 'PENDING']);

        $res = $this->actingAs($this->user)->postJson("/suppliers/{$s->id}/payments", [
            'method' => 'NEQUI', 'amount' => 10000, 'submission_key' => 'modal-test-' . uniqid(),
        ]);

        $res->assertStatus(201);
        $this->assertEquals(1, SupplierPayment::where('supplier_id', $s->id)->count());
    }

    /** Invoice-linked overpayment via JSON → 422 with Spanish message. */
    public function test_invoice_payment_overpay_json(): void
    {
        $s = $this->supplier();
        $inv = SupplierInvoice::create(['supplier_id' => $s->id, 'invoice_date' => '2026-01-01',
            'total_amount' => 10000, 'paid_amount' => 0, 'balance' => 10000, 'status' => 'PENDING']);

        $res = $this->actingAs($this->user)->postJson("/supplier-invoices/{$inv->id}/payments", [
            'method' => 'CASH', 'amount' => 15000,
        ]);

        $res->assertStatus(422)->assertJsonPath('message', fn($m) => str_contains($m, 'saldo'));
    }

    /** Module disabled → routes blocked (403). */
    public function test_routes_blocked_when_module_disabled(): void
    {
        Setting::set('module_suppliers_enabled', '0');
        $s = $this->supplier();

        $this->actingAs($this->user)
            ->postJson("/suppliers/{$s->id}/invoices", ['invoice_date' => '2026-01-10', 'total_amount' => 1000])
            ->assertStatus(403);
    }
}
