<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every tool call made to the MCP server, including reads and refused calls (see McpAuditLog)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mcp_audit_logs', function (Blueprint $table) {
            $table->id();
            // Kept when the token is revoked, with its name, so the log still says which token it was
            $table->foreignId('api_token_id')->nullable()->constrained('api_tokens')->nullOnDelete();
            $table->string('api_token_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tool', 100);
            $table->string('ability', 50);
            $table->string('outcome', 20);
            $table->string('target_type', 50)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            // The arguments sent, with long values shortened
            $table->json('arguments')->nullable();
            // What changed: the before and after values of each changed attribute
            $table->json('changes')->nullable();
            $table->text('message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['api_token_id', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
    }
};
