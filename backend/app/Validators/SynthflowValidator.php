<?php

namespace App\Validators;

/**
 * Synthflow Voice Agent Validator
 */
class SynthflowValidator extends VoiceAgentProviderValidator
{
    protected function getCustomValidationRules(): array
    {
        return [
            'service_value' => 'required|url|regex:/synthflow/',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
        ];
    }

    protected function performCustomValidation($validator, array $data): void
    {
        if (isset($data['service_value']) && !$this->validateServiceValue($data['service_value'])) {
            $validator->errors()->add('service_value', 'Synthflow endpoint URL must be a valid URL containing "synthflow".');
        }
    }

    protected function getCustomMessages(): array
    {
        return [
            'service_value.required' => 'Synthflow endpoint URL is required.',
            'service_value.url' => 'Synthflow endpoint must be a valid URL.',
            'service_value.regex' => 'Synthflow endpoint URL must contain "synthflow".',
            'username.required' => 'Synthflow API key is required.',
            'password.required' => 'Synthflow secret key is required.',
        ];
    }
}