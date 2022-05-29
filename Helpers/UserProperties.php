<?php

namespace Modules\GitlabIntegration\Helpers;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserProperties
{
    public const API_KEY = 'GITLAB_APIKEY';

    public static function getApiKeyStub(User $user): ?string
    {
        $apiKey = self::getApiKey($user);
        return $apiKey ? preg_replace('/^(.{4}).*(.{4})$/', '$1 ********* $2', $apiKey) : null;
    }

    public static function getApiKey(User $user): ?string
    {
        return rescue(decrypt(optional($user->properties()->firstWhere('name', '=', self::API_KEY))->value));
    }

    public static function setApiKey(User $user, string $key): Model
    {
        return $user->properties()->updateOrCreate(['name' => self::API_KEY], ['value' => encrypt($key)]);
    }

    public static function removeApiKey(User $user): void
    {
        optional($user->properties()->firstWhere('name', '=', self::API_KEY))
            ->forceDelete();
    }

    public static function getUsersWithApiKeys(): Collection
    {
        return User::active()->whereRelation('properties', 'name', self::API_KEY)->get();
    }
}
