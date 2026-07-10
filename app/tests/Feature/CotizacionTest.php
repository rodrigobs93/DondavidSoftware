<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ThermalPrinterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Cotización (price quotation) — page + synchronous print endpoint.
 * The printer service is mocked so no bytes ever reach the spooler.
 * DatabaseTransactions rolls each test back, keeping the dev DB clean.
 */
class CotizacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function product(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'name'       => 'Prod Cotiz ' . uniqid(),
            'sale_unit'  => 'KG',
            'base_price' => 10000,
            'active'     => true,
        ], $attrs));
    }

    public function test_page_requires_auth(): void
    {
        $this->get('/products/cotizacion')->assertRedirect('/login');
    }

    public function test_page_requires_admin(): void
    {
        $cashier = User::where('role', '!=', 'admin')->first();
        if (!$cashier) {
            $this->markTestSkipped('No non-admin user in DB.');
        }
        $this->actingAs($cashier)->get('/products/cotizacion')->assertStatus(403);
    }

    public function test_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get('/products/cotizacion')
            ->assertStatus(200)
            ->assertSee('Cotización')
            ->assertSee('Seleccionar todos');
    }

    public function test_print_sends_bytes_with_current_prices(): void
    {
        $kg   = $this->product(['name' => 'Jamón ahumado', 'sale_unit' => 'KG',   'base_price' => 18000]);
        $unit = $this->product(['name' => 'Chorizo',       'sale_unit' => 'UNIT', 'base_price' => 2500]);

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with(Mockery::on(fn ($bytes) =>
                str_contains($bytes, 'COTIZACION')
                && str_contains($bytes, 'Jamon ahumado')          // sanitized, no accent
                && str_contains($bytes, '$18.000 / kg')
                && str_contains($bytes, '$2.500 / unidad')
                && str_contains($bytes, 'OTROS')                  // uncategorized fall-back section
            ));
        });

        $this->actingAs($this->admin)
            ->postJson('/products/cotizacion/print', ['product_ids' => [$kg->id, $unit->id]])
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('printed', 2);
    }

    public function test_print_groups_products_by_category(): void
    {
        $res   = ProductCategory::create(['name' => 'Res Cotiz ' . uniqid(),   'active' => true]);
        $cerdo = ProductCategory::create(['name' => 'Cerdo Cotiz ' . uniqid(), 'active' => true]);

        $molida   = $this->product(['name' => 'Carne molida',  'base_price' => 18000, 'category_id' => $res->id]);
        $costilla = $this->product(['name' => 'Costilla',      'base_price' => 16000, 'category_id' => $cerdo->id]);
        $bolsa    = $this->product(['name' => 'Bolsa hielo',   'base_price' => 1000,  'sale_unit' => 'UNIT']); // no category

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) use ($res, $cerdo) {
            $mock->shouldReceive('send')->once()->with(Mockery::on(function ($bytes) use ($res, $cerdo) {
                $posCerdo = strpos($bytes, mb_strtoupper($cerdo->name)); // alphabetical: Cerdo < Res
                $posRes   = strpos($bytes, mb_strtoupper($res->name));
                $posOtros = strpos($bytes, 'OTROS');                     // uncategorized always last
                return $posCerdo !== false && $posRes !== false && $posOtros !== false
                    && $posCerdo < $posRes && $posRes < $posOtros
                    // each product sits inside its own section
                    && strpos($bytes, 'Costilla')     > $posCerdo && strpos($bytes, 'Costilla')    < $posRes
                    && strpos($bytes, 'Carne molida') > $posRes   && strpos($bytes, 'Carne molida') < $posOtros
                    && strpos($bytes, 'Bolsa hielo')  > $posOtros;
            }));
        });

        $this->actingAs($this->admin)
            ->postJson('/products/cotizacion/print', ['product_ids' => [$molida->id, $costilla->id, $bolsa->id]])
            ->assertStatus(200)
            ->assertJsonPath('printed', 3);
    }

    public function test_print_rejects_empty_selection(): void
    {
        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('send');
        });

        $this->actingAs($this->admin)
            ->postJson('/products/cotizacion/print', ['product_ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_ids']);
    }

    public function test_print_rejects_when_no_valid_products(): void
    {
        $zero     = $this->product(['base_price' => 0]);
        $inactive = $this->product(['active' => false]);

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('send');
        });

        $this->actingAs($this->admin)
            ->postJson('/products/cotizacion/print', ['product_ids' => [$zero->id, $inactive->id]])
            ->assertStatus(422)
            ->assertJsonPath('error', fn ($e) => str_contains($e, 'precio válido'));
    }

    public function test_print_filters_invalid_but_prints_valid(): void
    {
        $valid    = $this->product(['name' => 'Costilla', 'base_price' => 16000]);
        $zero     = $this->product(['base_price' => 0]);
        $inactive = $this->product(['active' => false]);

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with(Mockery::on(fn ($bytes) =>
                str_contains($bytes, 'Costilla') && str_contains($bytes, '$16.000 / kg')
            ));
        });

        $this->actingAs($this->admin)
            ->postJson('/products/cotizacion/print', ['product_ids' => [$valid->id, $zero->id, $inactive->id]])
            ->assertStatus(200)
            ->assertJsonPath('printed', 1);
    }

    public function test_print_returns_500_when_printer_fails(): void
    {
        $p = $this->product();

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('sin papel'));
        });

        $this->actingAs($this->admin)
            ->postJson('/products/cotizacion/print', ['product_ids' => [$p->id]])
            ->assertStatus(500)
            ->assertJsonPath('error', fn ($e) => str_contains($e, 'Error al imprimir'));
    }

    public function test_print_creates_no_sale_invoice_or_print_job(): void
    {
        $p = $this->product();

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once();
        });

        $invoices  = \App\Models\Invoice::count();
        $printJobs = \App\Models\PrintJob::count();

        $this->actingAs($this->admin)
            ->postJson('/products/cotizacion/print', ['product_ids' => [$p->id]])
            ->assertStatus(200);

        $this->assertEquals($invoices, \App\Models\Invoice::count());
        $this->assertEquals($printJobs, \App\Models\PrintJob::count());
    }
}
