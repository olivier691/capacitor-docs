<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('status')->default('pending')->index();

            // Demandeur
            $table->string('title')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->string('email');
            $table->string('phone');

            // Rendez-vous
            $table->string('subject');
            $table->date('date');
            $table->string('time', 5);
            $table->unsignedSmallInteger('duration');
            $table->unsignedTinyInteger('attendees')->default(1);
            $table->text('companions')->nullable();
            $table->text('message')->nullable();

            // Décision de la direction
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->string('outlook_event_id')->nullable();

            // Pointage par la sécurité
            $table->timestamp('arrived_at')->nullable();
            $table->foreignId('arrival_recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->index(['date', 'time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
