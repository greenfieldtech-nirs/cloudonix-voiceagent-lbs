<?php

namespace App\Services;

/**
 * CXML Service for Cloudonix Voice Application
 *
 * Generates Cloudonix-compliant CXML responses for voice call routing.
 * Supports different routing scenarios: voice agents, trunks, and hang-up.
 */
class CxmlService
{
    /**
     * Generate CXML for routing to a voice agent
     */
    public function generateVoiceAgentRouting(array $agent, string $callerId = null): string
    {
        $provider = $agent['provider'];
        $serviceValue = $agent['service_value'];

        // Get credentials (decrypted by model accessor)
        $username = $agent['username'] ?? null;
        $password = $agent['password'] ?? null;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Response>' . "\n";
        $xml .= '  <Dial';

        if ($callerId) {
            $xml .= ' callerId="' . htmlspecialchars($callerId) . '"';
        }

        $xml .= ' action="' . $this->getCallbackUrl() . '" method="POST">' . "\n";
        $xml .= '    <Service provider="' . htmlspecialchars($provider) . '"';

        if ($username && $password) {
            $xml .= ' username="' . htmlspecialchars($username) . '"';
            $xml .= ' password="' . htmlspecialchars($password) . '"';
        }

        $xml .= '>' . "\n";
        $xml .= '      ' . htmlspecialchars($serviceValue) . "\n";
        $xml .= '    </Service>' . "\n";
        $xml .= '  </Dial>' . "\n";
        $xml .= '</Response>' . "\n";

        return $xml;
    }

    /**
     * Generate CXML for routing to a trunk
     */
    public function generateTrunkRouting(string $phoneNumber, array $trunkConfig = [], string $callerId = null): string
    {
        $trunkIds = $trunkConfig['trunk_ids'] ?? [];
        $trunksAttribute = empty($trunkIds) ? '' : ' trunks="' . htmlspecialchars(implode(',', $trunkIds)) . '"';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Response>' . "\n";
        $xml .= '  <Dial' . $trunksAttribute;

        if ($callerId) {
            $xml .= ' callerId="' . htmlspecialchars($callerId) . '"';
        }

        $xml .= ' action="' . $this->getCallbackUrl() . '" method="POST">' . "\n";
        $xml .= '    <Number>' . htmlspecialchars($phoneNumber) . '</Number>' . "\n";
        $xml .= '  </Dial>' . "\n";
        $xml .= '</Response>' . "\n";

        return $xml;
    }

    /**
     * Generate CXML for hanging up the call
     */
    public function generateHangup(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Response>' . "\n";
        $xml .= '  <Hangup/>' . "\n";
        $xml .= '</Response>' . "\n";

        return $xml;
    }

    /**
     * Generate CXML for routing to an agent group with load balancing
     */
    public function generateGroupRouting(array $groupAgents, string $strategy, string $callerId = null): string
    {
        // For now, pick the first available agent
        // In production, this would use the load balancing algorithm
        $agent = $groupAgents[0] ?? null;

        if (!$agent) {
            return $this->generateHangup();
        }

        return $this->generateVoiceAgentRouting($agent, $callerId);
    }

    /**
     * Validate CXML against basic structure requirements
     */
    public function validateCxml(string $cxml): bool
    {
        // Basic validation - check for required elements
        if (!str_contains($cxml, '<?xml version="1.0"')) {
            return false;
        }

        if (!str_contains($cxml, '<Response>') || !str_contains($cxml, '</Response>')) {
            return false;
        }

        // Check for valid CXML verbs
        $validVerbs = ['<Dial>', '<Hangup>', '<Service>', '<Number>'];
        $hasValidVerb = false;

        foreach ($validVerbs as $verb) {
            if (str_contains($cxml, $verb)) {
                $hasValidVerb = true;
                break;
            }
        }

        if (!$hasValidVerb) {
            return false;
        }

        // Try to parse as XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($cxml);
        if ($xml === false) {
            return false;
        }

        return true;
    }

    /**
     * Get the callback URL for dial actions
     */
    private function getCallbackUrl(): string
    {
        // In production, this would be configurable
        // For now, return a placeholder
        return config('app.url') . '/api/voice/callback';
    }

    /**
     * Format CXML for pretty printing (development only)
     */
    public function formatCxml(string $cxml): string
    {
        // Simple formatting - in production, use a proper XML formatter
        return $cxml;
    }

    /**
     * Generate CXML for outbound trunk routing
     */
    public function generateOutboundTrunkRouting(string $phoneNumber, array $trunkConfig = [], string $callerId = null): string
    {
        $trunkIds = $trunkConfig['trunk_ids'] ?? [];
        $trunkAttributes = [];

        // Build trunk-specific attributes
        if (!empty($trunkIds)) {
            $trunkAttributes[] = 'trunks="' . htmlspecialchars(implode(',', $trunkIds)) . '"';
        }

        // Add any additional trunk configuration attributes
        if (isset($trunkConfig['ring_timeout'])) {
            $trunkAttributes[] = 'timeout="' . htmlspecialchars($trunkConfig['ring_timeout']) . '"';
        }

        if (isset($trunkConfig['max_duration'])) {
            $trunkAttributes[] = 'maxDuration="' . htmlspecialchars($trunkConfig['max_duration']) . '"';
        }

        $trunkAttributeString = empty($trunkAttributes) ? '' : ' ' . implode(' ', $trunkAttributes);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Response>' . "\n";
        $xml .= '  <Dial' . $trunkAttributeString;

        if ($callerId) {
            $xml .= ' callerId="' . htmlspecialchars($callerId) . '"';
        }

        $xml .= ' action="' . $this->getCallbackUrl() . '" method="POST">' . "\n";
        $xml .= '    <Number>' . htmlspecialchars($phoneNumber) . '</Number>' . "\n";
        $xml .= '  </Dial>' . "\n";
        $xml .= '</Response>' . "\n";

        return $xml;
    }

    /**
     * Generate CXML for outbound routing using a Trunk model
     */
    public function generateOutboundTrunkRoutingFromModel(\App\Models\Trunk $trunk, string $phoneNumber, string $callerId = null): string
    {
        // Use trunk configuration for routing attributes
        $trunkConfig = $trunk->configuration ?? [];
        $trunkConfig['trunk_ids'] = [$trunk->cloudonix_trunk_id];

        return $this->generateOutboundTrunkRouting($phoneNumber, $trunkConfig, $callerId);
    }

    /**
     * Validate outbound CXML structure
     */
    public function validateOutboundCxml(string $cxml): bool
    {
        // Basic validation for outbound calls
        if (!str_contains($cxml, '<?xml version="1.0"')) {
            return false;
        }

        if (!str_contains($cxml, '<Response>') || !str_contains($cxml, '</Response>')) {
            return false;
        }

        if (!str_contains($cxml, '<Dial>') || !str_contains($cxml, '<Number>')) {
            return false;
        }

        // Try to parse as XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($cxml);
        if ($xml === false) {
            return false;
        }

        return true;
    }

    /**
     * Get supported providers and their CXML requirements
     */
    public static function getProviderRequirements(): array
    {
        return [
            'vapi' => [
                'requires_auth' => false,
                'value_description' => 'VAPI assistant ID',
            ],
            'synthflow' => [
                'requires_auth' => true,
                'value_description' => 'Synthflow endpoint URL',
                'username_description' => 'Synthflow API key',
                'password_description' => 'Synthflow secret key',
            ],
            'dasha' => [
                'requires_auth' => false,
                'value_description' => 'Dasha application endpoint',
            ],
            'superdash.ai' => [
                'requires_auth' => true,
                'value_description' => 'Superdash endpoint',
                'username_description' => 'Superdash API key',
                'password_description' => 'Superdash secret',
            ],
            'elevenlabs' => [
                'requires_auth' => true,
                'value_description' => 'ElevenLabs voice ID or endpoint',
                'username_description' => 'ElevenLabs API key',
            ],
            'deepvox' => [
                'requires_auth' => false,
                'value_description' => 'Deepvox configuration endpoint',
            ],
            'relayhawk' => [
                'requires_auth' => false,
                'value_description' => 'Relayhawk agent endpoint',
            ],
            'voicehub' => [
                'requires_auth' => false,
                'value_description' => 'VoiceHub service endpoint',
            ],
            'retell-udp' => [
                'requires_auth' => false,
                'value_description' => 'Retell UDP endpoint',
            ],
            'retell-tcp' => [
                'requires_auth' => false,
                'value_description' => 'Retell TCP endpoint',
            ],
            'retell-tls' => [
                'requires_auth' => false,
                'value_description' => 'Retell TLS endpoint',
            ],
            'retell' => [
                'requires_auth' => false,
                'value_description' => 'Retell default endpoint',
            ],
            'fonio' => [
                'requires_auth' => false,
                'value_description' => 'Fonio service endpoint',
            ],
            'sigmamind' => [
                'requires_auth' => false,
                'value_description' => 'Sigma Mind endpoint',
            ],
            'modon' => [
                'requires_auth' => false,
                'value_description' => 'Modon service endpoint',
            ],
            'puretalk' => [
                'requires_auth' => false,
                'value_description' => 'PureTalk endpoint',
            ],
            'millis-us' => [
                'requires_auth' => false,
                'value_description' => 'Millis US endpoint',
            ],
            'millis-eu' => [
                'requires_auth' => false,
                'value_description' => 'Millis EU endpoint',
            ],
        ];
    }
}