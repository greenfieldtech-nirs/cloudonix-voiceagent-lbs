<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VoiceAgent extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'provider',
        'service_value',
        'username',
        'password',
        'enabled',
        'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'username',
        'password',
    ];

    /**
     * Supported voice agent providers
     */
    public const PROVIDERS = [
        'synthflow',
        'dasha',
        'superdash.ai',
        'elevenlabs',
        'deepvox',
        'relayhawk',
        'voicehub',
        'retell-udp',
        'retell-tcp',
        'retell-tls',
        'retell',
        'vapi',
        'fonio',
        'sigmamind',
        'modon',
        'puretalk',
        'millis-us',
        'millis-eu',
    ];

    /**
     * Get the tenant that owns this voice agent
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the agent groups this agent belongs to
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(AgentGroup::class, 'agent_group_memberships')
            ->withPivot('priority', 'capacity')
            ->withTimestamps();
    }

    /**
     * Get the call records for this agent
     */
    public function callRecords()
    {
        return $this->hasMany(CallRecord::class, 'agent_id');
    }

    /**
     * Scope to only enabled agents
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to specific provider
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Get decrypted username
     */
    public function getUsernameAttribute($value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    /**
     * Set encrypted username
     */
    public function setUsernameAttribute($value): void
    {
        $this->attributes['username'] = $value ? encrypt($value) : null;
    }

    /**
     * Get decrypted password
     */
    public function getPasswordAttribute($value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    /**
     * Set encrypted password
     */
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = $value ? encrypt($value) : null;
    }

    /**
     * Check if provider requires authentication
     */
    public function requiresAuthentication(): bool
    {
        return in_array($this->provider, ['synthflow', 'superdash.ai', 'elevenlabs']);
    }

    /**
     * Get provider display name
     */
    public function getProviderDisplayName(): string
    {
        return match($this->provider) {
            'synthflow' => 'Synthflow',
            'dasha' => 'Dasha',
            'superdash.ai' => 'Superdash',
            'elevenlabs' => 'Eleven Labs',
            'deepvox' => 'Deepvox',
            'relayhawk' => 'Relay Hawk',
            'voicehub' => 'Voice Hub',
            'retell-udp' => 'Retell (UDP)',
            'retell-tcp' => 'Retell (TCP)',
            'retell-tls' => 'Retell (TLS)',
            'retell' => 'Retell',
            'vapi' => 'VAPI',
            'fonio' => 'Fonio',
            'sigmamind' => 'Sigma Mind',
            'modon' => 'Modon',
            'puretalk' => 'PureTalk',
            'millis-us' => 'Millis (US)',
            'millis-eu' => 'Millis (EU)',
            default => ucfirst($this->provider),
        };
    }
}
