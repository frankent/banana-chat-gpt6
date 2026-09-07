<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ai_providers', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('name', 60);
            $t->string('provider_type')->default('openai_compatible');
            $t->string('base_url', 255);
            $t->text('api_key_encrypted');
            $t->string('api_key_last4', 4);
            $t->string('model', 100);
            $t->string('model_source')->default('custom');
            $t->unsignedInteger('window_size')->default(200000);
            $t->unsignedInteger('max_output_tokens')->default(4096);
            $t->decimal('temperature', 3, 2)->default(.7);
            $t->text('system_prompt')->nullable();
            $t->string('memory_model', 100)->nullable();
            $t->unsignedSmallInteger('timeout_seconds')->default(60);
            $t->json('extra_headers')->nullable();
            $t->json('capabilities')->nullable();
            $t->boolean('is_enabled')->default(true);
            $t->boolean('is_default')->default(false)->index();
            $t->json('allowed_workspace_ids')->nullable();
            $t->unsignedInteger('daily_message_limit_per_user')->nullable();
            $t->decimal('price_per_1k_in', 12, 6)->nullable();
            $t->decimal('price_per_1k_out', 12, 6)->nullable();
            $t->timestampTz('last_tested_at')->nullable();
            $t->json('last_test_status')->nullable();
            $t->ulid('created_by')->nullable();
            $t->ulid('updated_by')->nullable();
            $t->timestampsTz();
        });
        Schema::create('ai_conversations', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $t->string('title', 100)->nullable();
            $t->string('title_source')->nullable();
            $t->text('summary')->nullable();
            $t->unsignedBigInteger('summary_up_to_seq')->default(0);
            $t->unsignedInteger('summary_tokens')->default(0);
            $t->decimal('token_ratio', 6, 3)->nullable();
            $t->unsignedBigInteger('last_seq')->default(0);
            $t->unsignedInteger('message_count')->default(0);
            $t->unsignedBigInteger('total_tokens_in')->default(0);
            $t->unsignedBigInteger('total_tokens_out')->default(0);
            $t->timestampTz('last_message_at')->nullable();
            $t->timestampTz('archived_at')->nullable();
            $t->softDeletesTz();
            $t->timestampTz('purge_after')->nullable();
            $t->timestampsTz();
            $t->index(['user_id', 'deleted_at', 'last_message_at']);
        });
        Schema::create('ai_messages', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $t->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $t->ulid('workspace_id')->nullable();
            $t->unsignedBigInteger('seq');
            $t->string('role');
            $t->longText('content')->nullable();
            $t->string('status')->default('completed');
            $t->string('error_code', 40)->nullable();
            $t->string('error_detail', 500)->nullable();
            $t->uuid('client_message_id')->nullable();
            $t->ulid('parent_message_id')->nullable();
            $t->timestampTz('superseded_at')->nullable();
            $t->string('model', 100)->nullable();
            $t->string('finish_reason', 20)->nullable();
            $t->unsignedInteger('tokens_prompt')->nullable();
            $t->unsignedInteger('tokens_completion')->nullable();
            $t->string('tokens_source')->nullable();
            $t->unsignedInteger('latency_first_token_ms')->nullable();
            $t->unsignedInteger('latency_total_ms')->nullable();
            $t->json('attachments')->nullable();
            $t->timestampTz('started_at')->nullable();
            $t->timestampTz('completed_at')->nullable();
            $t->timestampsTz();
            $t->unique(['conversation_id', 'seq']);
            $t->unique(['conversation_id', 'client_message_id']);
            $t->index(['user_id', 'workspace_id', 'created_at']);
        });
        Schema::create('ai_user_memories', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $t->string('content', 300);
            $t->string('category')->default('other');
            $t->unsignedTinyInteger('importance')->default(3);
            $t->string('source')->default('user');
            $t->ulid('source_conversation_id')->nullable();
            $t->ulid('source_message_id')->nullable();
            $t->timestampTz('last_used_at')->nullable();
            $t->timestampsTz();
            $t->index(['user_id', 'importance', 'last_used_at']);
        });
        Schema::create('ai_usage_daily', function (Blueprint $t) {
            $t->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $t->ulid('workspace_id')->nullable();
            $t->date('date');
            $t->unsignedInteger('messages')->default(0);
            $t->unsignedBigInteger('tokens_in')->default(0);
            $t->unsignedBigInteger('tokens_out')->default(0);
            $t->unsignedBigInteger('tokens_memory')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->unique(['user_id', 'workspace_id', 'date']);
        });
    }
    public function down(): void {
        foreach (['ai_usage_daily','ai_user_memories','ai_messages','ai_conversations','ai_providers'] as $table) Schema::dropIfExists($table);
    }
};
