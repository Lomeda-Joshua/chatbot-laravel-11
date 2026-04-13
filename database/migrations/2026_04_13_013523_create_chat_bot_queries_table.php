<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_bot_queries', function (Blueprint $table) {
            $table->id();
            $table->integer('group_id')->comment('Group id for the agency or company');
            $table->integer('sequence')->comment('Primary sequence for questionaire order');
            $table->string('query_name');
            $table->string('choices')->comment('double semi-colon separated for explode');
            $table->integer('is_form')->comment('0 = action, 1 = form');
            $table->string('image_url');
            $table->string('form_description');
            $table->longText('form_details')->comment('blob format for long ');
            $table->string('is_active');
            $table->string('is_submit');
            $table->string('navigation');
            $table->string('is_ticket');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_bot_queries');
    }
};
