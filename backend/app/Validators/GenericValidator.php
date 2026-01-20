<?php

namespace App\Validators;

/**
 * Generic Voice Agent Validator
 *
 * For providers that don't require special validation logic
 */
class GenericValidator extends VoiceAgentProviderValidator
{
    protected function performCustomValidation($validator, array $data): void
    {
        if (isset($data['service_value']) && !$this->validateServiceValue($data['service_value'])) {
            $validator->errors()->add('service_value', 'Service value must be a valid URL.');
        }
    }

    protected function getCustomMessages(): array
    {
        return [
            'service_value.required' => 'Service endpoint is required.',
            'service_value.max' => 'Service endpoint must not exceed 500 characters.',
        ];
    }
}