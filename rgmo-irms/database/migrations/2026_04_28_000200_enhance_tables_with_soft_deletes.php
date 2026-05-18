<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add soft deletes to InventoryItem
        Schema::table('inventory_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_items', 'deleted_at')) {
                $table->softDeletes();
            }
            if (!Schema::hasColumn('inventory_items', 'price')) {
                $table->decimal('price', 12, 2)->nullable()->after('unit');
            }
            if (!Schema::hasColumn('inventory_items', 'reorder_level')) {
                $table->integer('reorder_level')->nullable()->after('min_stock');
            }
        });

        // Add soft deletes to Category
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Enhance ResourceRequest
        Schema::table('resource_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('resource_requests', 'requested_date')) {
                $table->timestamp('requested_date')->nullable()->after('purpose');
            }
            if (!Schema::hasColumn('resource_requests', 'needed_date')) {
                $table->timestamp('needed_date')->nullable()->after('requested_date');
            }
            if (!Schema::hasColumn('resource_requests', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Add IP address and details to AuditLog
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('module');
            }
            if (!Schema::hasColumn('audit_logs', 'model_type')) {
                $table->string('model_type')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('audit_logs', 'model_id')) {
                $table->unsignedBigInteger('model_id')->nullable()->after('model_type');
            }
            if (!Schema::hasColumn('audit_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('details');
            }
            if (!Schema::hasColumn('audit_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }
        });

        // Add deleted_at to UserActivityLog
        Schema::table('user_activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('user_activity_logs', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('activity');
            }
        });

        // Create login_histories table if needed
        if (!Schema::hasTable('login_histories')) {
            Schema::create('login_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('ip_address');
                $table->string('user_agent')->nullable();
                $table->timestamp('login_at');
                $table->timestamp('logout_at')->nullable();
                $table->timestamps();
            });
        }

        // Create backup_histories table
        if (!Schema::hasTable('backup_histories')) {
            Schema::create('backup_histories', function (Blueprint $table) {
                $table->id();
                $table->string('backup_name');
                $table->string('file_path');
                $table->string('file_size');
                $table->string('status')->default('pending');
                $table->timestamp('backed_up_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_histories');
        Schema::dropIfExists('login_histories');

        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'model_type', 'model_id', 'old_values', 'new_values']);
        });

        Schema::table('resource_requests', function (Blueprint $table) {
            $table->dropColumn(['requested_date', 'needed_date', 'deleted_at']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'price', 'reorder_level']);
        });
    }
};
