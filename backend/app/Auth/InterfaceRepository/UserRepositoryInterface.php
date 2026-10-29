<?php

namespace App\Auth\InterfaceRepository;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @param  array{name: string, email: string, password: string, avatar?: string|null}  $attributes  password is hashed by the model cast
     */
    public function create(array $attributes): User;

    public function markEmailVerified(User $user): void;

    /** Sets the password (hashed by the model cast). */
    public function updatePassword(User $user, string $plainPassword): void;

    /** Stamps `last_active_at` with the current time without touching `updated_at`. */
    public function markActive(User $user): void;
}
