<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('interface_name');
            $table->string('label')->nullable();
            $table->boolean('is_monitored')->default(false);
            $table->boolean('is_running')->default(false);
            $table->timestamps();
            $table->unique(['router_id', 'interface_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_interfaces');
    }
};
