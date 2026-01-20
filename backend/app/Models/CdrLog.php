<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CdrLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'call_id',
        'session_token',
        'from_number',
        'to_number',
        'direction',
        'disposition',
        'start_time',
        'answer_time',
        'end_time',
        'duration_seconds',
        'billsec',
        'domain',
        'subscriber',
        'cx_trunk_id',
        'application',
        'route',
        'vapp_server',
        'raw_cdr',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'answer_time' => 'datetime',
        'end_time' => 'datetime',
        'raw_cdr' => 'array',
    ];

    /**
     * Get the tenant that owns this CDR record
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to filter by tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        if ($endDate) {
            return $query->whereBetween('start_time', [$startDate, $endDate]);
        }

        return $query->whereDate('start_time', '>=', $startDate);
    }

    /**
     * Scope to filter by disposition
     */
    public function scopeByDisposition($query, $disposition)
    {
        return $query->where('disposition', $disposition);
    }
}
