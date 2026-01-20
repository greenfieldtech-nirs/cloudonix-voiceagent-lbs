<?php

namespace App\Validators;

use App\Enums\VoiceAgentProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Base Voice Agent Provider Validator
 *
 * Provides common validation logic and interface for all provider validators.
 */
abstract class VoiceAgentProviderValidator
{
    protected VoiceAgentProvider $provider;

    public function __construct(VoiceAgentProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Get the provider this validator handles
     */
    public function getProvider(): VoiceAgentProvider
    {
        return $this->provider;
    }

    /**
     * Validate the complete provider configuration
     *
     * @param array $data Configuration data (service_value, username, password, metadata)
     * @return array Validated and sanitized data
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        // Get base validation rules
        $rules = $this->provider->getValidationRules();

        // Add custom validation rules
        $rules = array_merge($rules, $this->getCustomValidationRules());

        // Create validator
        $validator = Validator::make($data, $rules, $this->getCustomMessages());

        // Add custom validation logic
        $validator->after(function ($validator) use ($data) {
            $this->performCustomValidation($validator, $data);
        });

        // Validate and return
        return $validator->validate();
    }

    /**
     * Get additional custom validation rules
     */
    protected function getCustomValidationRules(): array
    {
        return [];
    }

    /**
     * Get custom validation error messages
     */
    protected function getCustomMessages(): array
    {
        return [];
    }

    /**
     * Perform additional custom validation logic
     */
    protected function performCustomValidation($validator, array $data): void
    {
        // Override in subclasses for custom logic
    }

    /**
     * Validate service value format
     */
    protected function validateServiceValue(string $value): bool
    {
        return $this->provider->validateServiceValue($value);
    }

    /**
     * Factory method to create validator for a provider
     */
    public static function createForProvider(VoiceAgentProvider $provider): self
    {
        return match($provider) {
            VoiceAgentProvider::VAPI => new VapiValidator($provider),
            VoiceAgentProvider::SYNTHFLOW => new SynthflowValidator($provider),
            VoiceAgentProvider::ELEVENLABS => new ElevenLabsValidator($provider),
            default => new GenericValidator($provider),
        };
    }
}