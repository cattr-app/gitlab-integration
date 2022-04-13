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
        protected UserProperties $userProperties,
        protected Client $client,
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

    public function index(Request $request): array
    {
        $userId = $request->user()->id;
        $apiKey = $this->userProperties->getApiKey($userId);
        $hiddenKey = (bool)$apiKey
            ? preg_replace('/^(.{4}).*(.{4})$/i', '$1 ********* $2', $apiKey)
            : $apiKey;

        $integrationEnabled = $this->settings->isEnabled();

        return [
            'data' => [
                'api_key' => $hiddenKey,
                'enabled' => $integrationEnabled,
            ]
        ];
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'api_key' => 'string|nullable'
        ]);

        $userId = $request->user()->id;
        $apiKey = $this->userProperties->getApiKey($userId) ?? null;
        if (empty(trim($request->input('api_key')))) {
            if ($apiKey) {
                $this->userProperties->removeApiKey($userId);
                return new JsonResponse([
                    'data' => [
                        'enabled' => $this->settings->isEnabled(),
                        'api_key' => '',
                    ],
                ]);
            }
        }

        if (strpos(request('api_key'), '*')) {
            return new JsonResponse([
                'data' => [
                    'enabled' => $this->settings->isEnabled(),
                    'api_key' => preg_replace('/^(.{4}).*(.{4})$/i', '$1 ********* $2', $apiKey),
                ],
            ]);
        }

        try {
            $client = Client::create($this->settings->getApiUrl())
                ->authenticate($request->input('api_key'), Client::AUTH_URL_TOKEN);

            $fetcher = new ResultPager($client);
            $fetcher->fetch($client->api('users'), 'me');
        } catch (Throwable $throwable) {
            throw ValidationException::withMessages([
                'api_key' => __('Invalid API key.'),
            ]);
        }

        $token = $this->userProperties->setApiKey(auth()->user()->id, request('api_key'));

        return new JsonResponse([
            'data' => [
                'enabled' => $this->settings->isEnabled(),
                'api_key' => preg_replace('/^(.{4}).*(.{4})$/i', '$1 ********* $2', $token['value']),
            ],
        ]);
    }
}
