<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'title')) {
                $table->string('title')->default('Notification')->after('user_id');
            }

            if (! Schema::hasColumn('notifications', 'sender_id')) {
                $table->foreignId('sender_id')->nullable()->after('message')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('notifications', 'recipient_role')) {
                $table->string('recipient_role')->nullable()->after('sender_id')->index();
            }

            if (! Schema::hasColumn('notifications', 'related_request_id')) {
                $table->foreignId('related_request_id')->nullable()->after('recipient_role')->constrained('resource_requests')->nullOnDelete();
            }

            if (! Schema::hasColumn('notifications', 'data')) {
                $table->json('data')->nullable()->after('related_request_id');
            }
        });

        Schema::table('login_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('login_histories', 'user_role')) {
                $table->string('user_role')->nullable()->after('user_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'related_request_id')) {
                $table->dropConstrainedForeignId('related_request_id');
            }

            if (Schema::hasColumn('notifications', 'sender_id')) {
                $table->dropConstrainedForeignId('sender_id');
            }

            foreach (['title', 'recipient_role', 'data'] as $column) {
                if (Schema::hasColumn('notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('login_histories', function (Blueprint $table) {
            if (Schema::hasColumn('login_histories', 'user_role')) {
                $table->dropColumn('user_role');
            }
        });
    }
};
