<?php

namespace App\Http\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\DTOs\Responses\UserResponse;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Auth\Support\AuthCookies;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** The signed-in user's own account: profile, settings, password, photo and linked sign-ins. */
class MeController extends Controller
{
    private const PRIVACY_KEYS = ['ratings', 'reviews', 'lists', 'watchlist', 'history', 'following'];

    private const INTEGRATION_KEYS = ['calendar', 'location', 'weather', 'digest'];

    private const NOTIFY_KEYS = ['nights', 'mentions', 'social', 'releases'];

    /** GET /api/me — account details plus everything Settings and the header need. */
    public function show(CurrentUser $current): JsonResponse
    {
        return response()->json($this->payload($current->user()));
    }

    /** PATCH /api/me — Settings › Profile and the visibility switch. */
    public function update(Request $request, CurrentUser $current, EmailVerificationServiceInterface $verification): JsonResponse
    {
        $user = $current->user();
        if ($request->filled('username')) {
            $request->merge(['username' => strtolower(ltrim((string) $request->input('username'), '@'))]);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'regex:/^[a-z0-9_.]{3,20}$/i', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:160'],
            'profile_visibility' => ['sometimes', 'required', Rule::in(['public', 'followers', 'private'])],
        ]);

        $emailChanged = isset($data['email']) && strcasecmp($data['email'], $user->email) !== 0;
        $user->fill($data);
        if ($emailChanged) {
            // A new address has to be confirmed again.
            $user->email_verified_at = null;
        }
        $user->save();

        if ($emailChanged) {
            $verification->send($user);
        }

        return response()->json($this->payload($user->refresh()));
    }

    /** PATCH /api/me/preferences — favourite genres and the switches in Settings / Notifications. */
    public function updatePreferences(Request $request, CurrentUser $current): JsonResponse
    {
        $data = $request->validate([
            'favorite_genres' => ['sometimes', 'array', 'max:10'],
            'favorite_genres.*' => ['string', 'max:40'],
            'privacy' => ['sometimes', 'array'],
            'privacy.*' => ['boolean'],
            'integrations' => ['sometimes', 'array'],
            'integrations.*' => ['boolean'],
            'notifications' => ['sometimes', 'array'],
            'notifications.*' => ['boolean'],
        ]);

        $user = $current->user();
        $preferences = $user->preferences()->firstOrNew();
        $settings = $preferences->settings ?? [];

        foreach (['privacy' => self::PRIVACY_KEYS, 'integrations' => self::INTEGRATION_KEYS, 'notifications' => self::NOTIFY_KEYS] as $group => $keys) {
            if (isset($data[$group])) {
                $settings[$group] = [...($settings[$group] ?? []), ...array_intersect_key($data[$group], array_flip($keys))];
            }
        }

        $preferences->settings = $settings;
        if (array_key_exists('favorite_genres', $data)) {
            $preferences->favorite_genres = array_values($data['favorite_genres']);
        }
        $preferences->save();

        return response()->json($this->payload($user->refresh()));
    }

    /** PUT /api/me/password — signs out every other device by revoking their refresh tokens. */
    public function updatePassword(Request $request, CurrentUser $current, RefreshTokenRepositoryInterface $tokens): JsonResponse
    {
        $user = $current->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Your current password is incorrect.', 'errors' => ['current_password' => ['Your current password is incorrect.']]], 422);
        }

        $user->forceFill(['password' => $data['password']])->save();
        // The access cookie stays valid until it expires (15 min); every refresh token is gone.
        $tokens->revokeAllForUser($user->id);

        return response()->json(['message' => 'Password updated. Other devices have been signed out.']);
    }

    /** POST /api/me/avatar — multipart `avatar`, stored on the public disk. */
    public function uploadAvatar(Request $request, CurrentUser $current): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:2048']]);
        $user = $current->user();

        $this->deleteStoredAvatar($user);
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->forceFill(['avatar' => Storage::disk('public')->url($path)])->save();

        return response()->json($this->payload($user));
    }

    /** DELETE /api/me/avatar */
    public function deleteAvatar(CurrentUser $current): JsonResponse
    {
        $user = $current->user();
        $this->deleteStoredAvatar($user);
        $user->forceFill(['avatar' => null])->save();

        return response()->json($this->payload($user));
    }

    /**
     * DELETE /api/me/social/{provider}. Accounts created through a provider have a random
     * password, so the owner can still get back in with email + "Forgot password".
     */
    public function disconnectSocial(string $provider, CurrentUser $current): JsonResponse
    {
        $user = $current->user();
        $user->socialAccounts()->where('provider', $provider)->delete();

        return response()->json($this->payload($user));
    }

    /** DELETE /api/me — removes the account and everything it owns (FKs cascade). */
    public function destroy(Request $request, CurrentUser $current, AuthCookies $cookies): JsonResponse
    {
        $user = $current->user();
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json(['message' => 'That password is incorrect.', 'errors' => ['password' => ['That password is incorrect.']]], 422);
        }

        $this->deleteStoredAvatar($user);
        $user->delete();

        return $cookies->clear(response()->json(['message' => 'Account deleted.']));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        $user->loadMissing(['preferences', 'socialAccounts']);
        $settings = $user->preferences?->settings ?? [];

        $privacy = [];
        foreach (self::PRIVACY_KEYS as $key) {
            $privacy[$key] = $user->shares($key);
        }

        return [
            'user' => UserResponse::fromModel($user),
            'preferences' => [
                'favorite_genres' => $user->preferences?->favorite_genres ?? [],
                'privacy' => $privacy,
                'integrations' => [...['calendar' => true, 'location' => true, 'weather' => true, 'digest' => false], ...($settings['integrations'] ?? [])],
                'notifications' => [...['nights' => true, 'mentions' => true, 'social' => true, 'releases' => false], ...($settings['notifications'] ?? [])],
            ],
            'connected' => $user->socialAccounts->mapWithKeys(fn ($account) => [$account->provider => $account->created_at?->toJSON()]),
            'counts' => [
                'unread_notifications' => $user->notifications()->whereNull('read_at')->count(),
                'followers' => $user->followers()->count(),
                'following' => $user->following()->count(),
            ],
        ];
    }

    private function deleteStoredAvatar(User $user): void
    {
        $prefix = Storage::disk('public')->url('avatars/');
        if ($user->avatar && str_starts_with($user->avatar, $prefix)) {
            Storage::disk('public')->delete('avatars/'.substr($user->avatar, strlen($prefix)));
        }
    }
}
