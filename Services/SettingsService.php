<?php

namespace Modules\GitlabIntegration\Services;

use Modules\GitlabIntegration\Entities\SettingsEntity;
use Modules\GitlabIntegration\Repositories\SettingsRepository;

class SettingsService
{
    /**
     * @var SettingsRepository
     */
    protected SettingsRepository $settingsRepository;

    /**
     * @var SettingsEntity
     */
    protected SettingsEntity $settingsEntity;

    /**
     * SettingsService constructor.
     * @param SettingsRepository $settingsRepository
     * @param SettingsEntity $settingsEntity
     */
    public function __construct(SettingsRepository $settingsRepository, SettingsEntity $settingsEntity)
    {
        $this->settingsRepository = $settingsRepository;
        $this->settingsEntity = $settingsEntity;
    }

    /**
     * @return mixed
     */
    public function getTimeSyncPeriod()
    {
        return $this->settingsRepository->getByPropertyName(SettingsEntity::TIME_SYNC_PERIOD_KEY);
    }
}
