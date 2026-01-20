<?php

namespace App\Validators;

/**
 * Eleven Labs Voice Agent Validator
 */
class ElevenLabsValidator extends VoiceAgentProviderValidator
{
    protected function getCustomValidationRules(): array
    {
        return [
            'service_value' => 'required|string|regex:/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',
            'username' => 'required|string|max:255',
        ];
    }

    protected function performCustomValidation($validator, array $data): void
    {
        if (isset($data['service_value']) && !$this->validateServiceValue($data['service_value'])) {
            $validator->errors()->add('service_value', 'Eleven Labs voice ID must be a valid UUID.');
        }
    }

    protected function getCustomMessages(): array
    {
        return [
            'service_value.required' => 'Eleven Labs voice ID is required.',
            'service_value.regex' => 'Eleven Labs voice ID must be a valid UUID format.',
            'username.required' => 'Eleven Labs API key is required.',
        ];
    }
}