<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('locale', 10)->default('en')->after('slug');
            $table->uuid('translation_group')->nullable()->after('locale');
            $table->index('translation_group');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['locale', 'slug']);
        });
    }
};
