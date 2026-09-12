<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('agent')->after('password');
            $table->json('tags')->nullable()->after('role');
            $table->unsignedInteger('max_concurrency')->default(5)->after('tags');
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('form_id')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('api_key_prefix', 16);
            $table->string('api_key_hash', 64)->unique();
            $table->unsignedInteger('rate_limit_per_second')->default(200);
            $table->unsignedInteger('rate_limit_burst')->default(5000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('scope')->default('team');
            $table->string('product_line')->nullable();
            $table->unsignedInteger('priority_weight')->default(100);
            $table->unsignedInteger('max_concurrency_per_agent')->default(3);
            $table->string('assignment_strategy')->default('round_robin');
            $table->unsignedInteger('first_touch_sla_seconds')->default(300);
            $table->unsignedInteger('followup_sla_seconds')->default(86400);
            $table->unsignedInteger('idle_recycle_seconds')->default(3600);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['team_id', 'scope']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('intake_id')->unique();
            $table->foreignId('source_id')->constrained();
            $table->foreignId('queue_id')->nullable()->constrained('queues')->nullOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state')->default('new');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('form_id')->nullable();
            $table->string('product_line')->nullable();
            $table->json('tags')->nullable();
            $table->uuid('suspected_duplicate_of')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->timestamp('last_touched_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sla_first_touch_due_at')->nullable();
            $table->timestamp('sla_followup_due_at')->nullable();
            $table->string('sla_status')->default('ok');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['queue_id', 'state']);
            $table->index(['assigned_agent_id', 'state']);
            $table->index(['email', 'phone']);
            $table->index(['source_id', 'state']);
            $table->index('sla_status');
        });

        Schema::create('lead_transitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('lead_id');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_code');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lead_id', 'created_at']);
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });

        Schema::create('rejected_transitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('lead_id')->nullable();
            $table->string('expected_state')->nullable();
            $table->string('actual_state')->nullable();
            $table->string('attempted_to');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_code')->default('concurrent_conflict');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('lead_id');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('note');
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_note_id')->nullable()->constrained('notes')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lead_id', 'created_at']);
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->unique();
            $table->string('product_sku');
            $table->string('product_name');
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3)->default('USD');
            $table->json('lead_snapshot');
            $table->json('pricing_snapshot');
            $table->string('payment_status')->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('leads')->restrictOnDelete();
            $table->index(['payment_status', 'version']);
        });

        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->string('event_type');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index('event_type');
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->uuid('order_id')->nullable();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->useCurrent();
            $table->unique(['provider', 'event_id']);
            $table->index('order_id');
        });

        Schema::create('sla_breaches', function (Blueprint $table) {
            $table->id();
            $table->uuid('lead_id');
            $table->foreignId('queue_id')->nullable()->constrained('queues')->nullOnDelete();
            $table->string('type');
            $table->foreignId('notified_supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->useCurrent();
            $table->json('meta')->nullable();

            $table->index(['queue_id', 'type']);
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('leads', function (Blueprint $table) {
                $table->fullText(['name', 'email', 'phone']);
            });
            Schema::table('notes', function (Blueprint $table) {
                $table->fullText(['body']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_breaches');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('rejected_transitions');
        Schema::dropIfExists('lead_transitions');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('queues');
        Schema::dropIfExists('sources');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn(['role', 'tags', 'max_concurrency']);
        });
        Schema::dropIfExists('teams');
    }
};
