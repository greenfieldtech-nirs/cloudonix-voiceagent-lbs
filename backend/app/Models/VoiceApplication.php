<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceApplication extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'cxml_definition',
        'settings',
        'provider_app_id',
        'is_active',
    ];

    protected $casts = [
        'cxml_definition' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that owns this voice application.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
