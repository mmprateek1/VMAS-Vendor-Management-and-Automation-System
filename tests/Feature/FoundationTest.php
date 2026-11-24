<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessSeeder::class);
        $this->withoutVite();
    }

    private function account(string $role = 'employee', array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
            'is_active' => true, 'must_change_password' => false,
            'two_factor_confirmed_at' => $role === 'admin' ? now() : null,
            'password' => 'A-long-test-password-42',
        ], $attributes));
    }

    public function test_guests_are_redirected_and_public_registration_is_disabled(): void
    {
        foreach (['/dashboard', '/users', '/audit', '/settings', '/account/security'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_employee_can_login_logout_and_activity_is_recorded(): void
    {
        $user = $this->account();
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'A-long-test-password-42'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.login']);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout']);
    }

    public function test_disabled_and_unassigned_accounts_cannot_login(): void
    {
        foreach ([['is_active' => false], ['role_id' => null]] as $attributes) {
            $user = $this->account(attributes: $attributes);
            $this->post('/login', ['email' => $user->email, 'password' => 'A-long-test-password-42'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->account();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertStatus(429);
    }

    public function test_management_can_read_but_cannot_mutate_even_by_direct_http(): void
    {
        $coo = $this->account('coo');
        $employee = $this->account();
        $this->actingAs($coo);
        $this->get('/dashboard')->assertOk()->assertSee('Read-only');
        $this->get('/audit')->assertOk();
        $this->get('/users')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->post('/users', [])->assertForbidden();
        $this->patch('/users/'.$employee->id, [])->assertForbidden();
        $this->delete('/users/'.$employee->id)->assertStatus(405);
    }

    public function test_employee_is_denied_administration_and_other_users_activity(): void
    {
        $user = $this->account();
        $other = $this->account();
        AuditLog::create(['user_id' => $other->id, 'action' => 'private.other_event', 'entity_type' => 'user', 'created_at' => now()]);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('private.other_event')->assertDontSee('Private other event');
        foreach (['/users', '/audit', '/settings'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/users', [])->assertForbidden();
    }

    public function test_administrator_must_enroll_mfa_before_operations(): void
    {
        $this->actingAs($this->account('admin', ['two_factor_confirmed_at' => null]));
        $this->get('/dashboard')->assertRedirect('/account/security');
        $this->post('/users', [])->assertRedirect('/account/security');
    }

    public function test_temporary_password_must_be_changed(): void
    {
        $user = $this->account(attributes: ['must_change_password' => true]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/account/security');
        $this->put('/user/password', ['current_password' => 'A-long-test-password-42', 'password' => 'A-new-private-password-73', 'password_confirmation' => 'A-new-private-password-73'])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/dashboard')->assertOk();
    }

    public function test_administrator_can_provision_employee_and_password_is_never_audited(): void
    {
        $this->actingAs($this->account('admin'))->post('/users', [
            'name' => 'Test Employee', 'email' => 'EMPLOYEE@example.test',
            'role_id' => Role::where('name', 'employee')->value('id'),
            'password' => 'A-temporary-secret-59', 'password_confirmation' => 'A-temporary-secret-59', 'reason' => 'Approved onboarding',
        ])->assertRedirect('/users');
        $user = User::where('email', 'employee@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('A-temporary-secret-59', $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertStringNotContainsString('A-temporary-secret-59', AuditLog::all()->toJson());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'entity_id' => $user->id, 'reason' => 'Approved onboarding']);
    }

    public function test_invalid_user_creation_does_not_write_partial_data(): void
    {
        $admin = $this->account('admin');
        $this->actingAs($admin)->post('/users', ['name' => 'Test', 'email' => $admin->email, 'role_id' => 9999, 'password' => 'short', 'reason' => ''])->assertSessionHasErrors(['email', 'role_id', 'password', 'reason']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_cannot_deactivate_or_demote_self(): void
    {
        $admin = $this->account('admin');
        $this->actingAs($admin)->patch('/users/'.$admin->id, ['name' => $admin->name, 'role_id' => $admin->role_id, 'is_active' => false, 'reason' => 'Test'])->assertStatus(422);
        $this->patch('/users/'.$admin->id, ['name' => $admin->name, 'role_id' => Role::where('name', 'coo')->value('id'), 'is_active' => true, 'reason' => 'Test'])->assertStatus(422);
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame('admin', $admin->fresh()->role->name);
    }

    public function test_account_deactivation_revokes_sessions_and_records_history(): void
    {
        $admin = $this->account('admin');
        $user = $this->account();
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($admin)->patch('/users/'.$user->id, ['name' => $user->name, 'role_id' => $user->role_id, 'is_active' => false, 'reason' => 'Employee departed'])->assertRedirect('/users');
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'reason' => 'Employee departed']);
        $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_password_reset_token_is_required_and_single_use(): void
    {
        $user = $this->account();
        $data = ['email' => $user->email, 'password' => 'A-new-private-password-73', 'password_confirmation' => 'A-new-private-password-73'];
        $this->post('/reset-password', $data + ['token' => 'invalid'])->assertSessionHasErrors('email');
        $token = Password::createToken($user);
        $this->post('/reset-password', $data + ['token' => $token])->assertRedirect('/login');
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->post('/reset-password', $data + ['token' => $token])->assertSessionHasErrors('email');
    }

    public function test_expired_password_reset_token_is_rejected(): void
    {
        $user = $this->account();
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'A-new-private-password-73', 'password_confirmation' => 'A-new-private-password-73'])->assertSessionHasErrors('email');
        $this->travelBack();
    }

    public function test_password_reset_response_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        $user = $this->account();
        $existing = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->json();
        $absent = $this->postJson('/forgot-password', ['email' => 'absent@example.test'])->assertOk()->json();
        $this->assertSame($existing, $absent);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_mfa_enrollment_encrypts_secrets_and_requires_valid_code(): void
    {
        $user = $this->account('admin', ['two_factor_confirmed_at' => null]);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
        $user->refresh();
        $secret = decrypt($user->two_factor_secret);
        $this->assertNotSame($secret, $user->two_factor_secret);
        $this->post('/user/confirmed-two-factor-authentication', ['code' => 'invalid'])->assertSessionHasErrors();
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post('/user/confirmed-two-factor-authentication', ['code' => $code])->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.mfa_confirmed']);
    }

    public function test_mfa_login_challenges_and_recovery_code_cannot_be_reused(): void
    {
        $user = $this->account();
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $recovery = $user->recoveryCodes()[0];
        $this->post('/login', ['email' => $user->email, 'password' => 'A-long-test-password-42'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recovery, $user->fresh()->recoveryCodes());
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'A-long-test-password-42']);
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_security_secrets_require_recent_password_confirmation(): void
    {
        $this->actingAs($this->account())->get('/account/recovery')->assertRedirect('/user/confirm-password');
        $this->get('/account/security')->assertRedirect('/user/confirm-password');
    }

    public function test_audit_records_are_not_editable_through_the_model_or_routes(): void
    {
        $admin = $this->account('admin');
        $record = AuditLog::create(['user_id' => $admin->id, 'action' => 'test.event', 'entity_type' => 'user', 'created_at' => now()]);
        $this->actingAs($admin)->patch('/audit/'.$record->id, [])->assertNotFound();
        $this->delete('/audit/'.$record->id)->assertNotFound();
        $this->expectException(\LogicException::class);
        $record->update(['action' => 'tampered']);
    }

    public function test_html_is_escaped_and_private_files_have_no_public_route(): void
    {
        $admin = $this->account('admin', ['name' => '<script>alert(1)</script>']);
        $this->actingAs($admin)->get('/users')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->get('/storage/private/secret.pdf')->assertNotFound();
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->get('/dashboard')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_audit_filters_and_account_search_use_database_records(): void
    {
        $admin = $this->account('admin');
        $this->account(attributes: ['name' => 'Visible Search Employee']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'user.created', 'entity_type' => 'user', 'reason' => 'Find this event', 'created_at' => now()]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'auth.logout', 'entity_type' => 'user', 'reason' => 'Exclude this event', 'created_at' => now()]);
        $this->actingAs($admin)->get('/users?search=Visible')->assertOk()->assertSee('Visible Search Employee');
        $this->get('/audit?action=user.created')->assertOk()->assertSee('Find this event')->assertDontSee('Exclude this event');
        $this->get('/settings')->assertOk()->assertSee('Not configured');
        $this->get('/audit?to='.now()->format('Y-m-d'))->assertOk();
        $this->get('/audit?from=2026-11-23&to=2026-11-01')->assertSessionHasErrors('to');
    }

    public function test_database_rejects_direct_audit_updates_and_deletes(): void
    {
        $record = AuditLog::create(['action' => 'test.event', 'entity_type' => 'system', 'created_at' => now()]);
        foreach (['update', 'delete'] as $operation) {
            try {
                $query = DB::table('audit_logs')->where('id', $record->id);
                $operation === 'update' ? $query->update(['action' => 'tampered']) : $query->delete();
                $this->fail('Audit mutation was allowed.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Audit records cannot be', $exception->getMessage());
            }
        }
        $this->assertDatabaseHas('audit_logs', ['id' => $record->id, 'action' => 'test.event']);
    }

    public function test_disabled_account_cannot_complete_pending_mfa_challenge(): void
    {
        $user = $this->account();
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'A-long-test-password-42'])->assertRedirect('/two-factor-challenge');
        $user->forceFill(['is_active' => false])->save();
        $this->post('/two-factor-challenge', ['recovery_code' => $user->recoveryCodes()[0]])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_csrf_rejects_form_submission_without_token_outside_test_bypass(): void
    {
        $this->app['env'] = 'local';
        try {
            $this->post('/login', ['email' => 'test@example.test', 'password' => 'irrelevant'])->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
