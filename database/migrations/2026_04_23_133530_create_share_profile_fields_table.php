<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_profile_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('share_profile_id')->index();
            $table->unsignedBigInteger('language_id')->index();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_uneditable')->nullable()->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_default')->default(false);
            $table->string('label')->nullable();
            $table->string('entry_form_key')->nullable();
            $table->string('answer_method')->nullable();
            $table->integer('priority')->nullable();
            $table->json('setting')->nullable();
            $table->json('selector_items')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_profile_contents');
    }
};
