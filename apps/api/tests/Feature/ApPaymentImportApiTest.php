<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Payments\Support\PaymentImportCsvParser;
use Domains\Payments\Support\PaymentImportJsonParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApPaymentImportApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Tenant, User} */
    private function tenantUserPair(string $role): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);
        app(CurrentTenant::class)->set($tenant);

        return [$tenant, $user];
    }

    public function test_csv_parser_parses_valid_csv(): void
    {
        $csv = "handoff_number,status,paid_at,mark_full\nAPH-001,paid,2026-06-20,true\n";
        $parser = app(PaymentImportCsvParser::class);
        $rows = $parser->parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('APH-001', $rows[0]->handoffNumber);
        $this->assertSame('paid', $rows[0]->status);
    }

    public function test_csv_parser_throws_on_empty_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PaymentImportCsvParser::class)->parse('');
    }

    public function test_csv_parser_throws_on_missing_status_header(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PaymentImportCsvParser::class)->parse("handoff_number,paid_at\nAPH-001,2026-06-20\n");
    }

    public function test_json_parser_parses_valid_json(): void
    {
        $json = json_encode([
            'rows' => [
                ['handoffNumber' => 'APH-001', 'status' => 'paid', 'paidAt' => '2026-06-20', 'markFull' => true],
            ],
        ]);
        $parser = app(PaymentImportJsonParser::class);
        $rows = $parser->parse($json);

        $this->assertCount(1, $rows);
        $this->assertSame('APH-001', $rows[0]->handoffNumber);
        $this->assertSame('paid', $rows[0]->status);
        $this->assertTrue($rows[0]->markFull);
    }

    public function test_json_parser_throws_on_missing_rows_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(PaymentImportJsonParser::class)->parse('{"notRows": []}');
    }

    public function test_csv_parser_normalizes_empty_string_payment_reference_to_null(): void
    {
        $csv = "handoff_number,status,paid_at,payment_reference,mark_full\nAPH-001,paid,2026-06-20,,true\n";
        $rows = app(PaymentImportCsvParser::class)->parse($csv);

        $this->assertNull($rows[0]->paymentReference);
    }
}
