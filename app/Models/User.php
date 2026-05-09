<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\AccountType;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'account_type',
        'password',
        'state',
        'country',
        'outstanding_balance',
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
            'account_type' => AccountType::class,
        ];
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'user_id');
    }

    /**
     * Get all roles for this user
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /**
     * Get all permissions for this user (through roles)
     */
    public function permissions()
    {
        return $this->hasManyThrough(
            Permission::class,
            Role::class,
            'id',
            'id',
            'id',
            'id'
        )->through('user_role', null, null, null, 'role_permission');
    }

    /**
     * Check if user has a role
     */
    public function hasRole($roleName): bool
    {
        if (is_array($roleName)) {
            return $this->roles()
                ->whereIn('name', $roleName)
                ->exists();
        }

        return $this->roles()
            ->where('name', $roleName)
            ->exists();
    }

    /**
     * Check if user has a permission
     */
    public function hasPermission($permissionName): bool
    {
        // Check if user is an admin (super admin with account_type = admin)
        if ($this->account_type === AccountType::ADMIN) {
            return true;
        }

        // Check through roles
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission($permissions): bool
    {
        if (is_string($permissions)) {
            return $this->hasPermission($permissions);
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissions) {
                $query->whereIn('name', $permissions);
            })
            ->exists();
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions($permissions): bool
    {
        if (is_string($permissions)) {
            return $this->hasPermission($permissions);
        }

        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assign a role to the user
     */
    public function assignRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching($role->id);
    }

    /**
     * Remove a role from the user
     */
    public function removeRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $this->roles()->detach($role->id);
    }

    /**
     * Check if user is a customer (client or prospect)
     */
    public function isCustomer(): bool
    {
        return $this->account_type === AccountType::CLIENT || 
               $this->account_type === AccountType::PROSPECT;
    }

}
