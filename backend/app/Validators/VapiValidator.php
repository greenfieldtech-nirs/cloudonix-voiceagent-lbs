<?php

namespace App\Validators;

/**
 * VAPI Voice Agent Validator
 */
class VapiValidator extends VoiceAgentProviderValidator
{
    protected function performCustomValidation($validator, array $data): void
    {
        if (isset($data['service_value']) && !$this->validateServiceValue($data['service_value'])) {
            $validator->errors()->add('service_value', 'Invalid VAPI assistant ID format.');
        }
    }

    protected function getCustomMessages(): array
    {
        return [
            'service_value.required' => 'VAPI assistant ID is required.',
            'service_value.max' => 'VAPI assistant ID must not exceed 500 characters.',
        ];
    }
}