<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\AuthorizesPermissions;
use Illuminate\Support\Facades\Validator;

class RolePermissionController extends Controller
{
    use AuthorizesPermissions;

    /**
     * Get all available roles
     */
    public function getRoles(Request $request)
    {
        $roles = Role::with('permissions')->get();
        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Get all available permissions
     */
    public function getPermissions(Request $request)
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get();
        
        // Group by module
        $grouped = $permissions->groupBy('module');
        
        return response()->json([
            'success' => true,
            'data' => $grouped
        ]);
    }

    /**
     * Get a user's roles and permissions
     */
    public function getUserRoles(Request $request, $userId)
    {
        $permissionError = $this->checkPermission('manage_roles');
        if ($permissionError) {
            return $permissionError;
        }

        $user = User::with(['roles.permissions'])->find($userId);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'roles' => $user->roles,
                'permissions' => $user->roles()
                    ->with('permissions')
                    ->get()
                    ->pluck('permissions')
                    ->flatten()
                    ->unique('id')
            ]
        ]);
    }

    /**
     * Assign a role to a user
     */
    public function assignRoleToUser(Request $request)
    {
        $permissionError = $this->checkPermission('manage_roles');
        if ($permissionError) {
            return $permissionError;
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $role = Role::findOrFail($request->role_id);

        $user->assignRole($role);

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' assigned to {$user->email}",
            'data' => [
                'user_id' => $user->id,
                'role_id' => $role->id
            ]
        ]);
    }

    /**
     * Remove a role from a user
     */
    public function removeRoleFromUser(Request $request)
    {
        $permissionError = $this->checkPermission('manage_roles');
        if ($permissionError) {
            return $permissionError;
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $role = Role::findOrFail($request->role_id);

        $user->removeRole($role);

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' removed from {$user->email}",
            'data' => [
                'user_id' => $user->id,
                'role_id' => $role->id
            ]
        ]);
    }

    /**
     * Grant permission to a role
     */
    public function grantPermissionToRole(Request $request)
    {
        $permissionError = $this->checkPermission('manage_permissions');
        if ($permissionError) {
            return $permissionError;
        }

        $validator = Validator::make($request->all(), [
            'role_id' => 'required|exists:roles,id',
            'permission_id' => 'required|exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::findOrFail($request->role_id);
        $permission = Permission::findOrFail($request->permission_id);

        $role->grantPermission($permission);

        return response()->json([
            'success' => true,
            'message' => "Permission '{$permission->name}' granted to role '{$role->name}'",
            'data' => [
                'role_id' => $role->id,
                'permission_id' => $permission->id
            ]
        ]);
    }

    /**
     * Revoke permission from a role
     */
    public function revokePermissionFromRole(Request $request)
    {
        $permissionError = $this->checkPermission('manage_permissions');
        if ($permissionError) {
            return $permissionError;
        }

        $validator = Validator::make($request->all(), [
            'role_id' => 'required|exists:roles,id',
            'permission_id' => 'required|exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::findOrFail($request->role_id);
        $permission = Permission::findOrFail($request->permission_id);

        $role->revokePermission($permission);

        return response()->json([
            'success' => true,
            'message' => "Permission '{$permission->name}' revoked from role '{$role->name}'",
            'data' => [
                'role_id' => $role->id,
                'permission_id' => $permission->id
            ]
        ]);
    }
}
