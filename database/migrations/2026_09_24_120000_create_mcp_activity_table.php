<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row for every change and every email an MCP connection makes: who,
     * in which company, through which tool, and to which record.
     */
    public function up(): void
    {
        Schema::create('mcp_activity', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('mcp_connection_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');
            $table->string('tool', 64);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['mcp_connection_id', 'created_at']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_activity');
    }
};
