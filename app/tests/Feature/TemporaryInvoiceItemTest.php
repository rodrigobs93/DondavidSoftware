<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\ThermalPrinterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Temporary (off-catalog) invoice lines — items typed straight into the sale
 * with no `products` row behind them. They travel the same path as catalog
 * items and are stored as InvoiceItem rows with product_id = null.
 *
 * The printer is mocked so no bytes reach the spooler.
 */
class TemporaryInvoiceItemTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin    = User::where('role', 'admin')->firstOrFail();
        $this->customer = Customer::create([
            'name'   => 'Cliente Temp ' . uniqid(),
            'active' => true,
        ]);

        // Sales print synchronously inside the request — never hit the spooler.
        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->andReturnNull();
        });
    }

    private function payload(array $items, array $payments = []): array
    {
        return [
            'customer_id'    => $this->customer->id,
            'items'          => $items,
            'payments'       => $payments,
            'requires_fe'    => 0,
            'submission_key' => (string) \Illuminate\Support\Str::uuid(),
        ];
    }

    public function test_sales_page_offers_the_temporary_product_form_and_no_generic_chip(): void
    {
        $html = $this->actingAs($this->admin)
            ->get('/sales/new')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('+ Temporal', $html);
        $this->assertStringContainsString('Producto temporal', $html);
        // The quick GENÉRICO chip was removed; the fallback id the hidden field
        // uses when no customer is picked must still be emitted.
        $this->assertStringNotContainsString('is_generic: true', $html);
        $this->assertStringContainsString('__genericId', $html);
    }

    public function test_sale_with_temporary_unit_item_stores_null_product_id(): void
    {
        $productsBefore = Product::withTrashed()->count();

        $res = $this->actingAs($this->admin)->post('/sales', $this->payload([
            [
                'product_name' => 'Pilas AA para el cliente',
                'sale_unit'    => 'UNIT',
                'quantity'     => 3,
                'unit_price'   => 4000,
            ],
        ]));

        $res->assertRedirect();
        $invoice = Invoice::latest('id')->firstOrFail();

        $this->assertCount(1, $invoice->items);
        $item = $invoice->items->first();
        $this->assertNull($item->product_id);
        $this->assertSame('Pilas AA para el cliente', $item->product_name_snapshot);
        $this->assertSame('UNIT', $item->sale_unit_snapshot);
        $this->assertEquals('12000.00', $item->line_total);

        // 3 × 4.000 = 12.000 — already a multiple of 50, no rounding applied
        $this->assertEquals('12000.00', $invoice->subtotal);
        $this->assertEquals('12000.00', $invoice->total);
        $this->assertSame('PENDING', $invoice->status);

        // No catalog row was created
        $this->assertEquals($productsBefore, Product::withTrashed()->count());
    }

    public function test_temporary_kg_item_participates_in_totals(): void
    {
        $this->actingAs($this->admin)->post('/sales', $this->payload([
            [
                'product_name' => 'Carne de favor por kilo',
                'sale_unit'    => 'KG',
                'quantity'     => 1.250,
                'unit_price'   => 20000,
            ],
        ]))->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $item    = $invoice->items->first();

        $this->assertNull($item->product_id);
        $this->assertEquals('1.250', $item->quantity);
        $this->assertEquals('25000.00', $item->line_total);   // 1.25 kg × 20.000
        $this->assertEquals('25000.00', $invoice->total);
    }

    public function test_mixed_catalog_and_temporary_items_share_the_same_totals(): void
    {
        $catalog = Product::create([
            'name'       => 'Costilla Temp ' . uniqid(),
            'sale_unit'  => 'KG',
            'base_price' => 16000,
            'active'     => true,
        ]);

        $this->actingAs($this->admin)->post('/sales', $this->payload(
            items: [
                [
                    'product_id'   => $catalog->id,
                    'product_name' => $catalog->name,
                    'sale_unit'    => 'KG',
                    'quantity'     => 1,
                    'unit_price'   => 16000,
                ],
                [
                    'product_name' => 'Bolsa extra temporal',
                    'sale_unit'    => 'UNIT',
                    'quantity'     => 2,
                    'unit_price'   => 500,
                ],
            ],
            payments: [
                ['method' => 'CASH', 'amount' => 17000],
            ],
        ))->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();

        $this->assertEquals('17000.00', $invoice->subtotal);   // 16.000 + 1.000
        $this->assertEquals('17000.00', $invoice->total);
        $this->assertEquals('17000.00', $invoice->paid_amount);
        $this->assertEquals('0.00', $invoice->balance);
        $this->assertSame('PAID', $invoice->status);

        $byName = $invoice->items->keyBy('product_name_snapshot');
        $this->assertSame($catalog->id, $byName[$catalog->name]->product_id);
        $this->assertNull($byName['Bolsa extra temporal']->product_id);
    }

    public function test_empty_product_id_string_is_normalized_to_null(): void
    {
        // Defensive: a browser that still submits the hidden field as "" must
        // not push an empty string into the invoice_items FK column.
        $this->actingAs($this->admin)->post('/sales', $this->payload([
            [
                'product_id'   => '',
                'product_name' => 'Item con product_id vacio',
                'sale_unit'    => 'UNIT',
                'quantity'     => 1,
                'unit_price'   => 1000,
            ],
        ]))->assertRedirect();

        $this->assertNull(Invoice::latest('id')->firstOrFail()->items->first()->product_id);
    }

    public function test_temporary_item_still_requires_a_name(): void
    {
        $this->actingAs($this->admin)
            ->post('/sales', $this->payload([
                ['sale_unit' => 'UNIT', 'quantity' => 1, 'unit_price' => 1000],
            ]))
            ->assertSessionHasErrors('items.0.product_name');
    }

    public function test_temporary_item_appears_on_the_printed_ticket(): void
    {
        // Re-bind with an assertion on the rendered bytes for this test only.
        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with(
                \Mockery::on(fn ($bytes) => str_contains($bytes, 'Encargo especial'))
            );
        });

        $this->actingAs($this->admin)->post('/sales', $this->payload([
            [
                'product_name' => 'Encargo especial',
                'sale_unit'    => 'UNIT',
                'quantity'     => 1,
                'unit_price'   => 5000,
            ],
        ]))->assertRedirect();
    }
}
