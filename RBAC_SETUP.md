# RBAC Setup Instructions

## Quick Start

### 1. Run Database Migrations
```bash
php artisan migrate
```

This will create the following tables:
- `roles` - Stores role definitions
- `permissions` - Stores permission definitions
- `role_permission` - Links roles to permissions
- `user_role` - Links users to roles

### 2. Seed Initial Roles and Permissions
```bash
php artisan db:seed --class=RolePermissionSeeder
```

This creates:
- **3 Roles**: admin, legal, accountant
- **20+ Permissions**: across documents, properties, commissions, and users
- **Automatic Role Assignments**: Each role is pre-configured with appropriate permissions

### 3. Assign Roles to Users

#### Via Tinker (Laravel REPL)
```bash
php artisan tinker
```

```php
$user = User::find(1);
$user->assignRole('legal');  // Assign legal role
// or
$user->assignRole('accountant');  // Assign accountant role
```

#### Via API Endpoint
```bash
curl -X POST http://localhost:8000/api/v1/rbac/users/assign-role \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "role_id": 2
  }'
```

### 4. Verify Setup

Check if roles are created:
```bash
php artisan tinker
```

```php
Role::all();
Permission::all();
User::find(1)->roles;
User::find(1)->hasPermission('upload_documents');
```

## What Gets Created

### Roles
1. **admin** - Super Administrator (all permissions)
2. **legal** - Legal Officer (document management)
3. **accountant** - Accountant (commission & financial)

### Permissions (20 total)

#### Document Management (4)
- upload_documents
- publish_documents
- delete_documents
- view_all_documents

#### Property Management (7)
- allocate_property
- revoke_property
- view_allocations
- create_plots
- update_plot_id
- manage_estate_plot_details
- manage_estates

#### Commission Management (5)
- view_referrals
- manage_commission_settings
- view_withdrawals
- approve_withdrawals
- reject_withdrawals

#### User Management (4)
- reset_client_password
- create_admin_user
- create_users
- send_portal_access

#### System Management (3)
- view_dashboard
- manage_roles
- manage_permissions

## Testing the Implementation

### Test Permission Check
```php
// In tinker
$user = User::find(1);
$user->assignRole('legal');
$user->hasPermission('upload_documents');  // Returns true
$user->hasPermission('approve_withdrawals');  // Returns false
```

### Test Middleware
```bash
# With permission
curl -X GET http://localhost:8000/api/v1/rbac/permissions \
  -H "Authorization: Bearer TOKEN"

# Should work if user has permission
```

## Troubleshooting

### Tables not created
```bash
php artisan migrate --step
# Check each migration individually
```

### No roles appearing
```bash
php artisan db:seed --class=RolePermissionSeeder
# Check database for entries
```

### User still getting 403 errors
1. Verify user has role assigned: `User::find(id)->roles`
2. Verify role has permission: `Role::find(role_id)->permissions`
3. Clear any caches: `php artisan cache:clear`

## Files Modified/Created

### New Files
- `database/migrations/2025_01_10_000001_create_roles_table.php`
- `database/migrations/2025_01_10_000002_create_permissions_table.php`
- `database/migrations/2025_01_10_000003_create_role_permission_table.php`
- `database/migrations/2025_01_10_000004_create_user_role_table.php`
- `app/Models/Role.php`
- `app/Models/Permission.php`
- `app/Http/Middleware/CheckPermission.php`
- `app/Http/Middleware/CheckRole.php`
- `app/Http/Controllers/Api/RolePermissionController.php`
- `app/Traits/AuthorizesPermissions.php`
- `database/seeders/RolePermissionSeeder.php`
- `RBAC_GUIDE.md` (Documentation)
- `RBAC_SETUP.md` (This file)

### Modified Files
- `app/Models/User.php` - Added role relationships and permission methods
- `app/Http/Controllers/Api/AdminClientController.php` - Updated to use RBAC
- `app/Http/Controllers/Api/CommissionSettingController.php` - Updated to use RBAC
- `bootstrap/app.php` - Registered middleware
- `database/seeders/DatabaseSeeder.php` - Added RolePermissionSeeder call
- `routes/api/v1.php` - Added RBAC endpoints

## Next Steps

1. Update remaining admin controllers to use the new RBAC system
2. Update all admin route documentation
3. Train team on new permission system
4. Monitor and audit permission usage
5. Add additional permissions as needed

## Support

For detailed usage examples, see `RBAC_GUIDE.md`
