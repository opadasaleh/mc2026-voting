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
        // Immutable: one row per cast vote, never updated.
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('visitor_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('exhibitor_id');
            $table->ipAddress('ip')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            // One vote per verified phone per category (F2, F12).
            $table->unique(['visitor_id', 'category_id']);
            // Tally queries: COUNT(*) GROUP BY category, exhibitor.
            $table->index(['event_id', 'category_id', 'exhibitor_id']);

            $table->foreign('event_id')->references('id')->on('events')->restrictOnDelete();
            $table->foreign('visitor_id')->references('id')->on('visitors')->restrictOnDelete();
            // The exhibitor must be entered in that category of that event.
            $table->foreign(['event_id', 'category_id', 'exhibitor_id'])
                ->references(['event_id', 'category_id', 'exhibitor_id'])->on('category_exhibitor')
                ->restrictOnDelete();
            // The voter must be registered for that event.
            $table->foreign(['event_id', 'visitor_id'])
                ->references(['event_id', 'visitor_id'])->on('event_registrations')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
