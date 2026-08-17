<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ThermalPrinterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Per-customer default domicilio ("Valor de domicilio").
 *
 * The customer stores a *template* (customers.default_delivery_fee, NULL = not
 * configured). On sale it is copied into invoices.delivery_fee — the existing,
 * already-editable per-invoice charge — so:
 *   - editing the customer template never touches past invoices, and
 *   - editing an invoice's fee never touches the customer template.
 *
 * The auto-load itself is client-side (Alpine): these tests prove the value is
 * persisted, exposed to the sales screen (search endpoint), and copied with the
 * right independence server-side. The printer is mocked so no bytes are sent.
 */
class DefaultDeliveryFeeTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('role', 'admin')->firstOrFail();

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->andReturnNull();
        });
    }

    private function salePayload(Customer $customer, $deliveryFee): array
    {
        return [
            'customer_id'    => $customer->id,
            'items'          => [
                ['product_name' => 'Carne', 'sale_unit' => 'KG', 'quantity' => 1, 'unit_price' => 50000],
            ],
            'delivery_fee'   => $deliveryFee,
            'payments'       => [],
            'requires_fe'    => 0,
            'submission_key' => (string) Str::uuid(),
        ];
    }

    // ── Customer persistence ──────────────────────────────────────────────

    public function test_store_persists_default_delivery_fee(): void
    {
        $this->actingAs($this->admin)->post('/customers', [
            'name'                 => 'Cliente Domicilio ' . uniqid(),
            'default_delivery_fee' => 8000,
        ])->assertRedirect();

        $customer = Customer::latest('id')->firstOrFail();
        $this->assertEquals('8000.00', $customer->default_delivery_fee);
    }

    public function test_empty_default_delivery_fee_is_stored_as_null(): void
    {
        $this->actingAs($this->admin)->post('/customers', [
            'name'                 => 'Cliente Sin Domicilio ' . uniqid(),
            'default_delivery_fee' => '',
        ])->assertRedirect();

        $this->assertNull(Customer::latest('id')->firstOrFail()->default_delivery_fee);
    }

    public function test_negative_default_delivery_fee_rejected(): void
    {
        $this->actingAs($this->admin)->post('/customers', [
            'name'                 => 'Cliente Negativo ' . uniqid(),
            'default_delivery_fee' => -100,
        ])->assertSessionHasErrors('default_delivery_fee');
    }

    public function test_zero_default_delivery_fee_is_allowed(): void
    {
        $this->actingAs($this->admin)->post('/customers', [
            'name'                 => 'Cliente Cero ' . uniqid(),
            'default_delivery_fee' => 0,
        ])->assertRedirect();

        $this->assertEquals('0.00', Customer::latest('id')->firstOrFail()->default_delivery_fee);
    }

    public function test_update_changes_default_delivery_fee(): void
    {
        $customer = Customer::create(['name' => 'Cliente Upd ' . uniqid(), 'active' => true, 'default_delivery_fee' => 8000]);

        $this->actingAs($this->admin)->put("/customers/{$customer->id}", [
            'name'                 => $customer->name,
            'active'               => 1,
            'default_delivery_fee' => 12000,
        ])->assertRedirect();

        $this->assertEquals('12000.00', $customer->refresh()->default_delivery_fee);
    }

    // ── Exposure to the sales screen ──────────────────────────────────────

    public function test_search_endpoint_exposes_default_delivery_fee(): void
    {
        $name     = 'Cliente Buscable ' . uniqid();
        Customer::create(['name' => $name, 'active' => true, 'default_delivery_fee' => 8000]);

        $this->actingAs($this->admin)
            ->getJson('/customers/search?q=' . urlencode($name))
            ->assertOk()
            ->assertJsonFragment(['default_delivery_fee' => '8000.00']);
    }

    // ── Copy semantics on sale ────────────────────────────────────────────

    public function test_sale_stores_delivery_fee_as_an_independent_copy(): void
    {
        $customer = Customer::create(['name' => 'Cliente Copia ' . uniqid(), 'active' => true, 'default_delivery_fee' => 8000]);

        // The cashier submits the auto-loaded 8.000 (client-side copied value).
        $this->actingAs($this->admin)->post('/sales', $this->salePayload($customer, 8000))->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertEquals('8000.00', $invoice->delivery_fee);
        $this->assertEquals('58000.00', $invoice->total); // 50.000 + 8.000

        // Changing the customer template afterwards must NOT touch the invoice.
        $customer->update(['default_delivery_fee' => 12000]);
        $this->assertEquals('8000.00', $invoice->refresh()->delivery_fee);
        $this->assertEquals('12000.00', $customer->refresh()->default_delivery_fee);
    }

    public function test_invoice_can_override_the_default_without_touching_the_customer(): void
    {
        $customer = Customer::create(['name' => 'Cliente Override ' . uniqid(), 'active' => true, 'default_delivery_fee' => 8000]);

        // Cashier bumps the fee to 10.000 on this invoice only.
        $this->actingAs($this->admin)->post('/sales', $this->salePayload($customer, 10000))->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertEquals('10000.00', $invoice->delivery_fee);
        $this->assertEquals('8000.00', $customer->refresh()->default_delivery_fee); // template unchanged
    }

    public function test_zero_fee_on_invoice_is_respected(): void
    {
        $customer = Customer::create(['name' => 'Cliente CeroFactura ' . uniqid(), 'active' => true, 'default_delivery_fee' => 8000]);

        $this->actingAs($this->admin)->post('/sales', $this->salePayload($customer, 0))->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertEquals('0.00', $invoice->delivery_fee);
        $this->assertEquals('50000.00', $invoice->total);
    }

    public function test_customer_without_default_produces_no_fee(): void
    {
        $customer = Customer::create(['name' => 'Cliente NullFee ' . uniqid(), 'active' => true]);
        $this->assertNull($customer->default_delivery_fee);

        // No delivery_fee submitted → existing behaviour (0).
        $payload = $this->salePayload($customer, null);
        unset($payload['delivery_fee']);
        $this->actingAs($this->admin)->post('/sales', $payload)->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertEquals('0.00', $invoice->delivery_fee);
        $this->assertEquals('50000.00', $invoice->total);
    }
}
