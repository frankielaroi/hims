<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AHPC draws a line between registering a sample and entering raw metrics — which
 * a Medical Laboratory Assistant may do — and authorising a result for release,
 * which only a licensed Medical Laboratory Scientist or Pathologist may do. The
 * single `process_lab_orders` permission fused the two, so it is split here.
 *
 * Roles that held it inherit the assistant-level scope only. Granting
 * `authorize_lab_results` is a deliberate act that names a licensed person, so it
 * is never handed out automatically by a migration.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const NEW_PERMISSIONS = [
        'collect_lab_specimens' => 'Can register samples and collect specimens for the laboratory',
        'enter_lab_results' => 'Can enter raw laboratory result values',
        'authorize_lab_results' => 'Can authorise and verify laboratory results for release',
        'manage_lab_inventory' => 'Can manage laboratory reagent stock',
    ];

    /**
     * @var list<string>
     */
    private const INHERITED_BY_EXISTING_HOLDERS = [
        'collect_lab_specimens',
        'enter_lab_results',
        'manage_lab_inventory',
    ];

    public function up(): void
    {
        foreach (self::NEW_PERMISSIONS as $slug => $name) {
            $this->ensurePermission($slug, $name);
        }

        $retiredId = DB::table('permissions')->where('slug', 'process_lab_orders')->value('id');

        if ($retiredId === null) {
            return;
        }

        $roleIds = DB::table('permission_role')->where('permission_id', $retiredId)->pluck('role_id');

        foreach (self::INHERITED_BY_EXISTING_HOLDERS as $slug) {
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        DB::table('permission_role')->where('permission_id', $retiredId)->delete();
        DB::table('permissions')->where('id', $retiredId)->delete();
    }

    public function down(): void
    {
        $retiredId = $this->ensurePermission('process_lab_orders', 'Can collect specimens and record/verify lab results');

        $collectId = DB::table('permissions')->where('slug', 'collect_lab_specimens')->value('id');

        if ($collectId !== null) {
            $roleIds = DB::table('permission_role')->where('permission_id', $collectId)->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $retiredId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $newIds = DB::table('permissions')->whereIn('slug', array_keys(self::NEW_PERMISSIONS))->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $newIds)->delete();
        DB::table('permissions')->whereIn('id', $newIds)->delete();
    }

    private function ensurePermission(string $slug, string $name): int
    {
        $existing = DB::table('permissions')->where('slug', $slug)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('permissions')->insertGetId([
            'slug' => $slug,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
