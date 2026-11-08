<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kujanggate_signing_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key_id', 32)->unique();
            $table->string('product_slug', 64)->index();
            $table->string('public_key_hex', 64);
            $table->text('encrypted_secret_key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kujanggate_licenses', function (Blueprint $table): void {
            $table->id();
            $table->string('lic_id', 40)->unique();
            $table->string('key_id', 32)->index();
            $table->string('product_slug', 64)->index();
            $table->string('customer_email', 160)->index();
            $table->string('tier', 32)->default('pro');
            $table->unsignedSmallInteger('max_seats')->default(1);
            $table->json('features');
            $table->json('hw_locks')->nullable();
            $table->text('signed_token');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kujanggate_seat_activations', function (Blueprint $table): void {
            $table->id();
            $table->string('lic_id', 40)->index();
            $table->string('hardware_fingerprint', 48)->index();
            $table->string('device_label', 120);
            $table->string('domain', 190)->nullable();
            $table->timestamp('activated_at');
            $table->timestamp('last_seen_at');
            $table->unique(['lic_id', 'hardware_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kujanggate_seat_activations');
        Schema::dropIfExists('kujanggate_licenses');
        Schema::dropIfExists('kujanggate_signing_keys');
    }
};
