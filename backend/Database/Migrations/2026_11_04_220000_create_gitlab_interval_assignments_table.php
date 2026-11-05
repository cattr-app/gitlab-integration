<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGitlabIntervalAssignmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Holds, for intervals edited after being synced, the task whose GitLab issue currently carries the interval's time.
     */
    public function up(): void
    {
        Schema::create('gitlab_interval_assignments', static function (Blueprint $table) {
            $table->unsignedBigInteger('time_interval_id')->primary();
            $table->unsignedBigInteger('task_id');
            // Set when GitLab state is uncertain: no further moves of this interval until reconciled by hand
            $table->boolean('needs_reconciliation')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gitlab_interval_assignments');
    }
}
