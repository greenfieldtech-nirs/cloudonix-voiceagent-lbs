<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentication Controller for API endpoints
 *
 * Handles user authentication operations including login, registration, and logout
 * using Laravel Sanctum for token-based authentication.
 */
class AuthController extends Controller
{
    /**
     * Authenticate user and return access token
     *
     * @param  Request  $request  The incoming HTTP request
     * @return JsonResponse JSON response with user data and token
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = Auth::user();
        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], Response::HTTP_OK);
    }

    /**
     * Register a new user account
     *
     * @param  Request  $request  The incoming HTTP request
     * @return JsonResponse JSON response with user data and token
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create tenant automatically during registration
        $tenant = \App\Models\Tenant::create([
            'name' => $request->name.'\'s Organization',
            'slug' => \Illuminate\Support\Str::slug($request->name.'-organization'),
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $tenant->id,
        ]);

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user->load('tenant'),
            'token' => $token,
            'token_type' => 'Bearer',
        ], Response::HTTP_CREATED);
    }

    /**
     * Get user profile information
     *
     * @param  Request  $request  The incoming HTTP request
     * @return JsonResponse JSON response with user profile data
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenant');

        return response()->json([
            'user' => $user,
        ], Response::HTTP_OK);
    }

    /**
     * Update user profile information
     *
     * @param  Request  $request  The incoming HTTP request
     * @return JsonResponse JSON response confirming profile update
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$request->user()->id],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'country' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9]\d{1,14}$/'],
            'mobile' => ['nullable', 'string', 'regex:/^\+[1-9]\d{1,14}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();

        // Update user with profile data
        $user->update($request->only([
            'name',
            'email',
            'company_name',
            'address',
            'country',
            'phone',
            'mobile',
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->load('tenant'),
        ], Response::HTTP_OK);
    }

    /**
     * Change user password
     *
     * @param  Request  $request  The incoming HTTP request
     * @return JsonResponse JSON response confirming password change
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();

        // Verify current password
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
                'errors' => ['current_password' => ['Current password is incorrect']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Get user settings
     *
     * @param  Request  $request  The incoming HTTP request
     * @return JsonResponse JSON response with user settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;

        $settings = [
            'cloudonix_domain' => $tenant->domain ?? '',
            'cloudonix_api_key' => $tenant->settings['cloudonix_api_key'] ?? '',
            'voice_app_api_key' => $tenant->settings['voice_app_api_key'] ?? '',
            'voice_app_endpoint' => $tenant->settings['voice_app_endpoint'] ?? env('VOICE_APP_ENDPOINT', 'http://localhost:8000/api/voice/webhook'),
        ];

        // Check validation status if domain and API key are configured
        $validationStatus = [
            'domain_valid' => false,
            'last_validated' => null,
            'permissions' => [],
        ];

        if (! empty($settings['cloudonix_domain']) && ! empty($settings['cloudonix_api_key'])) {
            $validationResult = $this->validateCloudonixAccess($settings['cloudonix_domain'], $settings['cloudonix_api_key']);
            $validationStatus = [
                'domain_valid' => $validationResult['valid'],
                'last_validated' => now()->toISOString(),
                'permissions' => $validationResult['valid'] ? ['read', 'write', 'admin'] : [],
            ];
        }

        // Compute URLs based on voice app endpoint
        $voiceApplicationEndpoint = $settings['voice_app_endpoint'].'/api/voice/application';
        $sessionUpdateUrl = $settings['voice_app_endpoint'].'/api/session/update';
        $cdrCallbackUrl = $settings['voice_app_endpoint'].'/api/session/cdr';

        return response()->json([
            'settings' => array_merge($settings, [
                'computed_urls' => [
                    'voice_application_endpoint' => $voiceApplicationEndpoint,
                    'session_update_url' => $sessionUpdateUrl,
                    'cdr_callback_url' => $cdrCallbackUrl,
                ],
            ]),
            'validation_status' => $validationStatus,
        ], Response::HTTP_OK);
    }

    /**
     * Update user settings
     *
     * @param  Request  $request  The incoming HTTP request
     * @return JsonResponse JSON response confirming settings update
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cloudonix_domain' => ['nullable', 'string', 'max:255'],
            'cloudonix_api_key' => ['nullable', 'string', 'max:255'],
            'voice_app_api_key' => ['nullable', 'string', 'max:255'], // Optional - will be generated by Cloudonix
            'voice_app_endpoint' => ['nullable', 'url'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();
        $tenant = $user->tenant;

        // Validate Cloudonix domain access if domain and API key are provided
        if ($request->cloudonix_domain && $request->cloudonix_api_key) {
            \Log::info('Settings Update: Starting Cloudonix configuration', [
                'domain' => $request->cloudonix_domain,
                'voice_app_endpoint' => $request->voice_app_endpoint,
                'user_id' => $request->user()->id,
            ]);

            $validationResult = $this->validateCloudonixAccess($request->cloudonix_domain, $request->cloudonix_api_key);

            if (! $validationResult['valid']) {
                \Log::warning('Settings Update: Cloudonix validation failed', [
                    'domain' => $request->cloudonix_domain,
                    'error_type' => $validationResult['error_type'],
                    'error' => $validationResult['error'],
                ]);

                return response()->json([
                    'message' => 'Cloudonix validation failed',
                    'error' => [
                        'type' => $validationResult['error_type'] ?? 'domain_access_denied',
                        'details' => $validationResult['error'],
                        'suggestion' => $this->getValidationSuggestion($validationResult['error_type'] ?? 'unknown'),
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Check if domain already has a default application with matching URL
            $existingApplication = $validationResult['domain_data']['application'] ?? null;
            $voiceAppEndpoint = $request->voice_app_endpoint;
            $needsConfiguration = true;

            if ($existingApplication && isset($existingApplication['url'])) {
                $existingUrl = $existingApplication['url'];
                $expectedUrl = $voiceAppEndpoint.'/api/voice/application';

                \Log::info('Settings Update: Checking existing application', [
                    'existing_application_id' => $existingApplication['id'] ?? null,
                    'existing_url' => $existingUrl,
                    'expected_url' => $expectedUrl,
                ]);

                if ($existingUrl === $expectedUrl) {
                    \Log::info('Settings Update: Existing application URL matches, skipping configuration', [
                        'application_id' => $existingApplication['id'] ?? null,
                    ]);

                    $needsConfiguration = false;
                    $configurationResult = [
                        'success' => true,
                        'skipped' => true,
                        'existing_application' => $existingApplication,
                    ];
                }
            }

            if ($needsConfiguration) {
                \Log::info('Settings Update: Validation successful, proceeding with domain configuration', [
                    'domain' => $request->cloudonix_domain,
                ]);

                // Validation successful, configure Cloudonix domain
                try {
                    $configurationResult = $this->configureCloudonixDomain(
                        $request->cloudonix_domain,
                        $request->cloudonix_api_key,
                        $request->voice_app_endpoint
                    );
                } catch (\Exception $e) {
                    \Log::error('Settings Update: Unexpected exception during domain configuration', [
                        'domain' => $request->cloudonix_domain,
                        'exception_class' => get_class($e),
                        'exception_message' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'message' => 'Cloudonix domain configuration failed',
                        'error' => [
                            'type' => 'domain_configuration_error',
                            'details' => 'An unexpected error occurred during domain configuration',
                            'suggestion' => 'Contact Cloudonix support for assistance',
                        ],
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            } else {
                $configurationResult = [
                    'success' => true,
                    'skipped' => true,
                    'existing_application' => $existingApplication,
                ];
            }
        }

        // Get current settings or initialize empty array
        $currentSettings = $tenant->settings ?? [];

        // Determine voice application API key based on configuration result
        $voiceAppApiKey = null;
        if (isset($configurationResult['skipped']) && $configurationResult['skipped']) {
            // Configuration was skipped - use existing application's API key if available
            $existingApp = $configurationResult['existing_application'] ?? null;
            $voiceAppApiKey = $existingApp['apiKey'] ?? $request->voice_app_api_key;

            \Log::info('Settings Update: Using existing application API key', [
                'has_existing_api_key' => isset($existingApp['apiKey']),
                'using_provided_key' => ! isset($existingApp['apiKey']) && ! empty($request->voice_app_api_key),
            ]);
        } else {
            // Configuration was performed - use generated API key
            $voiceAppApiKey = $configurationResult['voice_application_api_key'] ?? $request->voice_app_api_key;
        }

        // Update tenant with Cloudonix settings stored in JSON
        $updatedSettings = array_merge($currentSettings, [
            'cloudonix_api_key' => $request->cloudonix_api_key,
            'voice_app_api_key' => $voiceAppApiKey,
            'voice_app_endpoint' => $request->voice_app_endpoint,
        ]);

        $tenant->update([
            'domain' => $request->cloudonix_domain,
            'settings' => $updatedSettings,
        ]);

        $response = [
            'message' => 'Settings updated successfully',
            'validation' => [
                'domain_valid' => true,
                'webhooks_configured' => true,
                'domain_configured' => isset($configurationResult) && $configurationResult['success'],
            ],
        ];

        // Add voice application info if configuration was successful
        if (isset($configurationResult) && $configurationResult['success']) {
            if (isset($configurationResult['skipped']) && $configurationResult['skipped']) {
                // Configuration was skipped - existing application
                $existingApp = $configurationResult['existing_application'];
                $response['voice_application'] = [
                    'id' => $existingApp['id'] ?? null,
                    'skipped' => true,
                    'reason' => 'existing_application_url_matched',
                ];
                $response['validation']['domain_configured'] = true;
            } else {
                // Configuration was performed - new application
                $response['voice_application'] = [
                    'id' => $configurationResult['voice_application_id'],
                    'api_key_generated' => isset($configurationResult['voice_application_api_key']),
                ];
            }
        }

        \Log::info('Settings Update: Settings updated successfully', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'domain_configured' => isset($configurationResult) && $configurationResult['success'],
            'configuration_skipped' => isset($configurationResult['skipped']) && $configurationResult['skipped'],
            'voice_app_api_key_provided' => ! empty($voiceAppApiKey),
        ]);

        return response()->json($response, Response::HTTP_OK);
    }

    /**
     * Validate Cloudonix domain access
     *
     * @param  string  $domain  The Cloudonix domain
     * @param  string  $apiKey  The API key to validate
     * @return array Validation result
     */
    private function validateCloudonixAccess(string $domain, string $apiKey): array
    {
        \Log::info('Cloudonix Validation: Starting domain access validation', [
            'domain' => $domain,
            'api_key_prefix' => substr($apiKey, 0, 6).'...',
        ]);

        try {
            $client = new Client;
            $url = "https://api.cloudonix.io/domains/{$domain}";

            \Log::info('Cloudonix Validation: Making GET request', [
                'url' => $url,
                'headers' => [
                    'Authorization' => 'Bearer '.substr($apiKey, 0, 6).'...',
                    'Accept' => 'application/json',
                ],
            ]);

            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Accept' => 'application/json',
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            \Log::info('Cloudonix Validation: Response received', [
                'status_code' => $statusCode,
                'response_body_length' => strlen($body),
                'response_preview' => substr($body, 0, 200),
            ]);

            if ($statusCode === 200) {
                $data = json_decode($body, true);
                \Log::info('Cloudonix Validation: Domain validation successful', [
                    'domain_data' => $data,
                ]);

                return [
                    'valid' => true,
                    'domain_data' => $data,
                ];
            }

            \Log::warning('Cloudonix Validation: Domain validation failed', [
                'status_code' => $statusCode,
                'response_body' => $body,
            ]);

            return [
                'valid' => false,
                'error_type' => $this->getErrorType($statusCode),
                'error' => $this->getErrorMessage($statusCode),
            ];
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode() ?? 0;
            $responseBody = $e->getResponse()?->getBody()?->getContents() ?? '';

            \Log::error('Cloudonix Validation: Request exception occurred', [
                'domain' => $domain,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'exception_message' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error_type' => $this->getErrorType($statusCode),
                'error' => $this->getErrorMessage($statusCode),
            ];
        } catch (\Exception $e) {
            \Log::error('Cloudonix Validation: Unexpected exception occurred', [
                'domain' => $domain,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return [
                'valid' => false,
                'error_type' => 'network_error',
                'error' => 'Unable to connect to Cloudonix API',
            ];
        }
    }

    /**
     * Get error type based on HTTP status code
     */
    private function getErrorType(int $statusCode): string
    {
        return match ($statusCode) {
            401 => 'invalid_api_key',
            403 => 'insufficient_permissions',
            404 => 'domain_not_found',
            429 => 'rate_limited',
            default => 'unknown_error',
        };
    }

    /**
     * Get user-friendly error message
     */
    private function getErrorMessage(int $statusCode): string
    {
        return match ($statusCode) {
            401 => 'Invalid API key',
            403 => 'API key does not have access to this domain',
            404 => 'Domain not found',
            429 => 'Rate limit exceeded. Please try again later.',
            0 => 'Network connection failed',
            default => 'Unknown error occurred',
        };
    }

    /**
     * Configure Cloudonix domain after successful validation
     *
     * @param  string  $domain  The Cloudonix domain
     * @param  string  $apiKey  The validated API key
     * @param  string  $voiceAppEndpoint  The voice application endpoint URL
     * @return array Configuration result
     */
    private function configureCloudonixDomain(string $domain, string $apiKey, string $voiceAppEndpoint): array
    {
        \Log::info('Cloudonix Configuration: Starting domain configuration', [
            'domain' => $domain,
            'voice_app_endpoint' => $voiceAppEndpoint,
        ]);

        try {
            $client = new Client;

            // Step 1: Create Voice Application
            $voiceAppUrl = "https://api.cloudonix.io/domains/{$domain}/applications";
            $voiceAppPayload = [
                'name' => 'AI Voice SaaS Application',
                'type' => 'cxml',
                'url' => $voiceAppEndpoint.'/api/voice/application',
            ];

            \Log::info('Cloudonix Configuration: Creating voice application', [
                'url' => $voiceAppUrl,
                'payload' => $voiceAppPayload,
            ]);

            $voiceAppResponse = $client->post($voiceAppUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $voiceAppPayload,
                'timeout' => 30,
            ]);

            $voiceAppStatus = $voiceAppResponse->getStatusCode();
            $voiceAppBody = $voiceAppResponse->getBody()->getContents();

            \Log::info('Cloudonix Configuration: Voice application creation response', [
                'status_code' => $voiceAppStatus,
                'response_body_length' => strlen($voiceAppBody),
                'response_preview' => substr($voiceAppBody, 0, 200),
            ]);

            if ($voiceAppStatus !== 200 && $voiceAppStatus !== 201) {
                \Log::error('Cloudonix Configuration: Voice application creation failed', [
                    'status_code' => $voiceAppStatus,
                    'response_body' => $voiceAppBody,
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to create voice application',
                ];
            }

            $voiceAppData = json_decode($voiceAppBody, true);
            $applicationId = $voiceAppData['id'] ?? null;
            $applicationApiKey = $voiceAppData['apiKey'] ?? null;

            \Log::info('Cloudonix Configuration: Voice application created successfully', [
                'application_id' => $applicationId,
                'application_api_key_prefix' => $applicationApiKey ? substr($applicationApiKey, 0, 6).'...' : null,
                'voice_app_data_keys' => array_keys($voiceAppData),
            ]);

            if (! $applicationId) {
                \Log::error('Cloudonix Configuration: Voice application created but no ID returned', [
                    'response_data' => $voiceAppData,
                ]);

                return [
                    'success' => false,
                    'error' => 'Voice application created but no ID returned',
                ];
            }

            if (! $applicationApiKey) {
                \Log::error('Cloudonix Configuration: Voice application created but no API key returned', [
                    'response_data' => $voiceAppData,
                ]);

                return [
                    'success' => false,
                    'error' => 'Voice application created but no API key returned',
                ];
            }

            // Step 2: Update domain settings
            $sessionUpdateUrl = $voiceAppEndpoint.'/api/session/update';
            $cdrCallbackUrl = $voiceAppEndpoint.'/api/session/cdr';
            $domainUpdateUrl = "https://api.cloudonix.io/domains/{$domain}";
            $domainUpdatePayload = [
                'defaultApplication' => $applicationId,
                'authorization-api-key' => $applicationApiKey,
                'profile' => [
                    'session-update-endpoint' => $sessionUpdateUrl,
                    'cdr-endpoint' => $cdrCallbackUrl,
                ],
            ];

            \Log::info('Cloudonix Configuration: Updating domain settings', [
                'url' => $domainUpdateUrl,
                'payload' => $domainUpdatePayload,
            ]);

            $domainUpdateResponse = $client->put($domainUpdateUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $domainUpdatePayload,
                'timeout' => 30,
            ]);

            $domainUpdateStatus = $domainUpdateResponse->getStatusCode();
            $domainUpdateBody = $domainUpdateResponse->getBody()->getContents();

            \Log::info('Cloudonix Configuration: Domain update response', [
                'status_code' => $domainUpdateStatus,
                'response_body_length' => strlen($domainUpdateBody),
                'response_preview' => substr($domainUpdateBody, 0, 200),
            ]);

            if ($domainUpdateStatus !== 200) {
                \Log::error('Cloudonix Configuration: Domain update failed', [
                    'status_code' => $domainUpdateStatus,
                    'response_body' => $domainUpdateBody,
                ]);

                return [
                    'success' => false,
                    'error' => 'Voice application created but domain update failed',
                ];
            }

            \Log::info('Cloudonix Configuration: Domain configuration completed successfully', [
                'application_id' => $applicationId,
                'application_api_key_set' => true,
                'session_update_url' => $sessionUpdateUrl,
                'cdr_callback_url' => $cdrCallbackUrl,
            ]);

            return [
                'success' => true,
                'voice_application_id' => $applicationId,
                'voice_application_api_key' => $applicationApiKey,
                'session_update_url' => $sessionUpdateUrl,
                'cdr_callback_url' => $cdrCallbackUrl,
            ];

        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode() ?? 0;
            $responseBody = $e->getResponse()?->getBody()?->getContents() ?? '';
            $errorMessage = $this->getErrorMessage($statusCode);

            \Log::error('Cloudonix Configuration: Request exception during configuration', [
                'domain' => $domain,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'exception_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => "Domain configuration failed: {$errorMessage}",
            ];
        } catch (\Exception $e) {
            \Log::error('Cloudonix Configuration: Unexpected exception during configuration', [
                'domain' => $domain,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Unexpected error during domain configuration',
            ];
        }
    }

    /**
     * Get validation suggestion based on error type
     */
    private function getValidationSuggestion(string $errorType): string
    {
        return match ($errorType) {
            'invalid_api_key' => 'Verify your API key is correct and active',
            'insufficient_permissions' => 'Contact Cloudonix support to grant domain access to your API key',
            'domain_not_found' => 'Check the domain name or UUID is correct',
            'rate_limited' => 'Wait a moment and try again',
            'network_error' => 'Check your internet connection and try again',
            default => 'Contact Cloudonix support for assistance',
        };
    }

    /**
     * Check if user needs setup
     *
     * @param  Request  $request  The incoming HTTP request
     * @return JsonResponse JSON response with setup status
     */
    public function needsSetup(Request $request): JsonResponse
    {
        $user = $request->user();

        $needsSetup = [
            'profile_incomplete' => empty($user->company_name) || empty($user->country),
            'cloudonix_unconfigured' => empty($user->tenant->settings['cloudonix_api_key']),
        ];

        $needsSetup['setup_required'] = $needsSetup['profile_incomplete'] || $needsSetup['cloudonix_unconfigured'];

        return response()->json($needsSetup, Response::HTTP_OK);
    }

    /**
     * Logout user by revoking current access token
     *
     * @param  Request  $request  The incoming HTTP request
     * @return JsonResponse JSON response confirming logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the token that authenticated the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ], Response::HTTP_OK);
    }
}
