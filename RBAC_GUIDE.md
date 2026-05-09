# Role-Based Access Control (RBAC) Implementation Guide

## Overview

This application now uses a comprehensive Role-Based Access Control (RBAC) system to manage admin access and permissions. Instead of checking `account_type` directly in controllers, we use a flexible role and permission system.

## Architecture

```
User → User_Role (pivot) → Role → Role_Permission (pivot) → Permission
```

### Key Models

- **User**: Base user model with relationships to roles
- **Role**: Defines admin roles (e.g., admin, legal, accountant)
- **Permission**: Defines specific actions/permissions
- **User_Role**: Pivot table linking users to roles
- **Role_Permission**: Pivot table linking roles to permissions

## Available Roles

### 1. Admin (Super Administrator)
- Has **all** permissions in the system
- Can manage users, roles, and permissions
- Can access all admin endpoints

### 2. Legal Officer
- Can upload documents
- Can publish/unpublish documents
- Can delete documents
- Can view all documents
- Can view dashboard

### 3. Accountant
- Can view referrals
- Can manage commission settings
- Can view withdrawal requests
- Can approve/reject withdrawals
- Can view dashboard

## Permissions by Module

### Document Management
- `upload_documents` - Upload new documents
- `publish_documents` - Publish/unpublish documents
- `delete_documents` - Delete documents
- `view_all_documents` - View all documents

### Property Management
- `allocate_property` - Allocate properties to customers
- `revoke_property` - Revoke allocated properties
- `view_allocations` - View all allocations
- `create_plots` - Generate plots for estates
- `update_plot_id` - Update plot IDs
- `manage_estate_plot_details` - Manage estate-plot details
- `manage_estates` - Create/Update/Delete estates

### Commission Management
- `view_referrals` - View all referrals
- `manage_commission_settings` - Create and manage commission settings
- `view_withdrawals` - View withdrawal requests
- `approve_withdrawals` - Approve withdrawals
- `reject_withdrawals` - Reject withdrawals

### User Management
- `reset_client_password` - Reset client passwords
- `create_admin_user` - Create new admin users
- `create_users` - Create users from admin panel
- `send_portal_access` - Send portal access information

### System Management
- `view_dashboard` - Access admin dashboard
- `manage_roles` - Manage user roles
- `manage_permissions` - Manage permissions

## Using RBAC in Your Code

### 1. Check Permissions in Controllers

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\AuthorizesPermissions;

class YourController extends Controller
{
    use AuthorizesPermissions;

    public function someAction(Request $request)
    {
        // Check single permission
        $error = $this->checkPermission('upload_documents');
        if ($error) {
            return $error; // Returns 403 response
        }

        // Your logic here
    }
}
```

### 2. Check Multiple Permissions

```php
// Check if user has ANY of the permissions
$error = $this->checkAnyPermission(['upload_documents', 'publish_documents']);
if ($error) {
    return $error;
}

// Check if user has ALL of the permissions
$error = $this->checkAllPermissions(['upload_documents', 'publish_documents']);
if ($error) {
    return $error;
}
```

### 3. Check Roles

```php
// Check single role
$error = $this->checkRole('legal');
if ($error) {
    return $error;
}

// Check multiple roles
$error = $this->checkRole(['legal', 'admin']);
if ($error) {
    return $error;
}
```

### 4. Using Middleware in Routes

```php
// Check permission via middleware
Route::post('/documents', [DocumentController::class, 'store'])
    ->middleware('auth:sanctum')
    ->middleware('permission:upload_documents');

// Check role via middleware
Route::post('/admin/settings', [AdminController::class, 'updateSettings'])
    ->middleware('auth:sanctum')
    ->middleware('role:admin');
```

### 5. Direct Permission Checks

```php
// In any controller or service
$user = auth()->user();

// Check single permission
if ($user->hasPermission('upload_documents')) {
    // User can upload documents
}

// Check multiple permissions
if ($user->hasAnyPermission(['upload_documents', 'publish_documents'])) {
    // User can either upload or publish
}

if ($user->hasAllPermissions(['upload_documents', 'publish_documents'])) {
    // User can do both
}

// Check role
if ($user->hasRole('legal')) {
    // User is a legal officer
}

// Check if user is customer
if ($user->isCustomer()) {
    // User is either client or prospect
}
```

## API Endpoints for RBAC Management

### Get All Roles
```
GET /api/v1/rbac/roles
```

Response:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "admin",
            "description": "Super Administrator...",
            "permissions": [...]
        }
    ]
}
```

### Get All Permissions
```
GET /api/v1/rbac/permissions
```

Response:
```json
{
    "success": true,
    "data": {
        "documents": [...],
        "properties": [...],
        "commissions": [...]
    }
}
```

### Get User's Roles and Permissions
```
GET /api/v1/rbac/users/{user_id}/roles
Authorization: Bearer {token}
```

### Assign Role to User
```
POST /api/v1/rbac/users/assign-role
Authorization: Bearer {token}

{
    "user_id": 5,
    "role_id": 2
}
```

### Remove Role from User
```
POST /api/v1/rbac/users/remove-role
Authorization: Bearer {token}

{
    "user_id": 5,
    "role_id": 2
}
```

### Grant Permission to Role
```
POST /api/v1/rbac/roles/grant-permission
Authorization: Bearer {token}

{
    "role_id": 2,
    "permission_id": 5
}
```

### Revoke Permission from Role
```
POST /api/v1/rbac/roles/revoke-permission
Authorization: Bearer {token}

{
    "role_id": 2,
    "permission_id": 5
}
```

## Adding New Permissions

### Step 1: Run Migration
The permissions are seeded automatically. To add a new one:

```php
// In a seeder or controller
Permission::create([
    'name' => 'can_export_reports',
    'description' => 'Can export reports to CSV',
    'module' => 'reports'
]);
```

### Step 2: Assign to Roles
```php
$role = Role::where('name', 'accountant')->first();
$permission = Permission::where('name', 'can_export_reports')->first();
$role->grantPermission($permission);
```

### Step 3: Use in Code
```php
if ($user->hasPermission('can_export_reports')) {
    // Allow export
}
```

## Important Notes

1. **Super Admin**: Users with `account_type = 'admin'` automatically have all permissions regardless of their roles.

2. **Prospect vs Client**: Both are treated as customers via the `isCustomer()` helper method.

3. **Role Assignments**: A user can have multiple roles. Their permissions are the union of all role permissions.

4. **Middleware Usage**: Always use `auth:sanctum` before permission/role middleware:
   ```php
   ->middleware('auth:sanctum', 'permission:upload_documents')
   ```

5. **Database**: Run migrations before using:
   ```bash
   php artisan migrate
   php artisan db:seed --class=RolePermissionSeeder
   ```

## Migration Path for Existing Admin Endpoints

When updating existing endpoints that check `$user->account_type->value !== 'admin'`:

### Before
```php
if ($admin->account_type->value !== 'admin') {
    return response()->json(['message' => 'Access denied'], 403);
}
```

### After
```php
use App\Traits\AuthorizesPermissions;

class YourController extends Controller {
    use AuthorizesPermissions;

    public function yourAction() {
        $error = $this->checkPermission('your_permission_name');
        if ($error) return $error;
    }
}
```

## Troubleshooting

### User doesn't have permission
1. Check if user is assigned to a role: `$user->roles`
2. Check if role has permission: `$role->permissions`
3. Verify permission exists: `Permission::where('name', 'xxx')->exists()`

### Permission middleware not working
1. Ensure `auth:sanctum` middleware is applied first
2. Check middleware is registered in `bootstrap/app.php`
3. Verify permission name is spelled correctly

### Database issues
```bash
# Re-run migrations
php artisan migrate:fresh --seed

# Or just seed
php artisan db:seed --class=RolePermissionSeeder
```
