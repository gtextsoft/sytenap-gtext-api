<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait AuthorizesPermissions
{
    /**
     * Check if the user has a specific permission and return a response if not
     */
    protected function checkPermission($permissionName): ?JsonResponse
    {
        if (!auth()->user()->hasPermission($permissionName)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not have the required permissions.'
            ], 403);
        }

        return null;
    }

    /**
     * Check if the user has a specific role and return a response if not
     */
    protected function checkRole($roleName): ?JsonResponse
    {
        if (!auth()->user()->hasRole($roleName)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not have the required role.'
            ], 403);
        }

        return null;
    }

    /**
     * Check if the user has any of the given permissions and return a response if not
     */
    protected function checkAnyPermission($permissions): ?JsonResponse
    {
        if (!auth()->user()->hasAnyPermission($permissions)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not have the required permissions.'
            ], 403);
        }

        return null;
    }

    /**
     * Check if the user has all of the given permissions and return a response if not
     */
    protected function checkAllPermissions($permissions): ?JsonResponse
    {
        if (!auth()->user()->hasAllPermissions($permissions)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You do not have the required permissions.'
            ], 403);
        }

        return null;
    }
}
