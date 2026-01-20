<?php

namespace App\Enums;

/**
 * Voice Agent Provider Enum
 *
 * Defines all supported AI voice agent providers for the Cloudonix Voice Application Tool.
 * Each provider has specific validation requirements and CXML parameters.
 */
enum VoiceAgentProvider: string
{
    case VAPI = 'vapi';
    case SYNTHFLOW = 'synthflow';
    case DASHA = 'dasha';
    case SUPERDASH_AI = 'superdash.ai';
    case ELEVENLABS = 'elevenlabs';
    case DEEPVOX = 'deepvox';
    case RELAYHAWK = 'relayhawk';
    case VOICEHUB = 'voicehub';
    case RETELL_UDP = 'retell-udp';
    case RETELL_TCP = 'retell-tcp';
    case RETELL_TLS = 'retell-tls';
    case RETELL = 'retell';
    case FONIO = 'fonio';
    case SIGMAMIND = 'sigmamind';
    case MODON = 'modon';
    case PURETALK = 'puretalk';
    case MILLIS_US = 'millis-us';
    case MILLIS_EU = 'millis-eu';

    /**
     * Get all supported providers as an array
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if provider requires authentication
     */
    public function requiresAuthentication(): bool
    {
        return match($this) {
            self::SYNTHFLOW, self::SUPERDASH_AI, self::ELEVENLABS => true,
            default => false,
        };
    }

    /**
     * Get the username field label for this provider
     */
    public function getUsernameLabel(): ?string
    {
        return match($this) {
            self::SYNTHFLOW => 'API Key',
            self::SUPERDASH_AI => 'API Key',
            self::ELEVENLABS => 'API Key',
            default => null,
        };
    }

    /**
     * Get the password field label for this provider
     */
    public function getPasswordLabel(): ?string
    {
        return match($this) {
            self::SYNTHFLOW => 'Secret Key',
            self::SUPERDASH_AI => 'Secret Key',
            default => null,
        };
    }

    /**
     * Get the service value field description
     */
    public function getServiceValueDescription(): string
    {
        return match($this) {
            self::VAPI => 'VAPI assistant ID',
            self::SYNTHFLOW => 'Synthflow endpoint URL',
            self::DASHA => 'Dasha application endpoint',
            self::SUPERDASH_AI => 'Superdash endpoint',
            self::ELEVENLABS => 'ElevenLabs voice ID or endpoint',
            self::DEEPVOX => 'Deepvox configuration endpoint',
            self::RELAYHAWK => 'Relayhawk agent endpoint',
            self::VOICEHUB => 'VoiceHub service endpoint',
            self::RETELL_UDP => 'Retell UDP endpoint',
            self::RETELL_TCP => 'Retell TCP endpoint',
            self::RETELL_TLS => 'Retell TLS endpoint',
            self::RETELL => 'Retell default endpoint',
            self::FONIO => 'Fonio service endpoint',
            self::SIGMAMIND => 'Sigma Mind endpoint',
            self::MODON => 'Modon service endpoint',
            self::PURETALK => 'PureTalk endpoint',
            self::MILLIS_US => 'Millis US endpoint',
            self::MILLIS_EU => 'Millis EU endpoint',
        };
    }

    /**
     * Get display name for the provider
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::VAPI => 'VAPI',
            self::SYNTHFLOW => 'Synthflow',
            self::DASHA => 'Dasha',
            self::SUPERDASH_AI => 'Superdash',
            self::ELEVENLABS => 'Eleven Labs',
            self::DEEPVOX => 'Deepvox',
            self::RELAYHAWK => 'Relay Hawk',
            self::VOICEHUB => 'Voice Hub',
            self::RETELL_UDP => 'Retell (UDP)',
            self::RETELL_TCP => 'Retell (TCP)',
            self::RETELL_TLS => 'Retell (TLS)',
            self::RETELL => 'Retell',
            self::FONIO => 'Fonio',
            self::SIGMAMIND => 'Sigma Mind',
            self::MODON => 'Modon',
            self::PURETALK => 'PureTalk',
            self::MILLIS_US => 'Millis (US)',
            self::MILLIS_EU => 'Millis (EU)',
        };
    }

    /**
     * Get validation rules for this provider
     */
    public function getValidationRules(): array
    {
        $rules = [
            'service_value' => 'required|string|max:500',
        ];

        if ($this->requiresAuthentication()) {
            $rules['username'] = 'required|string|max:255';

            if ($this === self::SYNTHFLOW || $this === self::SUPERDASH_AI) {
                $rules['password'] = 'required|string|max:255';
            }
        }

        return $rules;
    }

    /**
     * Validate provider-specific requirements
     */
    public function validateServiceValue(string $value): bool
    {
        return match($this) {
            self::VAPI => $this->validateVapiValue($value),
            self::SYNTHFLOW => $this->validateSynthflowValue($value),
            self::ELEVENLABS => $this->validateElevenLabsValue($value),
            default => $this->validateGenericUrl($value),
        };
    }

    /**
     * Validate VAPI assistant ID
     */
    private function validateVapiValue(string $value): bool
    {
        // VAPI assistant IDs are typically UUIDs or simple identifiers
        return preg_match('/^[a-zA-Z0-9_-]+$/', $value) && strlen($value) > 0;
    }

    /**
     * Validate Synthflow endpoint URL
     */
    private function validateSynthflowValue(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false &&
               str_contains($value, 'synthflow');
    }

    /**
     * Validate Eleven Labs voice ID
     */
    private function validateElevenLabsValue(string $value): bool
    {
        // Eleven Labs voice IDs are typically UUIDs
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $value);
    }

    /**
     * Validate generic URL
     */
    private function validateGenericUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}