<?php

namespace Tests\Feature;

use Database\Seeders\DeallyAccessSeeder;
use Database\Seeders\DemoSeeder;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Pipeline\Models\Contact;
use Deally\Pipeline\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class ContactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->seed(DeallyAccessSeeder::class);

        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $provisioner = app(DeallyTenantProvisioner::class);
        $provisioner->migrate($acme);
        $provisioner->seed($acme);
    }

    protected function tearDown(): void
    {
        foreach (['tenant_1', 'tenant_2', 'deally'] as $connection) {
            DB::disconnect($connection);
        }

        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_a_contact_can_be_added_to_a_customer(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        $this->actingAs($charlie)
            ->post(route('deally.contacts.store', $stark), [
                'name' => 'Pepper Potts',
                'title' => 'COO',
                'email' => 'pepper@stark.example',
                'phone' => '555-0101',
            ])
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('contacts', [
            'customer_id' => $stark->getKey(),
            'name' => 'Pepper Potts',
            'is_primary' => 0,
        ], 'deally');
    }

    public function test_a_name_is_required_when_adding_a_contact(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        $this->actingAs($charlie)
            ->post(route('deally.contacts.store', $stark), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, $stark->contacts()->count());
    }

    public function test_marking_a_contact_primary_clears_the_other_primary(): void
    {
        $charlie = $this->user('charlie@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        Contact::query()->create([
            'customer_id' => $stark->getKey(),
            'name' => 'Virginia Potts',
            'is_primary' => true,
        ]);

        $this->actingAs($charlie)
            ->post(route('deally.contacts.store', $stark), [
                'name' => 'Harold Hogan',
                'is_primary' => 1,
            ])
            ->assertSessionHas('toast');

        $this->assertSame(0, (int) $stark->contacts()->where('name', 'Virginia Potts')->first()->is_primary);
        $this->assertSame(1, (int) $stark->contacts()->where('name', 'Harold Hogan')->first()->is_primary);
    }

    public function test_a_viewer_cannot_add_contacts(): void
    {
        $viewer = $this->user('support@example.com');
        $stark = Customer::query()->where('company', 'Stark Industries')->firstOrFail();

        $this->actingAs($viewer)
            ->post(route('deally.contacts.store', $stark), ['name' => 'Intruder'])
            ->assertForbidden();

        $this->assertSame(0, $stark->contacts()->count());
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
