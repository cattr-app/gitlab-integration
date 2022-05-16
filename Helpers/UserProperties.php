<?php

namespace Modules\GitlabIntegration\Helpers;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserProperties
{
    public const API_KEY = 'GITLAB_APIKEY';

    public static function getApiKey(User $user): ?string
    {
        return optional($user->properties()->firstWhere('name', '=', self::API_KEY))->value;
    }

    public static function setApiKey(User $user, string $key): Model
    {
        return $user->properties()->updateOrCreate(['name' => self::API_KEY], ['value' => $key]);
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
