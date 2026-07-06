<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add schedule module permissions for existing databases.
     */
    public function up(): void
    {
        $now = now();
        $hasAccessScopeColumns = Schema::hasColumn('permissions', 'access_scope');

        foreach ($this->schedulePermissions() as $permission) {
            $values = [
                'group_name' => 'schedule',
                'name' => $permission['name'],
                'description' => $permission['description'],
                'updated_at' => $now,
            ];

            if ($hasAccessScopeColumns) {
                $values = array_merge($values, $this->adminScopeMeta());
            }

            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                array_merge($values, ['created_at' => $now])
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('slug', array_column($this->schedulePermissions(), 'slug'))
            ->delete();
    }

    /**
     * @return array<int, array{name: string, slug: string, description: string}>
     */
    private function schedulePermissions(): array
    {
        return [
            ['name' => 'View Schedule', 'slug' => 'schedule.view', 'description' => 'View Schedule'],
            ['name' => 'Create Schedule', 'slug' => 'schedule.create', 'description' => 'Create Schedule'],
            ['name' => 'Update Schedule', 'slug' => 'schedule.update', 'description' => 'Update Schedule'],
            ['name' => 'Delete Schedule', 'slug' => 'schedule.delete', 'description' => 'Delete Schedule'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function adminScopeMeta(): array
    {
        return [
            'access_scope' => 'admin',
            'access_scope_label' => 'Admin / Global',
            'access_scope_badge_class' => 'bg-danger',
            'access_scope_description' => 'User can access company-wide records, setup, approval, payroll, or reports.',
        ];
    }
};
