<?php

/**
 * API Contract Definitions for Cloudonix Voice Application Tool
 *
 * This file defines the REST API contracts, request/response schemas,
 * and webhook specifications for the voice agent routing system.
 */

namespace App\Api\Contracts;

class ApiContracts
{
    /**
     * Voice Agent Management API Contracts
     */
    public const VOICE_AGENTS = [
        'LIST' => [
            'method' => 'GET',
            'path' => '/api/voice-agents',
            'query_parameters' => [
                'page' => ['type' => 'integer', 'default' => 1, 'description' => 'Page number'],
                'per_page' => ['type' => 'integer', 'default' => 20, 'description' => 'Items per page'],
                'search' => ['type' => 'string', 'description' => 'Search term'],
                'enabled' => ['type' => 'boolean', 'description' => 'Filter by enabled status'],
                'provider' => ['type' => 'string', 'description' => 'Filter by provider'],
            ],
            'response' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/VoiceAgent']
                    ],
                    'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                ]
            ]
        ],

        'CREATE' => [
            'method' => 'POST',
            'path' => '/api/voice-agents',
            'request_body' => [
                'type' => 'object',
                'required' => ['name', 'provider', 'service_value'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'provider' => ['type' => 'string', 'enum' => ['vapi', 'synthflow', /* ... all providers */]],
                    'service_value' => ['type' => 'string', 'maxLength' => 500],
                    'username' => ['type' => 'string', 'maxLength' => 255],
                    'password' => ['type' => 'string', 'maxLength' => 255],
                    'enabled' => ['type' => 'boolean', 'default' => true],
                    'metadata' => ['type' => 'object'],
                ]
            ],
            'response' => ['$ref' => '#/components/schemas/VoiceAgent']
        ],

        'UPDATE' => [
            'method' => 'PUT',
            'path' => '/api/voice-agents/{id}',
            'path_parameters' => [
                'id' => ['type' => 'integer', 'description' => 'Voice agent ID']
            ],
            'request_body' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'service_value' => ['type' => 'string', 'maxLength' => 500],
                    'username' => ['type' => 'string', 'maxLength' => 255],
                    'password' => ['type' => 'string', 'maxLength' => 255],
                    'enabled' => ['type' => 'boolean'],
                    'metadata' => ['type' => 'object'],
                ]
            ],
            'response' => ['$ref' => '#/components/schemas/VoiceAgent']
        ],

        'TOGGLE' => [
            'method' => 'PATCH',
            'path' => '/api/voice-agents/{id}/toggle',
            'path_parameters' => [
                'id' => ['type' => 'integer', 'description' => 'Voice agent ID']
            ],
            'response' => ['$ref' => '#/components/schemas/VoiceAgent']
        ],

        'DELETE' => [
            'method' => 'DELETE',
            'path' => '/api/voice-agents/{id}',
            'path_parameters' => [
                'id' => ['type' => 'integer', 'description' => 'Voice agent ID']
            ],
            'response' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'Voice agent deleted successfully']
                ]
            ]
        ]
    ];

    /**
     * Agent Groups Management API Contracts
     */
    public const AGENT_GROUPS = [
        'LIST' => [
            'method' => 'GET',
            'path' => '/api/agent-groups',
            'query_parameters' => [
                'page' => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 20],
                'strategy' => ['type' => 'string', 'enum' => ['load_balanced', 'priority', 'round_robin']],
            ],
            'response' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/AgentGroup']
                    ],
                    'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                ]
            ]
        ],

        'CREATE' => [
            'method' => 'POST',
            'path' => '/api/agent-groups',
            'request_body' => [
                'type' => 'object',
                'required' => ['name', 'strategy'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'strategy' => ['type' => 'string', 'enum' => ['load_balanced', 'priority', 'round_robin']],
                    'settings' => ['type' => 'object'],
                ]
            ],
            'response' => ['$ref' => '#/components/schemas/AgentGroup']
        ],

        'MEMBERS_ADD' => [
            'method' => 'POST',
            'path' => '/api/agent-groups/{id}/members',
            'path_parameters' => [
                'id' => ['type' => 'integer', 'description' => 'Agent group ID']
            ],
            'request_body' => [
                'type' => 'object',
                'required' => ['agent_id'],
                'properties' => [
                    'agent_id' => ['type' => 'integer'],
                    'priority' => ['type' => 'integer'],
                    'capacity' => ['type' => 'integer', 'default' => 1],
                ]
            ],
            'response' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'membership' => ['type' => 'object']
                ]
            ]
        ]
    ];

    /**
     * Routing Rules API Contracts
     */
    public const ROUTING_RULES = [
        'INBOUND_LIST' => [
            'method' => 'GET',
            'path' => '/api/inbound-routing-rules',
            'response' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/InboundRoutingRule']
            ]
        ],

        'INBOUND_CREATE' => [
            'method' => 'POST',
            'path' => '/api/inbound-routing-rules',
            'request_body' => [
                'type' => 'object',
                'required' => ['pattern', 'target_type', 'target_id'],
                'properties' => [
                    'pattern' => ['type' => 'string', 'maxLength' => 255],
                    'target_type' => ['type' => 'string', 'enum' => ['agent', 'group']],
                    'target_id' => ['type' => 'integer'],
                    'priority' => ['type' => 'integer', 'default' => 0],
                    'enabled' => ['type' => 'boolean', 'default' => true],
                ]
            ],
            'response' => ['$ref' => '#/components/schemas/InboundRoutingRule']
        ],

        'OUTBOUND_LIST' => [
            'method' => 'GET',
            'path' => '/api/outbound-routing-rules',
            'response' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/OutboundRoutingRule']
            ]
        ]
    ];

    /**
     * Analytics API Contracts
     */
    public const ANALYTICS = [
        'METRICS' => [
            'method' => 'GET',
            'path' => '/api/analytics/metrics',
            'query_parameters' => [
                'start_date' => ['type' => 'string', 'format' => 'date'],
                'end_date' => ['type' => 'string', 'format' => 'date'],
                'agent_id' => ['type' => 'integer'],
                'group_id' => ['type' => 'integer'],
            ],
            'response' => [
                'type' => 'object',
                'properties' => [
                    'calls_today' => ['type' => 'integer'],
                    'success_rate' => ['type' => 'number', 'format' => 'float'],
                    'avg_duration' => ['type' => 'integer'],
                    'active_calls' => ['type' => 'integer'],
                    'trends' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'date' => ['type' => 'string', 'format' => 'date'],
                                'calls' => ['type' => 'integer'],
                                'success_rate' => ['type' => 'number'],
                            ]
                        ]
                    ]
                ]
            ]
        ],

        'CALL_RECORDS' => [
            'method' => 'GET',
            'path' => '/api/analytics/call-records',
            'query_parameters' => [
                'page' => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 50],
                'start_date' => ['type' => 'string', 'format' => 'date'],
                'end_date' => ['type' => 'string', 'format' => 'date'],
                'agent_id' => ['type' => 'integer'],
                'group_id' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
                'direction' => ['type' => 'string', 'enum' => ['inbound', 'outbound']],
            ],
            'response' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/CallRecord']
                    ],
                    'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                ]
            ]
        ]
    ];

    /**
     * Webhook API Contracts
     */
    public const WEBHOOKS = [
        'VOICE_APPLICATION' => [
            'method' => 'POST',
            'path' => '/api/voice/application/{domain}',
            'path_parameters' => [
                'domain' => ['type' => 'string', 'description' => 'Cloudonix domain']
            ],
            'headers' => [
                'X-CX-APIKey' => ['type' => 'string', 'description' => 'Application API key'],
                'X-CX-Domain' => ['type' => 'string', 'description' => 'Cloudonix domain'],
                'Content-Type' => ['type' => 'string', 'enum' => ['application/x-www-form-urlencoded', 'application/json']],
            ],
            'request_body' => [
                'type' => 'object',
                'properties' => [
                    'CallSid' => ['type' => 'string', 'description' => 'Cloudonix session identifier'],
                    'From' => ['type' => 'string', 'description' => 'Calling subscriber phone number'],
                    'To' => ['type' => 'string', 'description' => 'DNID that activated application'],
                    'Direction' => ['type' => 'string', 'enum' => ['inbound', 'outbound-api', 'subscriber']],
                    'Session' => ['type' => 'string', 'description' => 'Cloudonix session token'],
                    // ... additional webhook parameters
                ]
            ],
            'response' => [
                'type' => 'string',
                'description' => 'CXML response for call routing',
                'content_type' => 'application/xml'
            ]
        ],

        'SESSION_UPDATE' => [
            'method' => 'POST',
            'path' => '/api/voice/session/update/{domain}',
            'response' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'example' => 'processed']
                ]
            ]
        ],

        'SESSION_CDR' => [
            'method' => 'POST',
            'path' => '/api/voice/session/cdr/{domain}',
            'response' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'example' => 'recorded']
                ]
            ]
        ]
    ];

    /**
     * Error Response Schemas
     */
    public const ERRORS = [
        'VALIDATION_ERROR' => [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Validation failed'],
                'errors' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => ['type' => 'string']
                    ]
                ]
            ]
        ],

        'NOT_FOUND' => [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Resource not found'],
                'resource' => ['type' => 'string'],
                'id' => ['type' => 'string']
            ]
        ],

        'UNAUTHORIZED' => [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Unauthorized'],
                'error' => ['type' => 'string', 'example' => 'Invalid API key']
            ]
        ],

        'TENANT_ISOLATION' => [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Access denied'],
                'error' => ['type' => 'string', 'example' => 'Resource belongs to different tenant']
            ]
        ]
    ];

    /**
     * Common Schema Components
     */
    public const SCHEMAS = [
        'VoiceAgent' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'tenant_id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'provider' => ['type' => 'string'],
                'service_value' => ['type' => 'string'],
                'enabled' => ['type' => 'boolean'],
                'metadata' => ['type' => 'object'],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                'updated_at' => ['type' => 'string', 'format' => 'date-time'],
            ]
        ],

        'AgentGroup' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'tenant_id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'strategy' => ['type' => 'string'],
                'settings' => ['type' => 'object'],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                'agents' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/components/schemas/VoiceAgent']
                ]
            ]
        ],

        'CallRecord' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'session_token' => ['type' => 'string'],
                'direction' => ['type' => 'string'],
                'from_number' => ['type' => 'string'],
                'to_number' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'start_time' => ['type' => 'string', 'format' => 'date-time'],
                'end_time' => ['type' => 'string', 'format' => 'date-time'],
                'duration' => ['type' => 'integer'],
                'agent' => ['$ref' => '#/components/schemas/VoiceAgent'],
                'group' => ['$ref' => '#/components/schemas/AgentGroup'],
                'created_at' => ['type' => 'string', 'format' => 'date-time'],
            ]
        ],

        'PaginationMeta' => [
            'type' => 'object',
            'properties' => [
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer'],
                'to' => ['type' => 'integer'],
            ]
        ]
    ];
}