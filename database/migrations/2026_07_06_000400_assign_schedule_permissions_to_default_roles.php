<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grant schedule permissions to existing default roles.
     */
    public function up(): void
    {
        $now = now();
        $schedulePermissions = DB::table('permissions')
            ->where('group_name', 'schedule')
            ->whereIn('slug', $this->schedulePermissionSlugs())
            ->get(['id', 'slug'])
            ->keyBy('slug');

        if ($schedulePermissions->isEmpty()) {
            return;
        }

        foreach ($this->rolePermissionMap() as $roleSlug => $permissionSlugs) {
            $role = DB::table('roles')->where('slug', $roleSlug)->first(['id']);
            if (! $role) {
                continue;
            }

            foreach ($permissionSlugs as $permissionSlug) {
                $permission = $schedulePermissions->get($permissionSlug);
                if (! $permission) {
                    continue;
                }

                DB::table('permission_roles')->updateOrInsert(
                    [
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                    ],
                    [
                        'granted_by' => null,
                        'granted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('slug', array_keys($this->rolePermissionMap()))
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', $this->schedulePermissionSlugs())
            ->pluck('id');

        if ($roleIds->isEmpty() || $permissionIds->isEmpty()) {
            return;
        }

        DB::table('permission_roles')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }

    /**
     * @return array<int, string>
     */
    private function schedulePermissionSlugs(): array
    {
        return [
            'schedule.view',
            'schedule.create',
            'schedule.update',
            'schedule.delete',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rolePermissionMap(): array
    {
        $allSchedulePermissions = $this->schedulePermissionSlugs();

        return [
            'super-admin' => $allSchedulePermissions,
            'admin' => $allSchedulePermissions,
            'hr-admin' => $allSchedulePermissions,
            'hr-manager' => $allSchedulePermissions,
            'payroll-manager' => ['schedule.view'],
            'auditor' => ['schedule.view'],
        ];
    }
};
