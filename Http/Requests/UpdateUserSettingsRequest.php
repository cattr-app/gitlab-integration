<?php

namespace Modules\GitlabIntegration\Http\Requests;

use App\Http\Requests\CattrFormRequest;

class UpdateUserSettingsRequest extends CattrFormRequest
{
    public function _authorize(): bool
    {
        return true;
    }

    public function _rules(): array
    {
        return [
            'api_key' => 'string|nullable',
        ];
    }
}
