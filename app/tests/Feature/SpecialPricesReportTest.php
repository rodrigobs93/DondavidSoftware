<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\Invoice;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\User;
use App\Services\ThermalPrinterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Precios especiales — one thermal ticket with every customer's agreed prices,
 * triggered from the customers list. The printer service is mocked so no bytes
 * ever reach the spooler. DatabaseTransactions keeps the dev DB clean.
 *
 * NOTE: these tests run against the dev database, which may already hold real
 * customers with special prices. Assertions therefore check for *presence* of
 * the fixtures rather than exact counts, except where a fixture is isolated.
 */
class SpecialPricesReportTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function customer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'name'   => 'Cliente SP ' . uniqid(),
            'active' => true,
        ], $attrs));
    }

    private function product(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'name'       => 'Prod SP ' . uniqid(),
            'sale_unit'  => 'KG',
            'base_price' => 10000,
            'active'     => true,
        ], $attrs));
    }

    private function specialPrice(Customer $c, Product $p, $price): CustomerProductPrice
    {
        return CustomerProductPrice::create([
            'customer_id' => $c->id,
            'product_id'  => $p->id,
            'price'       => $price,
        ]);
    }

    /** Drop every pre-existing special price so counts are deterministic. */
    private function isolate(): void
    {
        CustomerProductPrice::query()->delete();
    }

    public function test_print_requires_auth(): void
    {
        $this->post('/customers/special-prices/print')->assertRedirect('/login');
    }

    public function test_print_requires_admin(): void
    {
        $cashier = User::where('role', '!=', 'admin')->first();
        if (!$cashier) {
            $this->markTestSkipped('No non-admin user in DB.');
        }
        $this->actingAs($cashier)
            ->postJson('/customers/special-prices/print')
            ->assertStatus(403);
    }

    public function test_customers_page_offers_the_print_button(): void
    {
        $this->actingAs($this->admin)
            ->get('/customers')
            ->assertStatus(200)
            ->assertSee('Imprimir precios especiales')
            ->assertSee('printSpecialPrices', false);
    }

    public function test_print_sends_bytes_with_current_prices(): void
    {
        $c    = $this->customer(['name' => 'Panadería Ñandú SP', 'business_name' => 'Café Bogotá SP']);
        $kg   = $this->product(['name' => 'Lomo fino SP', 'sale_unit' => 'KG',   'base_price' => 32000]);
        $unit = $this->product(['name' => 'Chorizo SP',   'sale_unit' => 'UNIT', 'base_price' => 2500]);
        $this->specialPrice($c, $kg,   29000);
        $this->specialPrice($c, $unit, 2000);

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with(Mockery::on(fn ($bytes) =>
                str_contains($bytes, 'PRECIOS ESPECIALES')
                && str_contains($bytes, 'Panaderia Nandu SP')   // sanitized, no accents
                && str_contains($bytes, 'Cafe Bogota SP')
                && str_contains($bytes, 'Lomo fino SP')
                && str_contains($bytes, '$29.000')              // special
                && str_contains($bytes, '$32.000')              // normal, for comparison
                && str_contains($bytes, '$2.000')
                && str_contains($bytes, '$2.500')
                && str_contains($bytes, 'Clientes:')
                && str_contains($bytes, 'Productos:')
            ));
        });

        $this->actingAs($this->admin)
            ->postJson('/customers/special-prices/print')
            ->assertStatus(200)
            ->assertJsonPath('ok', true);
    }

    public function test_print_groups_every_customer_alphabetically(): void
    {
        $this->isolate();

        $zeta = $this->customer(['name' => 'Zeta Restaurante SP']);
        $alfa = $this->customer(['name' => 'Alfa Restaurante SP']);
        $pz   = $this->product(['name' => 'Producto Zeta SP', 'base_price' => 20000]);
        $pa   = $this->product(['name' => 'Producto Alfa SP', 'base_price' => 10000]);
        $this->specialPrice($zeta, $pz, 19000);
        $this->specialPrice($alfa, $pa, 9000);

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with(Mockery::on(function ($bytes) {
                $posAlfa = strpos($bytes, 'Alfa Restaurante SP');
                $posZeta = strpos($bytes, 'Zeta Restaurante SP');
                return $posAlfa !== false && $posZeta !== false
                    && $posAlfa < $posZeta                                    // alphabetical
                    // each product sits inside its own customer section
                    && strpos($bytes, 'Producto Alfa SP') > $posAlfa
                    && strpos($bytes, 'Producto Alfa SP') < $posZeta
                    && strpos($bytes, 'Producto Zeta SP') > $posZeta;
            }));
        });

        $this->actingAs($this->admin)
            ->postJson('/customers/special-prices/print')
            ->assertStatus(200)
            ->assertJsonPath('customers', 2)
            ->assertJsonPath('printed', 2);
    }

    public function test_print_excludes_customers_without_special_prices(): void
    {
        $this->isolate();

        $with    = $this->customer(['name' => 'Con Acuerdo SP']);
        $without = $this->customer(['name' => 'Sin Acuerdo SP']);
        $this->specialPrice($with, $this->product(), 5000);

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with(Mockery::on(fn ($bytes) =>
                str_contains($bytes, 'Con Acuerdo SP') && !str_contains($bytes, 'Sin Acuerdo SP')
            ));
        });

        $this->actingAs($this->admin)
            ->postJson('/customers/special-prices/print')
            ->assertStatus(200)
            ->assertJsonPath('customers', 1);
    }

    public function test_print_excludes_soft_deleted_products(): void
    {
        $this->isolate();

        $c     = $this->customer();
        $alive = $this->product(['name' => 'Vivo SP',    'base_price' => 12000]);
        $gone  = $this->product(['name' => 'Borrado SP', 'base_price' => 9000]);
        $this->specialPrice($c, $alive, 11000);
        $this->specialPrice($c, $gone,  8000);
        $gone->delete();   // soft delete — the price row survives

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with(Mockery::on(fn ($bytes) =>
                str_contains($bytes, 'Vivo SP') && !str_contains($bytes, 'Borrado SP')
            ));
        });

        $this->actingAs($this->admin)
            ->postJson('/customers/special-prices/print')
            ->assertStatus(200)
            ->assertJsonPath('printed', 1);
    }

    public function test_print_rejects_when_nobody_has_special_prices(): void
    {
        $this->isolate();

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('send');
        });

        $this->actingAs($this->admin)
            ->postJson('/customers/special-prices/print')
            ->assertStatus(422)
            ->assertJsonPath('error', fn ($e) => str_contains($e, 'precios especiales'));
    }

    public function test_print_returns_500_when_printer_fails(): void
    {
        $this->specialPrice($this->customer(), $this->product(), 5000);

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('sin papel'));
        });

        $this->actingAs($this->admin)
            ->postJson('/customers/special-prices/print')
            ->assertStatus(500)
            ->assertJsonPath('error', fn ($e) => str_contains($e, 'Error al imprimir'));
    }

    public function test_print_creates_no_invoice_or_print_job(): void
    {
        $this->specialPrice($this->customer(), $this->product(), 5000);

        $this->mock(ThermalPrinterService::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once();
        });

        $invoices  = Invoice::count();
        $printJobs = PrintJob::count();

        $this->actingAs($this->admin)
            ->postJson('/customers/special-prices/print')
            ->assertStatus(200);

        $this->assertEquals($invoices, Invoice::count());
        $this->assertEquals($printJobs, PrintJob::count());
    }
}
