<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->integer('game_id');
            $table->integer('vendor_id');

            $table->date('start_date');
            $table->date('end_date');
            $table->date('registration_deadline')->nullable();

            $table->decimal('registration_fee', 10, 2)->default(0);
            $table->integer('max_teams')->nullable();
            $table->integer('status')->default(1);
            $table->text('description')->nullable();

            $table->integer('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};

