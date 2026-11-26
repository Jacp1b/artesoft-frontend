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

    public function test_customer_registration_rejects_invalid_name_phone_city_and_address(): void
    {
        $csrfToken = $this->get('/api/index.php?resource=auth/me')->json('csrfToken');
        $validData = [
            'name' => 'María Pérez',
            'email' => 'customer@example.com',
            'password' => 'StrongPass123!',
            'phone' => '3001234567',
            'city' => 'Bogotá D.C.',
            'address' => 'Calle 10 # 5-20',
        ];
        $invalidCases = [
            ['name' => 'María Pérez2'],
            ['phone' => '+573001234567'],
            ['city' => 'Medellín'],
            ['address' => 'Apto 201, edificio Central'],
        ];

        foreach ($invalidCases as $index => $invalidFields) {
            $this->withHeader('X-CSRF-Token', $csrfToken)
                ->postJson('/api/index.php?resource=auth/register', array_merge($validData, [
                    'email' => 'customer' . $index . '@example.com',
                ], $invalidFields))
                ->assertUnprocessable()
                ->assertJsonPath('ok', false);
        }
    }

    public function test_customer_registration_accepts_colombian_mobile_and_valid_street_address(): void
    {
        $csrfToken = $this->get('/api/index.php?resource=auth/me')->json('csrfToken');

        $this->withHeader('X-CSRF-Token', $csrfToken)
            ->postJson('/api/index.php?resource=auth/register', [
                'name' => 'María Pérez',
                'email' => 'customer@example.com',
                'password' => 'StrongPass123!',
                'phone' => '3001234567',
                'city' => 'Bogotá D.C.',
                'address' => 'Cra 15 # 10-20',
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.phone', '3001234567');
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
                ->get('/api/index.php?resource=reports/' . $type . '&status=pending');

            $response->assertOk()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('Cache-Control', 'no-store');
            $this->assertStringStartsWith("\xEF\xBB\xBF", $response->getContent());
        }
    }
}
