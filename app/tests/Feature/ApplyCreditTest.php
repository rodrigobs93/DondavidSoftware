<?php

namespace Tests\Feature;

use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for POST /cartera/{invoice}/apply-credit
 *
 * Uses DatabaseTransactions — each test runs inside a transaction that is
 * rolled back automatically, keeping the dev DB clean.
 */
class ApplyCreditTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Use the first admin user (seeded in dev DB) or create a minimal one
        $this->user = User::firstOrCreate(
            ['email' => 'test_credit@dondavid.test'],
            ['name' => 'Test Credit', 'password' => bcrypt('password'), 'is_admin' => true]
        );
    }

    private function makeCustomer(string $creditBalance): Customer
    {
        return Customer::create([
            'name'           => 'Cliente Test ' . uniqid(),
            'credit_balance' => $creditBalance,
        ]);
    }

    private function makeInvoice(Customer $customer, string $total, string $paidAmount = '0'): Invoice
    {
        $balance = bcsub($total, $paidAmount, 2);
        $status  = bccomp($balance, '0', 2) === 0 ? 'PAID'
                 : (bccomp($paidAmount, '0', 2) === 0 ? 'PENDING' : 'PARTIAL');

        $seq = DB::selectOne("SELECT nextval('invoice_consecutive_seq') AS val");

        return Invoice::create([
            'customer_id'        => $customer->id,
            'created_by_user_id' => $this->user->id,
            'consecutive'        => str_pad($seq->val, 7, '0', STR_PAD_LEFT),
            'consecutive_int'    => (int) $seq->val,
            'invoice_date'       => now()->toDateString(),
            'subtotal'           => $total,
            'delivery_fee'       => '0',
            'total'              => $total,
            'paid_amount'        => $paidAmount,
            'balance'            => $balance,
            'status'             => $status,
        ]);
    }

    /** credit >= invoice balance → invoice becomes PAID, credit reduced by full balance */
    public function test_credit_covers_full_balance(): void
    {
        $customer = $this->makeCustomer('200000');
        $invoice  = $this->makeInvoice($customer, '150000');

        $res = $this->actingAs($this->user)
                    ->postJson("/cartera/{$invoice->id}/apply-credit");

        $res->assertOk()->assertJson(['ok' => true, 'balance' => 0]);

        $invoice->refresh();
        $customer->refresh();

        $this->assertEquals('0.00',       $invoice->balance);
        $this->assertEquals('150000.00',  $invoice->paid_amount);
        $this->assertEquals('PAID',       $invoice->status);
        $this->assertEquals('50000.00',   $customer->credit_balance);

        $move = CreditMovement::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($move);
        $this->assertEquals('150000.00',          $move->amount);
        $this->assertEquals('APPLIED_TO_INVOICE', $move->type);
        $this->assertEquals($this->user->id,       $move->created_by_user_id);
    }

    /** credit < invoice balance → invoice PARTIAL, credit becomes 0 */
    public function test_credit_partially_covers_balance(): void
    {
        $customer = $this->makeCustomer('50000');
        $invoice  = $this->makeInvoice($customer, '150000');

        $res = $this->actingAs($this->user)
                    ->postJson("/cartera/{$invoice->id}/apply-credit");

        $res->assertOk()->assertJson(['ok' => true, 'balance' => 100000]);

        $invoice->refresh();
        $customer->refresh();

        $this->assertEquals('100000.00', $invoice->balance);
        $this->assertEquals('50000.00',  $invoice->paid_amount);
        $this->assertEquals('PARTIAL',   $invoice->status);
        $this->assertEquals('0.00',      $customer->credit_balance);

        $this->assertEquals('50000.00', CreditMovement::where('invoice_id', $invoice->id)->value('amount'));
    }

    /** custom amount less than max is applied correctly */
    public function test_custom_amount_applied(): void
    {
        $customer = $this->makeCustomer('100000');
        $invoice  = $this->makeInvoice($customer, '150000');

        $res = $this->actingAs($this->user)
                    ->postJson("/cartera/{$invoice->id}/apply-credit", ['amount' => 30000]);

        $res->assertOk()->assertJson(['applied' => 30000, 'balance' => 120000]);

        $invoice->refresh();
        $customer->refresh();

        $this->assertEquals('120000.00', $invoice->balance);
        $this->assertEquals('70000.00',  $customer->credit_balance);
        $this->assertEquals('30000.00',  CreditMovement::where('invoice_id', $invoice->id)->value('amount'));
    }

    /** credit = 0 → 422 error, nothing changes */
    public function test_no_credit_returns_error(): void
    {
        $customer = $this->makeCustomer('0');
        $invoice  = $this->makeInvoice($customer, '80000');

        $res = $this->actingAs($this->user)
                    ->postJson("/cartera/{$invoice->id}/apply-credit");

        $res->assertStatus(422)->assertJsonPath('error', fn($e) => str_contains($e, 'saldo'));

        $invoice->refresh();
        $this->assertEquals('80000.00', $invoice->balance);
        $this->assertEquals(0, CreditMovement::where('invoice_id', $invoice->id)->count());
    }

    /** invoice already PAID → 422 error */
    public function test_paid_invoice_returns_error(): void
    {
        $customer = $this->makeCustomer('100000');
        $invoice  = $this->makeInvoice($customer, '50000', '50000'); // already paid

        $res = $this->actingAs($this->user)
                    ->postJson("/cartera/{$invoice->id}/apply-credit");

        $res->assertStatus(422);
        $this->assertEquals(0, CreditMovement::where('invoice_id', $invoice->id)->count());
    }

    /** requested amount > max is clamped to max (server-side guard) */
    public function test_amount_exceeding_max_is_clamped(): void
    {
        $customer = $this->makeCustomer('30000');
        $invoice  = $this->makeInvoice($customer, '80000');

        // Request 999999 — server clamps to min(30000, 80000) = 30000
        $res = $this->actingAs($this->user)
                    ->postJson("/cartera/{$invoice->id}/apply-credit", ['amount' => 999999]);

        $res->assertOk()->assertJson(['applied' => 30000]);

        $customer->refresh();
        $this->assertEquals('0.00', $customer->credit_balance);
    }
}
