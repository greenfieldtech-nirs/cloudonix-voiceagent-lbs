<?php

namespace Tests\Unit\Services;

use App\Models\Trunk;
use App\Services\CxmlService;
use Tests\TestCase;

class CxmlOutboundRoutingTest extends TestCase
{
    protected CxmlService $cxmlService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cxmlService = app(CxmlService::class);
    }

    public function test_generate_outbound_trunk_routing()
    {
        $trunkConfig = [
            'trunk_ids' => ['trunk_123', 'trunk_456'],
            'ring_timeout' => 30,
            'max_duration' => 3600,
        ];

        $cxml = $this->cxmlService->generateOutboundTrunkRouting(
            '+1987654321',
            $trunkConfig,
            '+1234567890'
        );

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $cxml);
        $this->assertStringContainsString('<Response>', $cxml);
        $this->assertStringContainsString('<Dial', $cxml);
        $this->assertStringContainsString('<Number>+1987654321</Number>', $cxml);
        $this->assertStringContainsString('trunks="trunk_123,trunk_456"', $cxml);
        $this->assertStringContainsString('timeout="30"', $cxml);
        $this->assertStringContainsString('maxDuration="3600"', $cxml);
        $this->assertStringContainsString('callerId="+1234567890"', $cxml);
        $this->assertStringContains('</Response>', $cxml);
    }

    public function test_generate_outbound_trunk_routing_minimal_config()
    {
        $cxml = $this->cxmlService->generateOutboundTrunkRouting('+1987654321');

        $this->assertStringContainsString('<Dial', $cxml);
        $this->assertStringContainsString('<Number>+1987654321</Number>', $cxml);
        $this->assertStringContainsString('action=', $cxml);
        $this->assertStringContainsString('method="POST"', $cxml);
    }

    public function test_generate_outbound_trunk_routing_from_model()
    {
        $trunk = new Trunk([
            'id' => 1,
            'name' => 'Test Trunk',
            'cloudonix_trunk_id' => 'cx_trunk_123',
            'configuration' => [
                'timeout' => 45,
                'max_duration' => 1800,
            ],
            'priority' => 10,
            'capacity' => 5,
            'enabled' => true,
        ]);

        $cxml = $this->cxmlService->generateOutboundTrunkRoutingFromModel(
            $trunk,
            '+1987654321',
            '+1234567890'
        );

        $this->assertStringContainsString('trunks="cx_trunk_123"', $cxml);
        $this->assertStringContainsString('callerId="+1234567890"', $cxml);
        $this->assertStringContainsString('<Number>+1987654321</Number>', $cxml);
    }

    public function test_validate_outbound_cxml()
    {
        $validCxml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<Response>' . "\n" .
            '  <Dial trunks="trunk_123" callerId="+1234567890" action="/api/voice/callback" method="POST">' . "\n" .
            '    <Number>+1987654321</Number>' . "\n" .
            '  </Dial>' . "\n" .
            '</Response>';

        $this->assertTrue($this->cxmlService->validateOutboundCxml($validCxml));
    }

    public function test_validate_outbound_cxml_invalid_missing_xml_declaration()
    {
        $invalidCxml = '<Response>
  <Dial>
    <Number>+1987654321</Number>
  </Dial>
</Response>';

        $this->assertFalse($this->cxmlService->validateOutboundCxml($invalidCxml));
    }

    public function test_validate_outbound_cxml_invalid_missing_dial()
    {
        $invalidCxml = '<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Hangup/>
</Response>';

        $this->assertFalse($this->cxmlService->validateOutboundCxml($invalidCxml));
    }

    public function test_validate_outbound_cxml_invalid_missing_number()
    {
        $invalidCxml = '<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Dial trunks="trunk_123">
  </Dial>
</Response>';

        $this->assertFalse($this->cxmlService->validateOutboundCxml($invalidCxml));
    }

    public function test_validate_outbound_cxml_invalid_malformed_xml()
    {
        $invalidCxml = '<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Dial trunks="trunk_123">
    <Number>+1987654321</Number>
  </Dial>
</Response';

        $this->assertFalse($this->cxmlService->validateOutboundCxml($invalidCxml));
    }
}