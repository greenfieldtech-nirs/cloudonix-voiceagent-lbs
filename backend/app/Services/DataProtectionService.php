<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Data Protection Service
 *
 * Handles encryption/decryption of sensitive data and GDPR compliance features.
 */
class DataProtectionService
{
    /**
     * Encrypt sensitive data
     */
    public function encryptData(string $data): string
    {
        try {
            return Crypt::encryptString($data);
        } catch (\Exception $e) {
            Log::error('Data encryption failed', [
                'error' => $e->getMessage(),
                'data_length' => strlen($data),
            ]);
            throw $e;
        }
    }

    /**
     * Decrypt sensitive data
     */
    public function decryptData(string $encryptedData): string
    {
        try {
            return Crypt::decryptString($encryptedData);
        } catch (\Exception $e) {
            Log::error('Data decryption failed', [
                'error' => $e->getMessage(),
                'data_preview' => substr($encryptedData, 0, 50) . '...',
            ]);
            throw $e;
        }
    }

    /**
     * Encrypt multiple fields in an array
     */
    public function encryptFields(array $data, array $sensitiveFields): array
    {
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = $this->encryptData($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Decrypt multiple fields in an array
     */
    public function decryptFields(array $data, array $sensitiveFields): array
    {
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                try {
                    $data[$field] = $this->decryptData($data[$field]);
                } catch (\Exception $e) {
                    Log::warning('Failed to decrypt field, keeping encrypted value', [
                        'field' => $field,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $data;
    }

    /**
     * Generate a secure random key
     */
    public function generateSecureKey(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Hash data for non-reversible storage
     */
    public function hashData(string $data): string
    {
        return hash('sha256', $data);
    }

    /**
     * Anonymize personal data for GDPR compliance
     */
    public function anonymizePersonalData(array $data): array
    {
        $anonymized = $data;

        // Common PII fields to anonymize
        $piiFields = [
            'phone_number',
            'email',
            'first_name',
            'last_name',
            'address',
            'ip_address',
        ];

        foreach ($piiFields as $field) {
            if (isset($anonymized[$field])) {
                $anonymized[$field] = $this->anonymizeField($anonymized[$field], $field);
            }
        }

        return $anonymized;
    }

    /**
     * Anonymize a specific field
     */
    private function anonymizeField($value, string $field): string
    {
        if (!is_string($value)) {
            return '[ANONYMIZED]';
        }

        switch ($field) {
            case 'phone_number':
                return $this->anonymizePhoneNumber($value);
            case 'email':
                return $this->anonymizeEmail($value);
            case 'first_name':
            case 'last_name':
                return $this->anonymizeName($value);
            case 'ip_address':
                return $this->anonymizeIpAddress($value);
            default:
                return '[ANONYMIZED-' . strtoupper($field) . ']';
        }
    }

    /**
     * Anonymize phone number (keep area code, mask rest)
     */
    private function anonymizePhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/\D/', '', $phone);

        if (strlen($cleaned) >= 10) {
            // Keep first 3 digits, mask the rest
            return substr($cleaned, 0, 3) . '***' . substr($cleaned, -4);
        }

        return '***' . substr($cleaned, -4);
    }

    /**
     * Anonymize email (keep domain, mask username)
     */
    private function anonymizeEmail(string $email): string
    {
        if (strpos($email, '@') === false) {
            return '[INVALID-EMAIL]';
        }

        list($username, $domain) = explode('@', $email, 2);
        $maskedUsername = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 4)) . substr($username, -2);

        return $maskedUsername . '@' . $domain;
    }

    /**
     * Anonymize name (keep first letter, mask rest)
     */
    private function anonymizeName(string $name): string
    {
        if (strlen($name) <= 2) {
            return strtoupper(substr($name, 0, 1)) . '*';
        }

        return strtoupper(substr($name, 0, 1)) . str_repeat('*', strlen($name) - 2) . strtoupper(substr($name, -1));
    }

    /**
     * Anonymize IP address
     */
    private function anonymizeIpAddress(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.***.***';
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // For IPv6, mask more aggressively
            return substr($ip, 0, 4) . '::****:****:****:****';
        }

        return '[INVALID-IP]';
    }

    /**
     * Create data retention compliant export
     */
    public function createRetentionCompliantExport(array $data, array $retentionRules): array
    {
        $compliantData = [];

        foreach ($data as $record) {
            $compliantRecord = $record;

            foreach ($retentionRules as $field => $retentionPeriod) {
                if (isset($record[$field])) {
                    $fieldAge = $this->calculateFieldAge($record, $field);

                    if ($fieldAge > $retentionPeriod) {
                        // Field has exceeded retention period, anonymize or remove
                        if (isset($record['created_at'])) {
                            $compliantRecord[$field] = '[RETAINED-' . $retentionPeriod . '-DAYS]';
                        } else {
                            unset($compliantRecord[$field]);
                        }
                    }
                }
            }

            $compliantData[] = $compliantRecord;
        }

        return $compliantData;
    }

    /**
     * Calculate age of a field in days
     */
    private function calculateFieldAge(array $record, string $field): int
    {
        $referenceDate = isset($record['created_at']) ? $record['created_at'] :
                        (isset($record['updated_at']) ? $record['updated_at'] : now());

        if (is_string($referenceDate)) {
            $referenceDate = \Carbon\Carbon::parse($referenceDate);
        }

        return $referenceDate->diffInDays(now());
    }

    /**
     * Validate encryption configuration
     */
    public function validateEncryptionSetup(): array
    {
        $issues = [];

        // Check if APP_KEY is set
        if (!env('APP_KEY') || strlen(env('APP_KEY')) < 32) {
            $issues[] = 'APP_KEY is not properly configured (must be at least 32 characters)';
        }

        // Check encryption method
        try {
            $testData = 'encryption_test_' . time();
            $encrypted = $this->encryptData($testData);
            $decrypted = $this->decryptData($encrypted);

            if ($testData !== $decrypted) {
                $issues[] = 'Encryption/decryption cycle failed';
            }
        } catch (\Exception $e) {
            $issues[] = 'Encryption setup error: ' . $e->getMessage();
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Get data protection metrics
     */
    public function getProtectionMetrics(): array
    {
        return [
            'encryption_enabled' => !empty(env('APP_KEY')),
            'gdpr_compliance' => true, // Placeholder - implement actual checks
            'data_anonymization_available' => true,
            'retention_policies_configured' => true,
            'last_security_audit' => null, // Would be stored in database
        ];
    }
}