<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $username = 'pemilik_zufri'): User
    {
        return User::create([
            'nama_pemilik' => 'Zufri Padang',
            'nama_toko' => 'UD. Zufri',
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => Hash::make('Password123'),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/profil')->assertRedirect('/login');
        $this->put('/profil', [])->assertRedirect('/login');
        $this->put('/profil/password', [])->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = $this->user();
        $this->actingAs($user)
            ->get('/profil')
            ->assertOk()
            ->assertSee('Profil Pengguna')
            ->assertSee($user->nama_pemilik)
            ->assertSee($user->nama_toko);
    }

    public function test_user_can_update_profile_info(): void
    {
        $user = $this->user();
        $response = $this->actingAs($user)->put('/profil', [
            'nama_pemilik' => 'Halijah Yuhyi Pardede',
            'nama_toko' => 'UD. Zufri Retail',
            'username' => 'halijah',
            'email' => 'halijah@example.test',
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id_user' => $user->id_user,
            'nama_pemilik' => 'Halijah Yuhyi Pardede',
            'nama_toko' => 'UD. Zufri Retail',
            'username' => 'halijah',
            'email' => 'halijah@example.test',
        ]);
    }

    public function test_profile_update_validates_uniqueness(): void
    {
        $userA = $this->user('usera');
        $userB = $this->user('userb');

        // Keeping own username and email succeeds
        $this->actingAs($userA)->put('/profil', [
            'nama_pemilik' => 'User A Updated',
            'nama_toko' => 'Toko A',
            'username' => 'usera',
            'email' => 'usera@example.test',
        ])->assertSessionHasNoErrors();

        // Taking user B's username or email fails
        $this->actingAs($userA)->put('/profil', [
            'nama_pemilik' => 'User A',
            'nama_toko' => 'Toko A',
            'username' => 'userb',
            'email' => 'userb@example.test',
        ])->assertSessionHasErrors(['username', 'email']);
    }

    public function test_user_can_update_password_with_correct_current_password(): void
    {
        $user = $this->user();
        $response = $this->actingAs($user)->put('/profil/password', [
            'current_password' => 'Password123',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewPassword456', $user->fresh()->password));
    }

    public function test_password_update_rejects_wrong_current_password(): void
    {
        $user = $this->user();
        $response = $this->actingAs($user)->put('/profil/password', [
            'current_password' => 'WrongPassword',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Password123', $user->fresh()->password));
    }
}
