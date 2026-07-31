<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('area')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('capacity')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('odcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('status')->default('active');
            $table->unsignedInteger('capacity')->default(0);
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('odps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odc_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('status')->default('active');
            $table->unsignedSmallInteger('capacity')->default(16);
            $table->unsignedSmallInteger('used_ports')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ip')->nullable();
            $table->string('status')->default('online');
            $table->unsignedSmallInteger('capacity')->default(128);
            $table->unsignedSmallInteger('pon_ports')->default(8);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('onus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odp_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('olt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial')->nullable();
            $table->string('name');
            $table->string('status')->default('online');
            $table->decimal('rx_power', 5, 2)->nullable();
            $table->decimal('tx_power', 5, 2)->nullable();
            $table->string('pon_port')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('internet_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('speed_mbps');
            $table->unsignedInteger('price')->default(0);
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internet_packages');
        Schema::dropIfExists('onus');
        Schema::dropIfExists('olts');
        Schema::dropIfExists('odps');
        Schema::dropIfExists('odcs');
        Schema::dropIfExists('pops');
    }
};
