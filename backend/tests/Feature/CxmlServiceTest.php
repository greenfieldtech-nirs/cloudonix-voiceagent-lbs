<?php

namespace Tests\Feature;

use App\Services\CxmlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CxmlServiceTest extends TestCase
{
    use RefreshDatabase;

    private CxmlService $cxmlService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cxmlService = app(CxmlService::class);
    }

    public function test_generate_voice_agent_routing_basic()
    {
        $agent = [
            'provider' => 'vapi',
            'service_value' => 'agent_12345',
        ];

        $cxml = $this->cxmlService->generateVoiceAgentRouting($agent);

        $this->assertStringContains('<?xml version="1.0" encoding="UTF-8"?>', $cxml);
        $this->assertStringContains('<Response>', $cxml);
        $this->assertStringContains('<Dial', $cxml);
        $this->assertStringContains('<Service provider="vapi">', $cxml);
        $this->assertStringContains('agent_12345', $cxml);
        $this->assertStringContains('</Service>', $cxml);
        $this->assertStringContains('</Dial>', $cxml);
        $this->assertStringContains('</Response>', $cxml);
    }

    public function test_generate_voice_agent_routing_with_caller_id()
    {
        $agent = [
            'provider' => 'vapi',
            'service_value' => 'agent_12345',
        ];

        $cxml = $this->cxmlService->generateVoiceAgentRouting($agent, '+1234567890');

        $this->assertStringContains('callerId="+1234567890"', $cxml);
    }

    public function test_generate_voice_agent_routing_with_authentication()
    {
        $agent = [
            'provider' => 'synthflow',
            'service_value' => 'https://api.synthflow.com/endpoint',
            'username' => 'api_key_123',
            'password' => 'secret_key_456',
        ];

        $cxml = $this->cxmlService->generateVoiceAgentRouting($agent);

        $this->assertStringContains('provider="synthflow"', $cxml);
        $this->assertStringContains('username="api_key_123"', $cxml);
        $this->assertStringContains('password="secret_key_456"', $cxml);
        $this->assertStringContains('https://api.synthflow.com/endpoint', $cxml);
    }

    public function test_generate_trunk_routing_basic()
    {
        $cxml = $this->cxmlService->generateTrunkRouting('+1987654321');

        $this->assertStringContains('<Dial', $cxml);
        $this->assertStringContains('<Number>+1987654321</Number>', $cxml);
        $this->assertStringContains('</Dial>', $cxml);
    }

    public function test_generate_trunk_routing_with_trunks()
    {
        $trunkConfig = [
            'trunk_ids' => ['trunk_1', 'trunk_2'],
        ];

        $cxml = $this->cxmlService->generateTrunkRouting('+1987654321', $trunkConfig);

        $this->assertStringContains('trunks="trunk_1,trunk_2"', $cxml);
    }

    public function test_generate_trunk_routing_with_caller_id()
    {
        $cxml = $this->cxmlService->generateTrunkRouting('+1987654321', [], '+1234567890');

        $this->assertStringContains('callerId="+1234567890"', $cxml);
    }

    public function test_generate_hangup()
    {
        $cxml = $this->cxmlService->generateHangup();

        $this->assertStringContains('<Response>', $cxml);
        $this->assertStringContains('<Hangup/>', $cxml);
        $this->assertStringContains('</Response>', $cxml);
    }

    public function test_generate_group_routing_with_agents()
    {
        $agents = [
            [
                'provider' => 'vapi',
                'service_value' => 'agent_123',
            ],
        ];

        $cxml = $this->cxmlService->generateGroupRouting($agents, 'load_balanced');

        $this->assertStringContains('provider="vapi"', $cxml);
        $this->assertStringContains('agent_123', $cxml);
    }

    public function test_generate_group_routing_no_agents()
    {
        $cxml = $this->cxmlService->generateGroupRouting([], 'load_balanced');

        // Should generate hangup when no agents available
        $this->assertStringContains('<Hangup/>', $cxml);
    }

    public function test_cxml_validation_valid_xml()
    {
        $validCxml = '<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Dial>
    <Service provider="vapi">agent_123</Service>
  </Dial>
</Response>';

        $this->assertTrue($this->cxmlService->validateCxml($validCxml));
    }

    public function test_cxml_validation_invalid_xml()
    {
        $invalidCxml = '<Response><Dial><Service provider="vapi">agent_123</Service></Dial>';

        $this->assertFalse($this->cxmlService->validateCxml($invalidCxml));
    }

    public function test_cxml_validation_missing_response()
    {
        $invalidCxml = '<?xml version="1.0" encoding="UTF-8"?>
<Dial>
  <Service provider="vapi">agent_123</Service>
</Dial>';

        $this->assertFalse($this->cxmlService->validateCxml($invalidCxml));
    }

    public function test_cxml_validation_missing_verb()
    {
        $invalidCxml = '<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <InvalidVerb/>
</Response>';

        $this->assertFalse($this->cxmlService->validateCxml($invalidCxml));
    }

    public function test_provider_requirements_structure()
    {
        $requirements = CxmlService::getProviderRequirements();

        $this->assertIsArray($requirements);
        $this->assertArrayHasKey('vapi', $requirements);
        $this->assertArrayHasKey('synthflow', $requirements);

        // Check vapi requirements
        $this->assertFalse($requirements['vapi']['requires_auth']);
        $this->assertEquals('VAPI assistant ID', $requirements['vapi']['value_description']);

        // Check synthflow requirements
        $this->assertTrue($requirements['synthflow']['requires_auth']);
        $this->assertArrayHasKey('username_description', $requirements['synthflow']);
        $this->assertArrayHasKey('password_description', $requirements['synthflow']);
    }

    public function test_all_providers_have_requirements()
    {
        $requirements = CxmlService::getProviderRequirements();

        $expectedProviders = [
            'vapi', 'synthflow', 'dasha', 'superdash.ai', 'elevenlabs', 'deepvox',
            'relayhawk', 'voicehub', 'retell-udp', 'retell-tcp', 'retell-tls',
            'retell', 'fonio', 'sigmamind', 'modon', 'puretalk', 'millis-us', 'millis-eu'
        ];

        foreach ($expectedProviders as $provider) {
            $this->assertArrayHasKey($provider, $requirements, "Missing requirements for provider: {$provider}");
            $this->assertArrayHasKey('requires_auth', $requirements[$provider]);
            $this->assertArrayHasKey('value_description', $requirements[$provider]);
        }
    }

    public function test_format_cxml()
    {
        $unformatted = '<Response><Dial><Service provider="vapi">agent_123</Service></Dial></Response>';
        $formatted = $this->cxmlService->formatCxml($unformatted);

        // For now, formatCxml just returns the input
        // In production, this would format the XML
        $this->assertEquals($unformatted, $formatted);
    }

    public function test_xml_special_characters_escaped()
    {
        $agent = [
            'provider' => 'vapi',
            'service_value' => 'agent with "quotes" & <tags>',
        ];

        $cxml = $this->cxmlService->generateVoiceAgentRouting($agent);

        // Should contain escaped characters
        $this->assertStringContains('agent with &quot;quotes&quot; &amp; &lt;tags&gt;', $cxml);
        $this->assertStringNotContains('agent with "quotes" & <tags>', $cxml);
    }

    public function test_trunk_routing_special_characters()
    {
        $trunkConfig = [
            'trunk_ids' => ['trunk with spaces', 'trunk "quotes"'],
        ];

        $cxml = $this->cxmlService->generateTrunkRouting('+1234567890', $trunkConfig);

        // Should escape special characters in trunk IDs
        $this->assertStringContains('trunk with spaces', $cxml);
        $this->assertStringContains('trunk &quot;quotes&quot;', $cxml);
    }
}
