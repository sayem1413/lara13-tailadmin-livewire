<?php

namespace App\Http\Requests\Setting;

use App\Services\Setting\SettingService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in the controller via
     * Gate::authorize(), so every incoming request reaches validation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Dynamic, schema-driven from config/settings.php - see
     * SettingService::validationRules(), shared with the Livewire form so
     * both validate the same fields the same way.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return app(SettingService::class)->validationRules();
    }
}
