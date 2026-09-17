<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Deally\Calls\Models\Call;
use Deally\Core\Models\User;
use Deally\Core\Services\DeallyTenantProvisioner;
use Deally\Core\Services\TenantConnectionBinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaasFoundation\Models\Tenant;
use Tests\TestCase;

class DeallySmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        $acme = Tenant::query()->where('slug', 'acme-corp')->firstOrFail();
        $provisioner = app(DeallyTenantProvisioner::class);
        $provisioner->migrate($acme);
        $provisioner->seed($acme);
    }

    protected function tearDown(): void
    {
        foreach (glob(database_path('tenants').DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_guest_sees_login_and_can_sign_in(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('DeAlly');

        $this->post('/login', [
            'email' => 'alice@example.com',
            'password' => 'password',
        ])->assertRedirect(route('deally.workspace'));
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->post('/login', [
            'email' => 'alice@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_superadmin_login_redirects_to_central_dashboard(): void
    {
        $superadmin = $this->makeSuperadmin();

        $this->post('/login', [
            'email' => $superadmin->email,
            'password' => 'password',
        ])->assertRedirect(route('central.dashboard'));

        $this->get('/')
            ->assertRedirect(route('central.dashboard'));
    }

    public function test_superadmin_sees_all_tenants_on_central_dashboard(): void
    {
        $this->actingAs($this->makeSuperadmin())
            ->get(route('central.dashboard'))
            ->assertOk()
            ->assertSee('Admin Dashboard')
            ->assertSee('Acme Corp')
            ->assertSee('Globex');
    }

    public function test_superadmin_without_membership_is_redirected_from_workspace(): void
    {
        $this->actingAs($this->makeSuperadmin())
            ->get(route('deally.workspace'))
            ->assertRedirect(route('central.dashboard'));
    }

    public function test_all_app_pages_render_for_signed_in_user(): void
    {
        $user = User::query()->where('email', 'alice@example.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('deally.workspace'))
            ->assertOk()
            ->assertSee('Workspace');

        $this->actingAs($user)->get(route('deally.pipeline'))->assertOk()->assertSee('Pipeline');
        $this->actingAs($user)->get(route('deally.calls.index'))->assertOk()->assertSee('Calls');
        $this->actingAs($user)->get(route('deally.tasks.index'))->assertOk()->assertSee('Tasks');
        $this->actingAs($user)->get(route('deally.proposals.index'))->assertOk()->assertSee('Proposals');
        $this->actingAs($user)->get(route('deally.kb.index'))->assertOk()->assertSee('Knowledge Base');
        $this->actingAs($user)->get(route('deally.settings.index'))->assertOk()->assertSee('Settings');
    }

    public function test_call_pages_render(): void
    {
        $user = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $membership = $user->memberships()->active()->with('tenant')->first();

        app(TenantConnectionBinder::class)->bind($membership->tenant);

        $call = Call::query()->where('company', 'Acme Corp')->firstOrFail();

        $this->actingAs($user)->get(route('deally.calls.show', $call))->assertOk();
        $this->actingAs($user)->get(route('deally.calls.live', $call))->assertOk()->assertSee('live-script');
        $this->actingAs($user)->get(route('deally.calls.summary', $call))->assertOk()->assertSee('Call Summary');
    }

    private function makeSuperadmin(): User
    {
        return User::unguarded(fn () => User::create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
            'is_superadmin' => true,
            'is_admin' => true,
        ]));
    }
}
