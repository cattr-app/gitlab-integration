<?php

namespace Modules\GitlabIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use Gitlab\Client;
use Gitlab\ResultPager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\GitlabIntegration\Helpers\UserProperties;
use Modules\GitlabIntegration\Services\SettingsService;
use Throwable;

class UserSettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
    ) {
    }

    public static function getControllerRules(): array
    {
        return [
            'index' => 'integration.gitlab',
            'update' => 'integration.gitlab',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $apiKey = UserProperties::getApiKey($request->user());
        $hiddenKey = $apiKey
            ? preg_replace('/^(.{4}).*(.{4})$/', '$1 ********* $2', $apiKey)
            : $apiKey;

        return responder()->success([
                'api_key' => $hiddenKey,
                'enabled' => $this->settings->isEnabled(),
        ])->respond();
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'api_key' => 'string|nullable'
        ]);

        $apiKey = UserProperties::getApiKey($request->user());
        if ($apiKey && empty(trim($request->input('api_key')))) {
            UserProperties::removeApiKey($request->user());
            return responder()->success([
                    'enabled' => $this->settings->isEnabled(),
                    'api_key' => '',
            ])->respond();
        }

        if (strpos(request('api_key'), '*')) {
            return responder()->success([
                'enabled' => $this->settings->isEnabled(),
                'api_key' => preg_replace('/^(.{4}).*(.{4})$/', '$1 ********* $2', $apiKey),
            ])->respond();
        }

        try {
            $client = new Client();
            $client->setUrl($this->settings->getApiUrl());
            $client->authenticate($request->input('api_key'), Client::AUTH_HTTP_TOKEN);

            $fetcher = new ResultPager($client);
            $fetcher->fetch($client->users(), 'me');
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'api_key' => __('Invalid API key.'),
            ]);
        }

        UserProperties::setApiKey($request->user(), request('api_key'));

        return responder()->success([
            'enabled' => $this->settings->isEnabled(),
            'api_key' => preg_replace('/^(.{4}).*(.{4})$/', '$1 ********* $2', request('api_key')),
        ])->respond();
    }
}
