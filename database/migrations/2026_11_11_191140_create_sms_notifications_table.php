<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('evaluation_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('mobile', 20);
            $table->text('message');
            $table->string('status', 16)->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_reference')->nullable();
            $table->text('error')->nullable();
            $table->string('deduplication_key')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_notifications');
    }
};
