<?php
namespace App\Services;

use App\Exceptions\UserNotFoundException;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserService
{
    protected const CACHE_TTL           = 3600;
    protected const CACHE_KEY_PREFIX    = 'user:';
    protected const CACHE_ALL_USERS_KEY = 'users:all';
    protected const CACHE_EMAIL_PREFIX  = 'user:email:';

    public function createUser(array $data): User
    {
        try {
            $data['password'] = Hash::make($data['password']);
            $user             = User::create($data);

            $this->cacheUser($user);

            $this->clearUsersCache();

            return $user;
        } catch (Exception $e) {
            Log::error('Failed to create user', [
                'error' => $e->getMessage(),
                'data'  => array_except($data, ['password']),
            ]);
            throw $e;
        }
    }

    public function updateUser(int $id, array $data): User
    {
        try {
            $user = $this->getUser($id);

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $oldEmail = $user->email;

            $user->update($data);

            $this->clearUserCache($id);
            if (isset($data['email']) && $data['email'] !== $oldEmail) {
                $this->clearUserEmailCache($oldEmail);
            }

            $this->cacheUser($user->fresh());
            $this->clearUsersCache();

            return $user;
        } catch (Exception $e) {
            Log::error('Failed to update user', [
                'error'   => $e->getMessage(),
                'user_id' => $id,
                'data'    => array_except($data, ['password']),
            ]);
            throw $e;
        }
    }

    public function getUser(int $id): User
    {
        try {
            return Cache::remember(
                $this->getUserCacheKey($id),
                self::CACHE_TTL,
                function () use ($id) {
                    $user = User::find($id);

                    if (! $user) {
                        throw new UserNotFoundException("User not found with ID: {$id}");
                    }

                    return $user;
                }
            );
        } catch (Exception $e) {
            if ($e instanceof UserNotFoundException) {
                throw $e;
            }
            Log::error('Cache retrieval failed for user', [
                'error'   => $e->getMessage(),
                'user_id' => $id,
            ]);
            throw $e;
        }
    }

    public function getUserByEmail(string $email): ?User
    {
        try {
            return Cache::remember(
                $this->getUserEmailCacheKey($email),
                self::CACHE_TTL,
                function () use ($email) {
                    return User::where('email', $email)->first();
                }
            );
        } catch (Exception $e) {
            Log::error('Cache retrieval failed for user email', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);
            throw $e;
        }
    }

    public function getAllUsers(): Collection
    {
        try {
            return Cache::remember(
                self::CACHE_ALL_USERS_KEY,
                self::CACHE_TTL,
                function () {
                    return User::all();
                }
            );
        } catch (Exception $e) {
            Log::error('Cache retrieval failed for all users', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function cacheUser(User $user): void
    {
        try {
            Cache::put(
                $this->getUserCacheKey($user->id),
                $user,
                self::CACHE_TTL
            );

            Cache::put(
                $this->getUserEmailCacheKey($user->email),
                $user,
                self::CACHE_TTL
            );
        } catch (Exception $e) {
            Log::error('Failed to cache user', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id,
            ]);
        }
    }

    protected function clearUserCache(int $id): void
    {
        try {
            Cache::forget($this->getUserCacheKey($id));
        } catch (Exception $e) {
            Log::error('Failed to clear user cache', [
                'error'   => $e->getMessage(),
                'user_id' => $id,
            ]);
        }
    }

    protected function clearUserEmailCache(string $email): void
    {
        try {
            Cache::forget($this->getUserEmailCacheKey($email));
        } catch (Exception $e) {
            Log::error('Failed to clear user email cache', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);
        }
    }

    protected function clearUsersCache(): void
    {
        try {
            Cache::forget(self::CACHE_ALL_USERS_KEY);
        } catch (Exception $e) {
            Log::error('Failed to clear users cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getUserCacheKey(int $id): string
    {
        return self::CACHE_KEY_PREFIX . $id;
    }

    protected function getUserEmailCacheKey(string $email): string
    {
        return self::CACHE_EMAIL_PREFIX . md5($email);
    }
}
