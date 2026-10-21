<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User  extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'provider',
        'provider_id',
        'email_verified_at',
        'phone_otp',
        'otp_expires_at',
        'phone_verified_at',
        'lat',
        'lang',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
        ];
    }

    /**
     * Laravel 10 reads $casts, not the casts() method above, so these two
     * Batch 3 timestamps are declared here (the method's existing entries
     * are deliberately left as they were).
     */
    protected $casts = [
        'online_status_changed_at' => 'datetime',
        'last_location_at' => 'datetime',
    ];

    /**
     * Batch 3 Part 4 -- drivers/riders who can receive offers. NULL
     * online_status = never used the toggle (older app build) and is
     * treated as online, exactly as before the toggle existed.
     */
    public function scopeAcceptingJobs($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('users.online_status')->orWhere('users.online_status', 'online');
        });
    }

    public function isOffline(): bool
    {
        return $this->online_status === 'offline';
    }

    /**
     * offline / busy / online -- what admin lists and maps show.
     */
    public function presenceStatus(): string
    {
        if ($this->online_status === 'offline') {
            return 'offline';
        }

        return $this->driver_status === 'busy' ? 'busy' : 'online';
    }

    public function onlineSessions()
    {
        return $this->hasMany(DriverOnlineSession::class, 'driver_id');
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function driverCnic()
    {
        return $this->hasOne(DriverCnic::class, 'driver_id');
    }

    public function driverLicense()
    {
        return $this->hasOne(DriverLicense::class, 'driver_id');
    }

    public function driverVehicle()
    {
        return $this->hasOne(DriverVehicle::class, 'driver_id');
    }

    public function driverSelfie()
    {
        return $this->hasOne(DriverSelfie::class, 'driver_id');
    }

    public function driverVerification()
    {
        return $this->hasOne(DriverVerification::class, 'driver_id');
    }

    public function driverReviews()
    {
        return $this->hasMany(DriverReview::class, 'driver_id');
    }

    public function chauffeurFavourites()
    {
        return $this->hasMany(ChauffeurFavourite::class, 'user_id');
    }

    public function userWorkspaces()
    {
        return $this->hasMany(\App\Models\UserWorkspace::class);
    }

    public function termsAcceptedVersion()
    {
        return $this->belongsTo(TermsAndCondition::class, 'terms_accepted_version_id');
    }

    /**
     * False if the user never accepted any version, or accepted an older
     * version than the currently published one.
     */
    public function hasAcceptedCurrentTerms(): bool
    {
        $current = TermsAndCondition::current();

        if (!$current) {
            return true; // nothing published yet -- nothing to gate on
        }

        return $this->terms_accepted_version_id === $current->id;
    }

    /**
     * Workspace keys this user is allowed to switch into. Super admins
     * bypass the pivot entirely and get every workspace that exists.
     */
    public function allowedWorkspaces(): array
    {
        if ($this->hasRole('super-admin')) {
            return array_keys(config('workspaces.workspaces'));
        }

        return $this->userWorkspaces()->pluck('workspace')->all();
    }
}
