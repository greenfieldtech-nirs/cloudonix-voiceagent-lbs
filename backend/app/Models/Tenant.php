<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'settings',
        'is_active',
        'trial_ends_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the users for the tenant.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the integrations for the tenant.
     */
    public function integrations()
    {
        return $this->hasMany(Integration::class);
    }

    /**
     * Get the phone numbers for the tenant.
     */
    public function phoneNumbers()
    {
        return $this->hasMany(PhoneNumber::class);
    }

    /**
     * Get the routing rules for the tenant.
     */
    public function routingRules()
    {
        return $this->hasMany(RoutingRule::class);
    }

    /**
     * Get the voice applications for the tenant.
     */
    public function voiceApplications()
    {
        return $this->hasMany(VoiceApplication::class);
    }

    /**
     * Get the call sessions for the tenant.
     */
    public function callSessions()
    {
        return $this->hasMany(CallSession::class);
    }

    /**
     * Get the call events for the tenant.
     */
    public function callEvents()
    {
        return $this->hasMany(CallEvent::class);
    }
}
