<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Kalau di SQLite (testing), migration ini tidak perlu — SQLite
        // sudah otomatis bikin index untuk foreign key & unique
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        // Helper cek index (khusus MySQL/MariaDB)
        $hasIndex = function ($table, $indexName) {
            return collect(DB::select("SHOW INDEX FROM {$table}"))
                ->pluck('Key_name')
                ->contains($indexName);
        };

        // SHORT LINKS
        Schema::table('short_links', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('short_links', 'idx_short_links_status')) {
                $table->index('status', 'idx_short_links_status');
            }
            if (!$hasIndex('short_links', 'idx_short_links_created_at')) {
                $table->index('created_at', 'idx_short_links_created_at');
            }
        });

        // SHORT LINK CLICKS
        Schema::table('short_link_clicks', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('short_link_clicks', 'idx_clicks_clicked_at')) {
                $table->index('clicked_at', 'idx_clicks_clicked_at');
            }
            if (!$hasIndex('short_link_clicks', 'idx_clicks_short_link_id')) {
                $table->index('short_link_id', 'idx_clicks_short_link_id');
            }
        });

        // LINK HUBS
        Schema::table('link_hubs', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('link_hubs', 'idx_link_hubs_status')) {
                $table->index('status', 'idx_link_hubs_status');
            }
        });

        // LINK HUB ITEMS
        Schema::table('link_hub_items', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('link_hub_items', 'idx_items_sort_order')) {
                $table->index('sort_order', 'idx_items_sort_order');
            }
        });

        // USERS
        Schema::table('users', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('users', 'idx_users_role')) {
                $table->index('role', 'idx_users_role');
            }
            if (!$hasIndex('users', 'idx_users_unit_kerja')) {
                $table->index('unit_kerja_id', 'idx_users_unit_kerja');
            }
        });

        // AUDIT LOGS
        Schema::table('audit_logs', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('audit_logs', 'idx_audit_created_at')) {
                $table->index('created_at', 'idx_audit_created_at');
            }
            if (!$hasIndex('audit_logs', 'idx_audit_user_id')) {
                $table->index('user_id', 'idx_audit_user_id');
            }
        });

        // FEEDBACKS
        Schema::table('feedbacks', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('feedbacks', 'idx_feedbacks_user_id')) {
                $table->index('user_id', 'idx_feedbacks_user_id');
            }
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('short_links', function (Blueprint $table) {
            $table->dropIndex('idx_short_links_status');
            $table->dropIndex('idx_short_links_created_at');
        });

        Schema::table('short_link_clicks', function (Blueprint $table) {
            $table->dropIndex('idx_clicks_clicked_at');
            $table->dropIndex('idx_clicks_short_link_id');
        });

        Schema::table('link_hubs', function (Blueprint $table) {
            $table->dropIndex('idx_link_hubs_status');
        });

        Schema::table('link_hub_items', function (Blueprint $table) {
            $table->dropIndex('idx_items_sort_order');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role');
            $table->dropIndex('idx_users_unit_kerja');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_created_at');
            $table->dropIndex('idx_audit_user_id');
        });

        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropIndex('idx_feedbacks_user_id');
        });
    }
};