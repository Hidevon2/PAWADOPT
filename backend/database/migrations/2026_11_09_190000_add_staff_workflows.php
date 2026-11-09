<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('role', 20)->default('adopter')->index());
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('contact_messages', fn (Blueprint $table) => $table->timestamp('resolved_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('contact_messages', fn (Blueprint $table) => $table->dropColumn('resolved_at'));
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_note', 'reviewed_at']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
