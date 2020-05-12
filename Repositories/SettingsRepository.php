<?php

namespace Modules\GitlabIntegration\Repositories;

use App\Models\Property;
use Modules\GitlabIntegration\Entities\SettingsEntity;

class SettingsRepository
{
    /**
     * @var Property
     */
    protected Property $model;
    /**
     * @var SettingsEntity
     */
    protected SettingsEntity $settingsEntity;

    /**
     * SettingsRepository constructor.
     * @param Property $property
     * @param SettingsEntity $settingsEntity
     */
    public function __construct(Property $model, SettingsEntity $settingsEntity)
    {
        $this->model = $model;
        $this->settingsEntity = $settingsEntity;
    }

    /**
     * Returns the setting value by name.
     *
     * @param $propertyName
     * @return mixed
     */
    public function getByPropertyName($propertyName)
    {
        $property = $this->model->where([
            'entity_type' => $this->model::COMPANY_CODE,
            'name' => $propertyName
        ])
            ->first();

        return $property->value; 
    }
}
