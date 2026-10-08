<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each exhibitor now competes in exactly one category: the category_exhibitor
     * pivot is replaced by exhibitors.category_id. Existing exhibitors keep their
     * first category (lowest sort order).
     */
    public function up(): void
    {
        $split = DB::selectOne('SELECT count(*) AS n FROM (SELECT exhibitor_id FROM votes GROUP BY exhibitor_id HAVING count(DISTINCT category_id) > 1) s');

        if ($split->n > 0) {
            throw new RuntimeException('Some exhibitors have votes in more than one category; resolve them before running this migration.');
        }

        Schema::table('exhibitors', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('event_id');
        });

        // Prefer the category an exhibitor already has votes in, then the first by sort order.
        DB::statement(<<<'SQL'
            UPDATE exhibitors e SET category_id = (
                SELECT ce.category_id
                FROM category_exhibitor ce
                JOIN categories c ON c.id = ce.category_id
                WHERE ce.exhibitor_id = e.id
                ORDER BY EXISTS (SELECT 1 FROM votes v WHERE v.exhibitor_id = e.id AND v.category_id = ce.category_id) DESC,
                         c.sort_order, c.id
                LIMIT 1
            )
        SQL);

        // Exhibitors that were in no category go to their event's first category.
        DB::statement(<<<'SQL'
            UPDATE exhibitors e SET category_id = (
                SELECT c.id FROM categories c WHERE c.event_id = e.event_id ORDER BY c.sort_order, c.id LIMIT 1
            )
            WHERE e.category_id IS NULL
        SQL);

        if (DB::table('exhibitors')->whereNull('category_id')->exists()) {
            throw new RuntimeException('Some exhibitors belong to an event without categories; add a category first.');
        }

        Schema::table('votes', function (Blueprint $table) {
            $table->dropForeign(['event_id', 'category_id', 'exhibitor_id']);
        });

        Schema::table('exhibitors', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->index('category_id');
            // Target of the votes composite foreign key.
            $table->unique(['event_id', 'category_id', 'id']);
            // The category must belong to the exhibitor's own event.
            $table->foreign(['event_id', 'category_id'])->references(['event_id', 'id'])->on('categories')->restrictOnDelete();
        });

        Schema::table('votes', function (Blueprint $table) {
            // The vote's category must be the exhibitor's category. ON UPDATE RESTRICT also
            // stops an exhibitor that has votes from being moved to another category.
            $table->foreign(['event_id', 'category_id', 'exhibitor_id'])
                ->references(['event_id', 'category_id', 'id'])->on('exhibitors')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });

        Schema::drop('category_exhibitor');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('category_exhibitor', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('exhibitor_id');

            $table->primary(['category_id', 'exhibitor_id']);
            $table->unique(['event_id', 'category_id', 'exhibitor_id']);
            $table->index(['event_id', 'exhibitor_id']);
            $table->foreign(['event_id', 'category_id'])->references(['event_id', 'id'])->on('categories')->cascadeOnDelete();
            $table->foreign(['event_id', 'exhibitor_id'])->references(['event_id', 'id'])->on('exhibitors')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE category_exhibitor ENABLE ROW LEVEL SECURITY');
        DB::statement('INSERT INTO category_exhibitor (event_id, category_id, exhibitor_id) SELECT event_id, category_id, id FROM exhibitors');

        Schema::table('votes', function (Blueprint $table) {
            $table->dropForeign(['event_id', 'category_id', 'exhibitor_id']);
            $table->foreign(['event_id', 'category_id', 'exhibitor_id'])
                ->references(['event_id', 'category_id', 'exhibitor_id'])->on('category_exhibitor')
                ->restrictOnDelete();
        });

        Schema::table('exhibitors', function (Blueprint $table) {
            $table->dropForeign(['event_id', 'category_id']);
            $table->dropUnique(['event_id', 'category_id', 'id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
