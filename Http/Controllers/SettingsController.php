<?php

namespace Modules\GitlabIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\GitlabIntegration\Http\Requests\UpdateCompanySettingsRequest;
use Modules\GitlabIntegration\Services\SettingsService;

class SettingsController extends Controller
{
    public function __construct(protected SettingsService $settings)
    {
    }

    public function index(): JsonResponse
    {
        return responder()->success($this->settings->all())->respond();
    }

    public function update(UpdateCompanySettingsRequest $request): JsonResponse
    {
        $this->settings->set($request->validated());

        return responder()->success()->respond(204);
    }
}
