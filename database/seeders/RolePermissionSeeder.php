<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Document Management
            ['name' => 'upload_documents', 'description' => 'Upload documents', 'module' => 'documents'],
            ['name' => 'publish_documents', 'description' => 'Publish/Unpublish documents', 'module' => 'documents'],
            ['name' => 'delete_documents', 'description' => 'Delete documents', 'module' => 'documents'],
            ['name' => 'view_all_documents', 'description' => 'View all documents', 'module' => 'documents'],

            // Property Management
            ['name' => 'allocate_property', 'description' => 'Allocate properties to customers', 'module' => 'properties'],
            ['name' => 'revoke_property', 'description' => 'Revoke allocated properties', 'module' => 'properties'],
            ['name' => 'view_allocations', 'description' => 'View all property allocations', 'module' => 'properties'],
            ['name' => 'create_plots', 'description' => 'Generate plots for estates', 'module' => 'properties'],
            ['name' => 'update_plot_id', 'description' => 'Update plot IDs', 'module' => 'properties'],
            ['name' => 'manage_estate_plot_details', 'description' => 'Manage estate-plot details', 'module' => 'properties'],
            ['name' => 'manage_estates', 'description' => 'Create/Update/Delete estates', 'module' => 'properties'],

            // Commission Management
            ['name' => 'view_referrals', 'description' => 'View all referrals', 'module' => 'commissions'],
            ['name' => 'manage_commission_settings', 'description' => 'Create and manage commission settings', 'module' => 'commissions'],
            ['name' => 'view_withdrawals', 'description' => 'View commission withdrawal requests', 'module' => 'commissions'],
            ['name' => 'approve_withdrawals', 'description' => 'Approve commission withdrawals', 'module' => 'commissions'],
            ['name' => 'reject_withdrawals', 'description' => 'Reject commission withdrawals', 'module' => 'commissions'],

            // User Management
            ['name' => 'reset_client_password', 'description' => 'Reset client passwords', 'module' => 'users'],
            ['name' => 'create_admin_user', 'description' => 'Create admin users', 'module' => 'users'],
            ['name' => 'create_users', 'description' => 'Create users from admin panel', 'module' => 'users'],
            ['name' => 'send_portal_access', 'description' => 'Send portal access information', 'module' => 'users'],

            // System Management
            ['name' => 'view_dashboard', 'description' => 'Access admin dashboard', 'module' => 'system'],
            ['name' => 'manage_roles', 'description' => 'Manage user roles', 'module' => 'system'],
            ['name' => 'manage_permissions', 'description' => 'Manage permissions', 'module' => 'system'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        // Create roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Super Administrator - Full access to all features']
        );

        $legalRole = Role::firstOrCreate(
            ['name' => 'legal'],
            ['description' => 'Legal Officer - Manages documents and legal matters']
        );

        $accountantRole = Role::firstOrCreate(
            ['name' => 'accountant'],
            ['description' => 'Accountant - Manages commissions and withdrawals']
        );

        // Assign permissions to roles
        // Admin gets all permissions
        $adminPermissions = Permission::pluck('id')->toArray();
        $adminRole->permissions()->sync($adminPermissions);

        // Legal Officer - Document Management permissions
        $legalPermissions = Permission::whereIn('name', [
            'upload_documents',
            'publish_documents',
            'delete_documents',
            'view_all_documents',
            'view_dashboard',
        ])->pluck('id')->toArray();
        $legalRole->permissions()->sync($legalPermissions);

        // Accountant - Commission and Financial permissions
        $accountantPermissions = Permission::whereIn('name', [
            'view_referrals',
            'manage_commission_settings',
            'view_withdrawals',
            'approve_withdrawals',
            'reject_withdrawals',
            'view_dashboard',
        ])->pluck('id')->toArray();
        $accountantRole->permissions()->sync($accountantPermissions);
    }
}
