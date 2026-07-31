<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pppoe')->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('package')->default('20 Mbps');
            $table->string('status')->default('active');
            $table->string('area')->nullable();
            $table->string('address')->nullable();
            $table->string('odp')->nullable();
            $table->string('olt')->nullable();
            $table->string('onu')->nullable();
            $table->string('pon_port')->nullable();
            $table->decimal('rx_power', 5, 2)->nullable();
            $table->decimal('tx_power', 5, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'area']);
            $table->index('odp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
