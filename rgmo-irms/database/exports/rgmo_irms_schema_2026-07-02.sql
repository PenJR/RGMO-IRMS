PRAGMA foreign_keys=OFF;

CREATE TABLE "audit_logs" ("id" integer primary key autoincrement not null, "user_id" integer, "action" varchar not null, "module" varchar not null, "details" text, "created_at" datetime, "updated_at" datetime, "ip_address" varchar, "model_type" varchar, "model_id" integer, "old_values" text, "new_values" text, foreign key("user_id") references "users"("id") on delete set null);

CREATE TABLE "backup_histories" ("id" integer primary key autoincrement not null, "backup_name" varchar not null, "file_path" varchar not null, "file_size" varchar not null, "status" varchar not null default 'pending', "backed_up_at" datetime, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));

CREATE TABLE "categories" ("id" integer primary key autoincrement not null, "name" varchar not null, "description" text, "created_at" datetime, "updated_at" datetime, "deleted_at" datetime);

CREATE TABLE "inventory_items" ("id" integer primary key autoincrement not null, "category_id" integer not null, "name" varchar not null, "sku" varchar not null, "stock" integer not null default '0', "unit" varchar not null, "min_stock" integer not null default '10', "description" text, "created_at" datetime, "updated_at" datetime, "deleted_at" datetime, "price" numeric, "reorder_level" integer, "planting_date" datetime, "has_expiry" tinyint(1) not null default '0', "expiry_date" date, foreign key("category_id") references "categories"("id") on update cascade);

CREATE TABLE "inventory_transactions" ("id" integer primary key autoincrement not null, "inventory_item_id" integer not null, "user_id" integer, "transaction_type" varchar not null, "quantity" integer not null, "source" varchar, "destination" varchar, "meta" text, "created_at" datetime, "updated_at" datetime, "funding_source" varchar, foreign key("inventory_item_id") references "inventory_items"("id") on delete cascade, foreign key("user_id") references "users"("id") on delete set null);

CREATE TABLE "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);

CREATE TABLE "login_histories" ("id" integer primary key autoincrement not null, "user_id" integer not null, "ip_address" varchar not null, "user_agent" varchar, "login_at" datetime not null, "logout_at" datetime, "created_at" datetime, "updated_at" datetime, "user_role" varchar, foreign key("user_id") references "users"("id") on delete cascade);

CREATE TABLE "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);

CREATE TABLE "notifications" ("id" integer primary key autoincrement not null, "user_id" integer not null, "type" varchar not null, "message" text not null, "read_at" datetime, "created_at" datetime, "updated_at" datetime, "title" varchar not null default 'Notification', "sender_id" integer, "recipient_role" varchar, "related_request_id" integer, "data" text, foreign key("user_id") references users("id") on delete cascade on update no action, foreign key("sender_id") references "users"("id") on delete set null, foreign key("related_request_id") references "resource_requests"("id") on delete set null);

CREATE TABLE "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));

CREATE TABLE "project_user" ("id" integer primary key autoincrement not null, "project_id" integer not null, "user_id" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("project_id") references "projects"("id") on delete cascade, foreign key("user_id") references "users"("id") on delete cascade);

CREATE TABLE "projects" ("id" integer primary key autoincrement not null, "name" varchar not null, "code" varchar not null, "status" varchar not null default 'active', "start_date" date, "end_date" date, "description" text, "created_at" datetime, "updated_at" datetime, "deleted_at" datetime);

CREATE TABLE "request_items" ("id" integer primary key autoincrement not null, "resource_request_id" integer not null, "inventory_item_id" integer not null, "quantity" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("resource_request_id") references "resource_requests"("id") on delete cascade, foreign key("inventory_item_id") references "inventory_items"("id") on update cascade);

CREATE TABLE "resource_requests" ("id" integer primary key autoincrement not null, "user_id" integer not null, "status" varchar not null default 'pending', "purpose" text not null, "remarks" text, "approved_by" integer, "approved_at" datetime, "cancelled_at" datetime, "created_at" datetime, "updated_at" datetime, "requested_date" datetime, "needed_date" datetime, "deleted_at" datetime, "rejected_at" datetime, "ris_no" varchar, "responsible_center" varchar, foreign key("user_id") references "users"("id") on update cascade, foreign key("approved_by") references "users"("id") on delete set null);

CREATE TABLE "resource_usages" ("id" integer primary key autoincrement not null, "inventory_item_id" integer not null, "user_id" integer, "field_id" varchar, "quantity" integer not null, "notes" text, "created_at" datetime, "updated_at" datetime, "project_id" integer, foreign key("user_id") references users("id") on delete set null on update no action, foreign key("inventory_item_id") references inventory_items("id") on delete cascade on update no action, foreign key("project_id") references "projects"("id") on delete set null);

CREATE TABLE "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));

CREATE TABLE "system_settings" ("id" integer primary key autoincrement not null, "key" varchar not null, "value" text, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "user_activity_logs" ("id" integer primary key autoincrement not null, "user_id" integer not null, "activity" varchar not null, "context" text, "created_at" datetime, "updated_at" datetime, "ip_address" varchar, foreign key("user_id") references "users"("id") on delete cascade);

CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "password" varchar not null, "role" varchar not null default 'staff', "is_active" tinyint(1) not null default '1', "last_login_at" datetime, "remember_token" varchar, "created_at" datetime, "updated_at" datetime, "status" varchar check ("status" in ('active', 'inactive', 'suspended')) not null default 'active', "avatar" varchar, "login_attempts" integer not null default '0', "locked_until" datetime, "email_verified_at" datetime, "two_factor_enabled" tinyint(1) not null default '0', "two_factor_secret" varchar, "department" varchar);

CREATE UNIQUE INDEX "categories_name_unique" on "categories" ("name");

CREATE UNIQUE INDEX "inventory_items_sku_unique" on "inventory_items" ("sku");

CREATE INDEX "jobs_queue_index" on "jobs" ("queue");

CREATE INDEX "login_histories_user_role_index" on "login_histories" ("user_role");

CREATE INDEX "notifications_recipient_role_index" on "notifications" ("recipient_role");

CREATE UNIQUE INDEX "project_user_project_id_user_id_unique" on "project_user" ("project_id", "user_id");

CREATE UNIQUE INDEX "projects_code_unique" on "projects" ("code");

CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");

CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");

CREATE UNIQUE INDEX "system_settings_key_unique" on "system_settings" ("key");

CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");

PRAGMA foreign_keys=ON;
