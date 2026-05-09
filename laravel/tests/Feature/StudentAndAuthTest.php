<?php

use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

describe('Student API', function () {

    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $this->tenant);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);

        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $this->admin->assignRole(
            Role::where('name', 'admin')->where('tenant_id', $this->tenant->id)->first()
        );
    });

    test('admin can create a student', function () {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/students', [
                'student_number' => '2024-9001',
                'first_name' => 'Jose',
                'last_name' => 'Rizal',
                'email' => 'jose@test.com',
                'status' => 'active',
                'program' => 'BS History',
                'year_level' => '4th Year',
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(201)
            ->assertJsonPath('data.student_number', '2024-9001')
            ->assertJsonPath('data.full_name', 'Jose Rizal');
    });

    test('student number must be unique within tenant', function () {
        Student::factory()->forTenant($this->tenant)->create(['student_number' => '2024-DUPE']);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/students', [
                'student_number' => '2024-DUPE',
                'first_name' => 'Another',
                'last_name' => 'Student',
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_number']);
    });

    test('admin can update a student', function () {
        $student = Student::factory()->forTenant($this->tenant)->create();

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/students/{$student->id}", [
                'status' => 'graduated',
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'graduated');
    });

    test('list supports search by name and student number', function () {
        Student::factory()->forTenant($this->tenant)->create([
            'first_name' => 'Andres',
            'last_name' => 'Bonifacio',
            'student_number' => '2024-SEARCH',
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/students?search=Bonifacio', ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });

    test('list supports filtering by status', function () {
        Student::factory()->forTenant($this->tenant)->count(3)->create(['status' => 'active']);
        Student::factory()->forTenant($this->tenant)->count(2)->create(['status' => 'inactive']);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/students?status=inactive', ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    });

});

describe('Auth Endpoints', function () {

    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $this->tenant);

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'staff',   'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
    });

    test('user can register and receive a token', function () {
        $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student',
        ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'roles']]]);
    });

    test('register assigns student role by default', function () {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Default Role User',
            'email' => 'defaultrole@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(201);

        expect($response->json('data.user.roles'))->toContain('student');
    });

    test('register prevents duplicate email within same tenant', function () {
        User::factory()->forTenant($this->tenant)->create(['email' => 'duplicate@test.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Dupe User',
            'email' => 'duplicate@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('register does not allow admin role self-assignment', function () {
        $this->postJson('/api/auth/register', [
            'name' => 'Hacker',
            'email' => 'hacker@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin', // should be rejected by validation
        ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    });

    test('user can login and receive a passport token', function () {
        $user = User::factory()->forTenant($this->tenant)->create([
            'email' => 'login@test.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'login@test.com',
            'password' => 'password123',
        ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    });

    test('login fails with wrong credentials', function () {
        User::factory()->forTenant($this->tenant)->create([
            'email' => 'valid@test.com',
            'password' => bcrypt('correctpassword'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'valid@test.com',
            'password' => 'wrongpassword',
        ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422);
    });

    test('inactive user cannot login', function () {
        User::factory()->forTenant($this->tenant)->create([
            'email' => 'inactive@test.com',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@test.com',
            'password' => 'password123',
        ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422);
    });

    test('logout revokes the token', function () {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        $admin = User::factory()->forTenant($this->tenant)->create();
        $admin->assignRole(Role::where('name', 'admin')->where('tenant_id', $this->tenant->id)->first());

        $this->actingAs($admin, 'api')
            ->postJson('/api/auth/logout', [], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully.']);
    });

});
