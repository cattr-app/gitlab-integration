<?php

namespace Modules\GitlabIntegration\Http\Requests;

use App\Http\Requests\CattrFormRequest;

class UpdateCompanySettingsRequest extends CattrFormRequest
{
    public function _authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function _rules(): array
    {
        return [
            'enabled' => 'sometimes|required|bool',
            'time_sync_period' => 'sometimes|required|integer',
            'url' => 'sometimes|required|url',
        ];
    }
}
