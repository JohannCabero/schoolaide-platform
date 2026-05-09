<?php

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

describe('Tenant Registration', function () {

    test('a new school can register a tenant and receive admin credentials', function () {
        $response = $this->postJson('/api/tenant/register', [
            'organization_name' => 'St. Paul University',
            'slug' => 'stpaul',
            'admin_name' => 'Head Admin',
            'admin_email' => 'admin@stpaul.edu',
            'admin_password' => 'securepass123',
            'admin_password_confirmation' => 'securepass123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'tenant' => ['id', 'name', 'slug', 'login_hint'],
                    'admin' => ['id', 'name', 'email', 'roles'],
                    'token',
                ],
                'message',
            ]);

        // Tenant was persisted
        $this->assertDatabaseHas('tenants', [
            'slug' => 'stpaul',
            'name' => 'St. Paul University',
            'is_active' => true,
        ]);

        // Admin user was persisted under the correct tenant
        $tenant = Tenant::where('slug', 'stpaul')->first();
        $this->assertDatabaseHas('users', [
            'email' => 'admin@stpaul.edu',
            'tenant_id' => $tenant->id,
        ]);

        // Admin has the admin role
        $admin = User::where('email', 'admin@stpaul.edu')->first();
        expect($admin->hasRole('admin'))->toBeTrue();

        // All three base roles were created for this tenant
        expect(Role::where('tenant_id', $tenant->id)->pluck('name')->sort()->values()->toArray())
            ->toBe(['admin', 'staff', 'student']);

        // A Passport token was issued
        expect($response->json('data.token'))->not->toBeNull();
    });

    test('slug must be unique across tenants', function () {
        Tenant::factory()->create(['slug' => 'taken-slug']);

        $this->postJson('/api/tenant/register', [
            'organization_name' => 'Another School',
            'slug' => 'taken-slug',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@another.com',
            'admin_password' => 'securepass123',
            'admin_password_confirmation' => 'securepass123',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['slug']);
    });

    test('slug must be lowercase alphanumeric with hyphens only', function () {
        $this->postJson('/api/tenant/register', [
            'organization_name' => 'Bad Slug School',
            'slug' => 'Bad_Slug 123!',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@bad.com',
            'admin_password' => 'securepass123',
            'admin_password_confirmation' => 'securepass123',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['slug']);
    });

    test('password confirmation must match', function () {
        $this->postJson('/api/tenant/register', [
            'organization_name' => 'Mismatch School',
            'slug' => 'mismatch',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@mismatch.com',
            'admin_password' => 'securepass123',
            'admin_password_confirmation' => 'differentpass',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['admin_password']);
    });

    test('registration is atomic — no partial data on failure', function () {
        // Manually create a tenant with the same slug to force a failure
        Tenant::factory()->create(['slug' => 'atomic-test']);

        $this->postJson('/api/tenant/register', [
            'organization_name' => 'Atomic Test School',
            'slug' => 'atomic-test', // duplicate
            'admin_name' => 'Admin',
            'admin_email' => 'admin@atomic.com',
            'admin_password' => 'securepass123',
            'admin_password_confirmation' => 'securepass123',
        ])->assertStatus(422);

        // No user should have been created for the failed registration
        $this->assertDatabaseMissing('users', ['email' => 'admin@atomic.com']);
    });

});

describe('Tenant Login Flow', function () {

    beforeEach(function () {
        // Register a tenant first
        $this->tenant = Tenant::factory()->create(['slug' => 'login-test-school']);
        app()->instance('currentTenant', $this->tenant);

        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'staff',   'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);

        $this->admin = User::factory()->forTenant($this->tenant)->create([
            'email' => 'admin@loginschool.com',
            'password' => bcrypt('adminpass123'),
        ]);
        $this->admin->assignRole(
            Role::where('name', 'admin')->where('tenant_id', $this->tenant->id)->first()
        );
    });

    test('admin can login using X-Tenant header and receive a token', function () {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@loginschool.com',
            'password' => 'adminpass123',
        ], ['X-Tenant' => 'login-test-school']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'roles'],
                ],
            ]);

        expect($response->json('data.user.roles'))->toContain('admin');
    });

    test('login fails when using a valid email but wrong tenant header', function () {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-school']);
        app()->instance('currentTenant', $otherTenant);

        // Valid credentials but wrong tenant
        $this->postJson('/api/auth/login', [
            'email' => 'admin@loginschool.com',
            'password' => 'adminpass123',
        ], ['X-Tenant' => 'other-school'])  // Wrong tenant!
            ->assertStatus(422);
    });

    test('login fails when tenant header is missing', function () {
        $this->postJson('/api/auth/login', [
            'email' => 'admin@loginschool.com',
            'password' => 'adminpass123',
        ])->assertStatus(404); // TenantMiddleware returns 404 when tenant not found
    });

    test('authenticated user can get their profile', function () {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/auth/me', ['X-Tenant' => 'login-test-school']);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'admin@loginschool.com')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'roles', 'permissions']]);
    });

    test('authenticated admin can view tenant profile', function () {
        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/tenant/profile', ['X-Tenant' => 'login-test-school']);

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'login-test-school');
    });

    test('full flow: register tenant → login → access protected endpoint', function () {
        // Step 1: Register
        $regResponse = $this->postJson('/api/tenant/register', [
            'organization_name' => 'Flow Test University',
            'slug' => 'flowtest',
            'admin_name' => 'Flow Admin',
            'admin_email' => 'admin@flowtest.com',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])->assertStatus(201);

        $token = $regResponse->json('data.token');
        expect($token)->not->toBeNull();

        // Step 2: Use token to access protected endpoint
        $this->withToken($token)
            ->getJson('/api/auth/me', ['X-Tenant' => 'flowtest'])
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'admin@flowtest.com');

        // Step 3: Login explicitly
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@flowtest.com',
            'password' => 'password123',
        ], ['X-Tenant' => 'flowtest'])->assertStatus(200);

        $newToken = $loginResponse->json('data.token');
        expect($newToken)->not->toBeNull();
    });

});

describe('Custom Domain Resolution', function () {

    test('tenant registered with a custom domain is resolved by that hostname', function () {
        // Register a tenant with a custom domain
        $tenant = Tenant::factory()->create([
            'slug' => 'domaintest',
            'domain' => 'portal.greenfield.edu',
        ]);
        app()->instance('currentTenant', $tenant);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $tenant->id]);
        $admin = User::factory()->forTenant($tenant)->create([
            'email' => 'admin@domaintest.com',
            'password' => bcrypt('password123'),
        ]);
        $admin->assignRole(Role::where('name', 'admin')->where('tenant_id', $tenant->id)->first());

        // Simulate a request arriving at the custom domain — no X-Tenant header
        // The middleware resolves it via Strategy 2 (domain column lookup)
        $response = $this->postJson('/api/auth/login',
            ['email' => 'admin@domaintest.com', 'password' => 'password123'],
            ['HOST' => 'portal.greenfield.edu']   // custom domain as HTTP Host
        );

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    });

    test('domain takes priority over subdomain when both could match', function () {
        // Tenant A: slug = "alpha", no custom domain
        $tenantA = Tenant::factory()->create(['slug' => 'alpha', 'domain' => null]);

        // Tenant B: different slug but custom domain that happens to start with "alpha"
        $tenantB = Tenant::factory()->create([
            'slug' => 'beta',
            'domain' => 'alpha.external-school.com',
        ]);

        // A request to alpha.external-school.com should resolve to Tenant B (domain match),
        // NOT Tenant A (which would match on subdomain "alpha")
        app()->instance('currentTenant', null);

        $middleware = new \App\Http\Middleware\TenantMiddleware();

        $request = \Illuminate\Http\Request::create('http://alpha.external-school.com/api/test');

        $resolved = null;
        $middleware->handle($request, function ($req) use (&$resolved) {
            $resolved = app('currentTenant');
            return response('ok');
        });

        expect($resolved?->id)->toBe($tenantB->id);
    });

    test('tenant registration accepts and persists a custom domain', function () {
        $this->postJson('/api/tenant/register', [
            'organization_name' => 'Domain School',
            'slug' => 'domainschool',
            'domain' => 'students.domainschool.edu',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@ds.com',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])->assertStatus(201)
          ->assertJsonPath('data.tenant.domain', 'students.domainschool.edu')
          ->assertJsonFragment(['login_hint' => 'Requests to students.domainschool.edu are automatically routed to this tenant. You can also use X-Tenant: domainschool.']);

        $this->assertDatabaseHas('tenants', ['domain' => 'students.domainschool.edu']);
    });

    test('two tenants cannot share the same custom domain', function () {
        Tenant::factory()->create(['domain' => 'shared.edu']);

        $this->postJson('/api/tenant/register', [
            'organization_name' => 'Another School',
            'slug' => 'another',
            'domain' => 'shared.edu',   // already taken
            'admin_name' => 'Admin',
            'admin_email' => 'admin@another.com',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['domain']);
    });

    test('admin can set a custom domain after registration via profile update', function () {
        $tenant = Tenant::factory()->create(['slug' => 'latedomain', 'domain' => null]);
        app()->instance('currentTenant', $tenant);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $tenant->id]);
        $admin = User::factory()->forTenant($tenant)->create();
        $admin->assignRole(Role::where('name', 'admin')->where('tenant_id', $tenant->id)->first());

        $this->actingAs($admin, 'api')
            ->patchJson('/api/tenant/profile',
                ['domain' => 'portal.lateschool.com'],
                ['X-Tenant' => 'latedomain']
            )->assertStatus(200)
             ->assertJsonPath('data.domain', 'portal.lateschool.com');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'domain' => 'portal.lateschool.com',
        ]);
    });

    test('admin can remove a custom domain by setting it to null', function () {
        $tenant = Tenant::factory()->create(['slug' => 'removedomain', 'domain' => 'old.school.com']);
        app()->instance('currentTenant', $tenant);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $tenant->id]);
        $admin = User::factory()->forTenant($tenant)->create();
        $admin->assignRole(Role::where('name', 'admin')->where('tenant_id', $tenant->id)->first());

        $this->actingAs($admin, 'api')
            ->patchJson('/api/tenant/profile',
                ['domain' => null],
                ['X-Tenant' => 'removedomain']
            )->assertStatus(200)
             ->assertJsonPath('data.domain', null);
    });

});
