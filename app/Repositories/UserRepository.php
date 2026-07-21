<?php

namespace App\Repositories;

use App\Models\User;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository
{
    protected function model(): string
    {
        return User::class;
    }

    /**
     * Resolve a user by email within a single role.
     *
     * The four roles share the `users` table, so every credential lookup must
     * be scoped by role — otherwise a customer's email would resolve at the
     * admin endpoints. Callers pass an already-normalized (lowercased) email.
     */
    public function findByEmailAndRole(string $email, string $role): ?User
    {
        return $this->query()
            ->where('role', $role)
            ->where('email', $email)
            ->first();
    }

    /**
     * Reload a user with the country/county/city rows their ids point at.
     *
     * Eager loading here rather than letting a Resource touch `$user->country`
     * is what keeps the profile endpoints at a fixed query count — and `load()`
     * re-queries every call, so a profile update that moved the user to another
     * city cannot answer with the previous city still attached.
     */
    public function withLocation(User $user): User
    {
        return $user->load(['country', 'county', 'city']);
    }

    /**
     * Issue a Sanctum personal access token and return its plaintext value.
     *
     * The plaintext is only ever available here, at issuance — it is never
     * readable again from the stored hash.
     */
    public function issueToken(User $user, string $name): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    /**
     * Revoke the token that authenticated the current request.
     */
    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Revoke every token the user holds — used when a password is reset by
     * someone who was locked out, so any session an attacker holds dies too.
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Revoke every token except the one authenticating this request — used
     * when a signed-in user changes their own password, which should sign
     * out their other devices without signing out the one they are using.
     */
    public function revokeOtherTokens(User $user): void
    {
        $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()->getKey())
            ->delete();
    }
}
