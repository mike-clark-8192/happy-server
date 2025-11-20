<?php

namespace Happy\Storage;

use Illuminate\Database\Schema\Blueprint;

/**
 * Migration runner for creating database tables.
 * Call Migration::run() to create all tables.
 */
class Migration
{
    /**
     * Run all migrations to create the database schema.
     */
    public static function run(): void
    {
        $schema = Database::schema();

        // Accounts table
        if (!$schema->hasTable('accounts')) {
            $schema->create('accounts', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('github_username')->unique()->nullable();
                $table->string('github_user_id')->unique()->nullable();
                $table->string('public_key')->unique();
                $table->text('profile_encrypted')->nullable();
                $table->text('settings_encrypted')->nullable();
                $table->integer('version')->default(0);
                $table->timestamps();

                $table->index('github_username');
            });
        }

        // Machines table
        if (!$schema->hasTable('machines')) {
            $schema->create('machines', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('account_id', 64);
                $table->string('name');
                $table->text('daemon_state_encrypted')->nullable();
                $table->integer('version')->default(0);
                $table->timestamp('last_alive_at')->nullable();
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->index(['account_id', 'last_alive_at']);
            });
        }

        // Sessions table
        if (!$schema->hasTable('sessions')) {
            $schema->create('sessions', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('account_id', 64);
                $table->string('tag')->nullable();
                $table->text('metadata_encrypted')->nullable();
                $table->text('state_encrypted')->nullable();
                $table->text('agent_state_encrypted')->nullable();
                $table->integer('version')->default(0);
                $table->bigInteger('sort_key')->default(0);
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->unique(['account_id', 'tag']);
                $table->index(['account_id', 'created_at']);
                $table->index(['account_id', 'sort_key']);
            });
        }

        // Session messages table
        if (!$schema->hasTable('session_messages')) {
            $schema->create('session_messages', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('session_id', 64);
                $table->string('local_id')->nullable();
                $table->text('content_encrypted');
                $table->integer('seq')->default(0);
                $table->timestamps();

                $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
                $table->index(['session_id', 'seq']);
                $table->unique(['session_id', 'local_id']);
            });
        }

        // Artifacts table
        if (!$schema->hasTable('artifacts')) {
            $schema->create('artifacts', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('session_id', 64);
                $table->string('account_id', 64);
                $table->text('content_encrypted')->nullable();
                $table->text('metadata_encrypted')->nullable();
                $table->integer('version')->default(0);
                $table->timestamps();

                $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->index(['session_id']);
                $table->index(['account_id']);
            });
        }

        // User relationships table
        if (!$schema->hasTable('user_relationships')) {
            $schema->create('user_relationships', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('from_user_id', 64);
                $table->string('to_user_id', 64);
                $table->string('status'); // pending, friend, requested, rejected, blocked
                $table->text('metadata_encrypted')->nullable();
                $table->timestamp('last_notified_at')->nullable();
                $table->timestamps();

                $table->foreign('from_user_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->foreign('to_user_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->unique(['from_user_id', 'to_user_id']);
            });
        }

        // User feed items table
        if (!$schema->hasTable('user_feed_items')) {
            $schema->create('user_feed_items', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('user_id', 64);
                $table->text('content_encrypted');
                $table->string('repeat_key')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->unique(['user_id', 'repeat_key']);
                $table->index(['user_id', 'created_at']);
            });
        }

        // User KV store table
        if (!$schema->hasTable('user_kv_store')) {
            $schema->create('user_kv_store', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('user_id', 64);
                $table->string('key');
                $table->text('value_encrypted')->nullable();
                $table->integer('version')->default(0);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->unique(['user_id', 'key']);
            });
        }

        // Terminal auth requests table
        if (!$schema->hasTable('terminal_auth_requests')) {
            $schema->create('terminal_auth_requests', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('response_account_id', 64)->nullable();
                $table->text('metadata_encrypted')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->foreign('response_account_id')->references('id')->on('accounts')->onDelete('set null');
            });
        }

        // Account auth requests table
        if (!$schema->hasTable('account_auth_requests')) {
            $schema->create('account_auth_requests', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('account_id', 64)->nullable();
                $table->text('metadata_encrypted')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            });
        }

        // GitHub OAuth tokens table
        if (!$schema->hasTable('github_oauth_tokens')) {
            $schema->create('github_oauth_tokens', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('account_id', 64)->unique();
                $table->text('access_token_encrypted');
                $table->text('refresh_token_encrypted')->nullable();
                $table->string('scope')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            });
        }

        // Service account tokens table
        if (!$schema->hasTable('service_account_tokens')) {
            $schema->create('service_account_tokens', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('account_id', 64);
                $table->string('vendor');
                $table->text('token_encrypted');
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->unique(['account_id', 'vendor']);
            });
        }

        // Push tokens table
        if (!$schema->hasTable('push_tokens')) {
            $schema->create('push_tokens', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('account_id', 64);
                $table->string('token');
                $table->string('platform'); // ios, android
                $table->text('metadata_encrypted')->nullable();
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->unique(['account_id', 'token']);
            });
        }

        // Voice settings table
        if (!$schema->hasTable('voice_settings')) {
            $schema->create('voice_settings', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('account_id', 64)->unique();
                $table->string('provider')->default('elevenlabs');
                $table->text('voice_id_encrypted')->nullable();
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            });
        }

        // Usage reports table
        if (!$schema->hasTable('usage_reports')) {
            $schema->create('usage_reports', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('account_id', 64);
                $table->date('date');
                $table->string('metric');
                $table->integer('count')->default(0);
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
                $table->unique(['account_id', 'date', 'metric']);
            });
        }

        // Simple cache table
        if (!$schema->hasTable('simple_cache')) {
            $schema->create('simple_cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->text('value');
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->index('expires_at');
            });
        }

        // Repeat keys table (for deduplication)
        if (!$schema->hasTable('repeat_keys')) {
            $schema->create('repeat_keys', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('domain');
                $table->string('key');
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->unique(['domain', 'key']);
                $table->index('expires_at');
            });
        }
    }

    /**
     * Drop all tables (for testing).
     */
    public static function rollback(): void
    {
        $schema = Database::schema();

        // Drop in reverse order to respect foreign keys
        $tables = [
            'repeat_keys',
            'simple_cache',
            'usage_reports',
            'voice_settings',
            'push_tokens',
            'service_account_tokens',
            'github_oauth_tokens',
            'account_auth_requests',
            'terminal_auth_requests',
            'user_kv_store',
            'user_feed_items',
            'user_relationships',
            'artifacts',
            'session_messages',
            'sessions',
            'machines',
            'accounts',
        ];

        foreach ($tables as $table) {
            $schema->dropIfExists($table);
        }
    }
}
