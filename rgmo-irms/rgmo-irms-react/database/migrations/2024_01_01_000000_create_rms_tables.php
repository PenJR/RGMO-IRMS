<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support:Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users Table with Roles
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'staff', 'head'])->default('staff');
            $table->rememberToken();
            $table->timestamps();
        });

        // Categories for Inventory
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Inventory Items
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('name');
            $table->integer('stock')->default(0);
            $table->string('unit'); // kg, liters, pcs
            $table->integer('min_stock')->default(10);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Requests for resources
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('purpose');
            $table->text('admin_remarks')->nullable();
            $table->text('head_remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // Request Items (Many-to-Many request-item)
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained();
            $table->integer('quantity');
            $table->timestamps();
        });

        // Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('action');
            $table->text('details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('request_items');
        Schema::dropIfExists('requests');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
    }
};
