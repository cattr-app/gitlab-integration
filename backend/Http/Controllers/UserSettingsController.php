<?php

namespace Modules\GitlabIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use Gitlab\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\GitlabIntegration\Helpers\UserProperties;
use Modules\GitlabIntegration\Http\Requests\UpdateUserSettingsRequest;
use Modules\GitlabIntegration\Services\SettingsService;
use Throwable;

class UserSettingsController extends Controller
{
    public function __construct(protected SettingsService $settings)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return responder()->success([
            'api_key' => UserProperties::getApiKeyStub($request->user()),
            'enabled' => $this->settings->isEnabled(),
        ])->respond();
    }

    /**
     * @throws ValidationException
     */
    public function update(UpdateUserSettingsRequest $request): JsonResponse
    {
        $apiKey = UserProperties::getApiKey($request->user());
        if ($apiKey && empty($request->input('api_key'))) {
            UserProperties::removeApiKey($request->user());
            return responder()->success()->respond(204);
        }

        if (strpos(request('api_key'), '*')) {
            return responder()->success()->respond(304);
        }

        try {
            $client = new Client();
            $client->setUrl($this->settings->getApiUrl());
            $client->authenticate(request('api_key'), Client::AUTH_HTTP_TOKEN);
            $client->users()->me();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'api_key' => __('Invalid API key.'),
            ]);
        }

        UserProperties::setApiKey($request->user(), request('api_key'));

        return responder()->success()->respond(204);
    }
}
