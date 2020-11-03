<?php

namespace Modules\GitlabIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\GitlabIntegration\Http\Requests\Setting\UpdateSettingsRequest;
use Modules\GitlabIntegration\Services\SettingsService;

class SettingsController extends Controller
{
    /**
     * @var SettingsService
     */
    protected SettingsService $settings;

    /**
     * SettingsController constructor.
     * @param $settings
     */
    public function __construct(SettingsService $settings)
    {
        parent::__construct();

        $this->settings = $settings;
    }

    /**
     * Returns controller rules.
     *
     * @return array
     */
    public static function getControllerRules(): array
    {
        return [
            'index' => 'integration.gitlab-settings',
            'update' => 'integration.gitlab-settings'
        ];
    }

    /**
     * Get all settings.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $settings = $this->settings->all();

        return new JsonResponse([
            'data' => $settings
        ]);
    }

    /**
     * Update the settings.
     *
     * @param UpdateSettingsRequest $request
     * @return JsonResponse
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = $this->settings->set($request->validated());

        return new JsonResponse([
            'data' => $settings
        ]);
    }
}
