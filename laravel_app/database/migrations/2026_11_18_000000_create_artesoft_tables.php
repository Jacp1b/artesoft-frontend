<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_state')) {
            Schema::create('app_state', function (Blueprint $table): void {
                $table->string('state_key', 80)->primary();
                $table->json('payload');
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 90);
                $table->string('email', 120)->unique();
                $table->string('password_hash');
                $table->string('role', 20)->default('cliente')->index();
                $table->string('phone', 20)->default('');
                $table->string('city', 60)->default('');
                $table->string('address', 120)->default('');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->string('product_id', 50)->primary();
                $table->string('name', 100)->unique();
                $table->string('category', 60)->index();
                $table->unsignedInteger('price');
                $table->unsignedInteger('stock')->default(0)->index();
                $table->string('description', 180)->default('');
                $table->string('badge', 28)->default('');
                $table->string('image', 180)->default('');
                $table->string('color', 30)->default('lilac');
                $table->string('icon', 16)->default('🎨');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table): void {
                $table->string('order_id', 20)->primary();
                $table->unsignedBigInteger('customer_user_id')->nullable()->index();
                $table->char('guest_token_hash', 64)->nullable()->index();
                $table->string('customer_name', 90);
                $table->string('customer_email', 120)->index();
                $table->string('customer_phone', 20)->default('');
                $table->string('customer_city', 60)->default('');
                $table->string('customer_address', 120)->default('');
                $table->string('customer_notes', 500)->default('');
                $table->unsignedBigInteger('sold_by_user_id')->nullable()->index();
                $table->unsignedInteger('subtotal');
                $table->unsignedInteger('shipping');
                $table->unsignedInteger('total');
                $table->string('status', 30)->default('Pedido recibido')->index();
                $table->string('payment', 60)->default('Pago contra entrega');
                $table->boolean('is_demo')->default(false);
                $table->boolean('is_test')->default(false);
                $table->dateTime('created_at')->index();
                $table->foreign('customer_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('sold_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        } else {
            if (!Schema::hasColumn('orders', 'sold_by_user_id')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->unsignedBigInteger('sold_by_user_id')->nullable()->index();
                    $table->foreign('sold_by_user_id')->references('id')->on('users')->nullOnDelete();
                });
            }
            if (!Schema::hasColumn('orders', 'is_test')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->boolean('is_test')->default(false);
                });
            }
        }

        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table): void {
                $table->id();
                $table->string('order_id', 20)->index();
                $table->string('product_id', 50)->nullable()->index();
                $table->string('product_name', 100);
                $table->unsignedInteger('unit_price');
                $table->unsignedInteger('quantity');
                $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
                $table->foreign('product_id')->references('product_id')->on('products')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('users');
        Schema::dropIfExists('app_state');
    }
};
