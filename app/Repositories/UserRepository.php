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
     * Revoke every token the user holds — used when a password changes.
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }
}
