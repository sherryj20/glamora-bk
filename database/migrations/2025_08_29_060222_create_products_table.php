<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // id BIGINT autoincremental (Laravel por defecto)
            $table->id();

            $table->string('name', 150);
            $table->decimal('price', 10, 2);

            $table->integer('category')->index();

            $table->text('img')->nullable();
            $table->string('description', 255)->nullable();

            $table->boolean('in_stock')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
