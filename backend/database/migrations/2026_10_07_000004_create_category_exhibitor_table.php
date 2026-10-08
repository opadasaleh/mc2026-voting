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
        Schema::create('category_exhibitor', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('exhibitor_id');

            $table->primary(['category_id', 'exhibitor_id']);
            // Target of the votes composite foreign key.
            $table->unique(['event_id', 'category_id', 'exhibitor_id']);
            $table->index(['event_id', 'exhibitor_id']);

            // An exhibitor can only be entered in a category of its own event.
            $table->foreign(['event_id', 'category_id'])->references(['event_id', 'id'])->on('categories')->cascadeOnDelete();
            $table->foreign(['event_id', 'exhibitor_id'])->references(['event_id', 'id'])->on('exhibitors')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_exhibitor');
    }
};
