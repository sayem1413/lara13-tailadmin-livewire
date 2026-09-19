<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferenceRequest extends FormRequest
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
     * Dynamic, schema-driven from config/notification_types.php - every
     * configured type/channel pair is an optional boolean toggle.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (config('notification_types', []) as $type => $definition) {
            foreach (array_keys($definition['channels']) as $channel) {
                $rules["values.{$type}.{$channel}"] = ['boolean'];
            }
        }

        return $rules;
    }
}
