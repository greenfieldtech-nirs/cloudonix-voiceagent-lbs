<?php

namespace App\Services;

use App\Models\CallRecord;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter Builder Service
 *
 * Constructs complex database queries for call records with multiple filtering criteria.
 * Supports advanced filtering, validation, and query optimization.
 */
class FilterBuilderService
{
    /**
     * Build a call record query with filters
     */
    public function buildCallRecordQuery(Tenant $tenant, array $filters): Builder
    {
        $query = CallRecord::where('tenant_id', $tenant->id);

        // Apply each filter
        foreach ($filters as $field => $value) {
            $method = 'apply' . ucfirst($field) . 'Filter';
            if (method_exists($this, $method)) {
                $this->$method($query, $value);
            }
        }

        return $query;
    }

    /**
     * Apply start date filter
     */
    private function applyStartDateFilter(Builder $query, $value): void
    {
        if ($value) {
            $query->where('start_time', '>=', $value);
        }
    }

    /**
     * Apply end date filter
     */
    private function applyEndDateFilter(Builder $query, $value): void
    {
        if ($value) {
            $query->where('start_time', '<=', $value);
        }
    }

    /**
     * Apply status filter
     */
    private function applyStatusFilter(Builder $query, $value): void
    {
        if (is_array($value) && !empty($value)) {
            $query->whereIn('status', $value);
        } elseif (is_string($value)) {
            $query->where('status', $value);
        }
    }

    /**
     * Apply direction filter
     */
    private function applyDirectionFilter(Builder $query, $value): void
    {
        if ($value) {
            $query->where('direction', $value);
        }
    }

    /**
     * Apply agent filter
     */
    private function applyAgentIdFilter(Builder $query, $value): void
    {
        if ($value) {
            $query->where('agent_id', $value);
        }
    }

    /**
     * Apply group filter
     */
    private function applyGroupIdFilter(Builder $query, $value): void
    {
        if ($value) {
            $query->where('group_id', $value);
        }
    }

    /**
     * Apply from number filter (partial match)
     */
    private function applyFromNumberFilter(Builder $query, $value): void
    {
        if ($value) {
            $query->where('from_number', 'LIKE', '%' . $value . '%');
        }
    }

    /**
     * Apply to number filter (partial match)
     */
    private function applyToNumberFilter(Builder $query, $value): void
    {
        if ($value) {
            $query->where('to_number', 'LIKE', '%' . $value . '%');
        }
    }

    /**
     * Apply minimum duration filter
     */
    private function applyMinDurationFilter(Builder $query, $value): void
    {
        if ($value !== null && $value >= 0) {
            $query->where('duration', '>=', $value);
        }
    }

    /**
     * Apply maximum duration filter
     */
    private function applyMaxDurationFilter(Builder $query, $value): void
    {
        if ($value !== null && $value >= 0) {
            $query->where('duration', '<=', $value);
        }
    }

    /**
     * Apply session token filter (partial match)
     */
    private function applySessionTokenFilter(Builder $query, $value): void
    {
        if ($value) {
            $query->where('session_token', 'LIKE', '%' . $value . '%');
        }
    }

    /**
     * Get available filter options for a tenant
     */
    public function getFilterOptions(Tenant $tenant): array
    {
        return [
            'statuses' => CallRecord::STATUSES,
            'directions' => CallRecord::DIRECTIONS,
            'agents' => $tenant->voiceAgents()->select('id', 'name')->get(),
            'groups' => $tenant->agentGroups()->select('id', 'name')->get(),
            'date_range' => [
                'min' => CallRecord::where('tenant_id', $tenant->id)->min('start_time'),
                'max' => CallRecord::where('tenant_id', $tenant->id)->max('start_time'),
            ],
        ];
    }

    /**
     * Validate filter parameters
     */
    public function validateFilters(array $filters): array
    {
        $errors = [];

        // Validate date formats
        if (isset($filters['start_date']) && !strtotime($filters['start_date'])) {
            $errors[] = 'Invalid start_date format';
        }

        if (isset($filters['end_date']) && !strtotime($filters['end_date'])) {
            $errors[] = 'Invalid end_date format';
        }

        // Validate date range
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            if (strtotime($filters['start_date']) > strtotime($filters['end_date'])) {
                $errors[] = 'Start date cannot be after end date';
            }
        }

        // Validate status values
        if (isset($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            foreach ($statuses as $status) {
                if (!in_array($status, CallRecord::STATUSES)) {
                    $errors[] = "Invalid status: {$status}";
                }
            }
        }

        // Validate direction
        if (isset($filters['direction']) && !in_array($filters['direction'], CallRecord::DIRECTIONS)) {
            $errors[] = 'Invalid direction';
        }

        // Validate numeric fields
        if (isset($filters['min_duration']) && (!is_numeric($filters['min_duration']) || $filters['min_duration'] < 0)) {
            $errors[] = 'Minimum duration must be a non-negative number';
        }

        if (isset($filters['max_duration']) && (!is_numeric($filters['max_duration']) || $filters['max_duration'] < 0)) {
            $errors[] = 'Maximum duration must be a non-negative number';
        }

        // Validate duration range
        if (isset($filters['min_duration']) && isset($filters['max_duration'])) {
            if ($filters['min_duration'] > $filters['max_duration']) {
                $errors[] = 'Minimum duration cannot be greater than maximum duration';
            }
        }

        return $errors;
    }

    /**
     * Get filter summary for display
     */
    public function getFilterSummary(array $filters): array
    {
        $summary = [];
        $activeFilters = 0;

        foreach ($filters as $key => $value) {
            if (!empty($value) || $value === 0 || $value === '0') {
                $activeFilters++;

                switch ($key) {
                    case 'start_date':
                    case 'end_date':
                        $summary[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . date('M j, Y', strtotime($value));
                        break;

                    case 'status':
                        if (is_array($value)) {
                            $summary[] = 'Status: ' . implode(', ', array_map('ucfirst', $value));
                        } else {
                            $summary[] = 'Status: ' . ucfirst($value);
                        }
                        break;

                    case 'direction':
                        $summary[] = 'Direction: ' . ucfirst($value);
                        break;

                    case 'agent_id':
                        $summary[] = 'Agent: ' . $this->getAgentName($value);
                        break;

                    case 'group_id':
                        $summary[] = 'Group: ' . $this->getGroupName($value);
                        break;

                    case 'from_number':
                    case 'to_number':
                        $summary[] = ucfirst(str_replace('_', ' ', $key)) . ': *' . substr($value, -4);
                        break;

                    case 'min_duration':
                    case 'max_duration':
                        $minutes = floor($value / 60);
                        $seconds = $value % 60;
                        $duration = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";
                        $label = $key === 'min_duration' ? 'Min Duration' : 'Max Duration';
                        $summary[] = "{$label}: {$duration}";
                        break;

                    case 'session_token':
                        $summary[] = 'Session: *' . substr($value, -8);
                        break;

                    default:
                        $summary[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
                }
            }
        }

        return [
            'summary' => $summary,
            'active_filters' => $activeFilters,
        ];
    }

    /**
     * Get agent name by ID
     */
    private function getAgentName($agentId): string
    {
        static $agentNames = [];

        if (!isset($agentNames[$agentId])) {
            $agent = \App\Models\VoiceAgent::find($agentId);
            $agentNames[$agentId] = $agent ? $agent->name : 'Unknown Agent';
        }

        return $agentNames[$agentId];
    }

    /**
     * Get group name by ID
     */
    private function getGroupName($groupId): string
    {
        static $groupNames = [];

        if (!isset($groupNames[$groupId])) {
            $group = \App\Models\AgentGroup::find($groupId);
            $groupNames[$groupId] = $group ? $group->name : 'Unknown Group';
        }

        return $groupNames[$groupId];
    }

    /**
     * Create a saved filter preset
     */
    public function saveFilterPreset(Tenant $tenant, string $name, array $filters, int $userId): bool
    {
        // This would create a saved filter in the database
        // For now, return true as placeholder
        return true;
    }

    /**
     * Get saved filter presets for a user
     */
    public function getFilterPresets(Tenant $tenant, int $userId): array
    {
        // This would retrieve saved filters from the database
        // For now, return empty array as placeholder
        return [];
    }
}