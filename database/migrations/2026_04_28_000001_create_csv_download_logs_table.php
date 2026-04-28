<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csv_download_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_uuid')->nullable()->index();
            $table->string('ip_address', 45);
            $table->integer('downloaded_user_count')->default(0);
            $table->json('live_chat_user_uuids')->nullable();
            $table->smallInteger('http_status_code')->default(200);
            $table->string('status', 20)->default('success'); // success | failed | unauthorized
            $table->string('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_download_logs');
    }
};
