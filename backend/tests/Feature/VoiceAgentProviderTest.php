<?php

namespace Tests\Feature;

use App\Enums\VoiceAgentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceAgentProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_providers_are_defined()
    {
        $providers = VoiceAgentProvider::all();
        $this->assertCount(18, $providers);
        $this->assertContains('vapi', $providers);
        $this->assertContains('synthflow', $providers);
        $this->assertContains('elevenlabs', $providers);
    }

    public function test_provider_requires_authentication()
    {
        $this->assertTrue(VoiceAgentProvider::SYNTHFLOW->requiresAuthentication());
        $this->assertTrue(VoiceAgentProvider::SUPERDASH_AI->requiresAuthentication());
        $this->assertTrue(VoiceAgentProvider::ELEVENLABS->requiresAuthentication());
        $this->assertFalse(VoiceAgentProvider::VAPI->requiresAuthentication());
        $this->assertFalse(VoiceAgentProvider::DASHA->requiresAuthentication());
    }

    public function test_provider_display_names()
    {
        $this->assertEquals('VAPI', VoiceAgentProvider::VAPI->getDisplayName());
        $this->assertEquals('Synthflow', VoiceAgentProvider::SYNTHFLOW->getDisplayName());
        $this->assertEquals('Eleven Labs', VoiceAgentProvider::ELEVENLABS->getDisplayName());
        $this->assertEquals('Relay Hawk', VoiceAgentProvider::RELAYHAWK->getDisplayName());
        $this->assertEquals('Sigma Mind', VoiceAgentProvider::SIGMAMIND->getDisplayName());
    }

    public function test_provider_field_labels()
    {
        // Synthflow requires API key and secret
        $this->assertEquals('API Key', VoiceAgentProvider::SYNTHFLOW->getUsernameLabel());
        $this->assertEquals('Secret Key', VoiceAgentProvider::SYNTHFLOW->getPasswordLabel());

        // Eleven Labs requires API key only
        $this->assertEquals('API Key', VoiceAgentProvider::ELEVENLABS->getUsernameLabel());
        $this->assertNull(VoiceAgentProvider::ELEVENLABS->getPasswordLabel());

        // VAPI doesn't require authentication
        $this->assertNull(VoiceAgentProvider::VAPI->getUsernameLabel());
        $this->assertNull(VoiceAgentProvider::VAPI->getPasswordLabel());
    }

    public function test_service_value_descriptions()
    {
        $this->assertEquals('VAPI assistant ID', VoiceAgentProvider::VAPI->getServiceValueDescription());
        $this->assertEquals('Synthflow endpoint URL', VoiceAgentProvider::SYNTHFLOW->getServiceValueDescription());
        $this->assertEquals('ElevenLabs voice ID or endpoint', VoiceAgentProvider::ELEVENLABS->getServiceValueDescription());
        $this->assertEquals('Retell UDP endpoint', VoiceAgentProvider::RETELL_UDP->getServiceValueDescription());
    }

    public function test_validation_rules()
    {
        // VAPI - no auth required
        $vapiRules = VoiceAgentProvider::VAPI->getValidationRules();
        $this->assertArrayHasKey('service_value', $vapiRules);
        $this->assertArrayNotHasKey('username', $vapiRules);
        $this->assertArrayNotHasKey('password', $vapiRules);

        // Synthflow - auth required
        $synthflowRules = VoiceAgentProvider::SYNTHFLOW->getValidationRules();
        $this->assertArrayHasKey('service_value', $synthflowRules);
        $this->assertArrayHasKey('username', $synthflowRules);
        $this->assertArrayHasKey('password', $synthflowRules);

        // Eleven Labs - API key required
        $elevenLabsRules = VoiceAgentProvider::ELEVENLABS->getValidationRules();
        $this->assertArrayHasKey('service_value', $elevenLabsRules);
        $this->assertArrayHasKey('username', $elevenLabsRules);
        $this->assertArrayNotHasKey('password', $elevenLabsRules);
    }

    public function test_service_value_validation()
    {
        // VAPI - valid assistant ID
        $this->assertTrue(VoiceAgentProvider::VAPI->validateServiceValue('assistant_123'));
        $this->assertTrue(VoiceAgentProvider::VAPI->validateServiceValue('my-assistant_456'));
        $this->assertFalse(VoiceAgentProvider::VAPI->validateServiceValue(''));

        // Synthflow - valid URL with synthflow
        $this->assertTrue(VoiceAgentProvider::SYNTHFLOW->validateServiceValue('https://api.synthflow.com/v1/endpoint'));
        $this->assertFalse(VoiceAgentProvider::SYNTHFLOW->validateServiceValue('https://api.example.com/endpoint'));
        $this->assertFalse(VoiceAgentProvider::SYNTHFLOW->validateServiceValue('not-a-url'));

        // Eleven Labs - valid UUID
        $this->assertTrue(VoiceAgentProvider::ELEVENLABS->validateServiceValue('12345678-1234-1234-1234-123456789012'));
        $this->assertFalse(VoiceAgentProvider::ELEVENLABS->validateServiceValue('not-a-uuid'));

        // Generic providers - valid URLs
        $this->assertTrue(VoiceAgentProvider::DASHA->validateServiceValue('https://api.dasha.ai/v1'));
        $this->assertFalse(VoiceAgentProvider::DASHA->validateServiceValue('not-a-url'));
    }

    public function test_enum_cases_exist()
    {
        $this->assertInstanceOf(VoiceAgentProvider::class, VoiceAgentProvider::VAPI);
        $this->assertInstanceOf(VoiceAgentProvider::class, VoiceAgentProvider::SYNTHFLOW);
        $this->assertInstanceOf(VoiceAgentProvider::class, VoiceAgentProvider::ELEVENLABS);
        $this->assertInstanceOf(VoiceAgentProvider::class, VoiceAgentProvider::MILLIS_US);
        $this->assertInstanceOf(VoiceAgentProvider::class, VoiceAgentProvider::MILLIS_EU);
    }

    public function test_enum_values_are_strings()
    {
        $this->assertIsString(VoiceAgentProvider::VAPI->value);
        $this->assertIsString(VoiceAgentProvider::SYNTHFLOW->value);
        $this->assertEquals('vapi', VoiceAgentProvider::VAPI->value);
        $this->assertEquals('synthflow', VoiceAgentProvider::SYNTHFLOW->value);
    }

    public function test_all_providers_have_unique_values()
    {
        $values = array_map(fn($case) => $case->value, VoiceAgentProvider::cases());
        $uniqueValues = array_unique($values);
        $this->assertCount(count($values), $uniqueValues, 'All provider values must be unique');
    }
}
