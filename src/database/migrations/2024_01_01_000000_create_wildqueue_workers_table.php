<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wildqueue_workers', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->unique();
            $table->unsignedInteger('pid')->nullable();
            $table->enum('status', ['running', 'idle', 'stopped'])->default('stopped');
            $table->timestamp('last_job_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wildqueue_workers');
    }
}; 