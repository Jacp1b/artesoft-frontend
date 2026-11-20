<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_and_legacy_product_api_work_through_laravel(): void
    {
        $this->seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('ARTESOFT');

        $this->get('/api/index.php?resource=products')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(12, 'data');

        $adminId = DB::table('users')->where('email', 'admin@artesoft.local')->value('id');
        foreach (['orders', 'products'] as $type) {
            $response = $this->withSession(['user_id' => $adminId])
                ->get('/api/index.php?resource=reports/' . $type);

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_api_mutations_require_the_csrf_header(): void
    {
        $this->postJson('/api/index.php?resource=auth/login', [
            'email' => 'admin@artesoft.local',
            'password' => 'Demo1234!',
        ])
            ->assertForbidden()
            ->assertJsonPath('ok', false);
    }

    public function test_all_pdf_reports_render_when_the_database_is_empty(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'administrador',
            'phone' => '',
            'city' => '',
            'address' => '',
        ]);

        foreach (['pdf', 'orders', 'products'] as $type) {
            $response = $this->withSession(['user_id' => $userId])
                ->get('/api/index.php?resource=reports/' . $type);

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_csv_reports_download_as_utf8_csv(): void
    {
        $this->seed();
        $adminId = DB::table('users')->where('email', 'admin@artesoft.local')->value('id');

        foreach (['csv', 'orders/csv', 'products/csv'] as $type) {
            $response = $this->withSession(['user_id' => $adminId])
                ->get('/api/index.php?resource=reports/' . $type);

            $response->assertOk()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('Cache-Control', 'no-store');
            $this->assertStringStartsWith("\xEF\xBB\xBF", $response->getContent());
        }
    }
}
