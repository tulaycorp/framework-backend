<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Users Table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->text('address1')->nullable();
            $table->text('address2')->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('role', 50)->default('customer');
            $table->timestamps();

            $table->index('email', 'idx_email');
            $table->index('role', 'idx_role');
        });

        // Admins Table
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
        });

        // Admin Sessions Table
        Schema::create('admin_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('session_token')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            $table->index('expires_at');
        });

        // Personal Access Tokens Table (Laravel Sanctum style)
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
        });

        // Categories Table
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Products Table (String ID, Shopify-style fields)
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('cost_per_item', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('continue_selling_when_out_of_stock')->default(false);
            $table->boolean('featured')->default(false);
            $table->string('status', 20)->default('active');
            $table->string('category')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->text('tags')->nullable();
            $table->text('image_url')->nullable();
            $table->jsonb('images')->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit', 10)->default('kg');
            $table->boolean('requires_shipping')->default(true);
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->string('dimension_unit', 10)->default('cm');
            $table->boolean('taxable')->default(true);
            $table->string('tax_code')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->jsonb('metafields')->nullable();
            $table->timestamps();

            $table->index('category_id', 'products_category_id_index');
            $table->index('status', 'products_status_index');
            $table->index('sku', 'products_sku_index');
            $table->index('stock_quantity', 'products_stock_quantity_index');
            $table->index('featured', 'products_featured_index');
            $table->index('vendor', 'products_vendor_index');
            $table->index('barcode', 'products_barcode_index');
        });

        // Add CHECK constraints for PostgreSQL (replacing ENUMs)
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('active', 'draft', 'archived'))");
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_weight_unit_check CHECK (weight_unit IN ('kg', 'g', 'lb', 'oz'))");
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_dimension_unit_check CHECK (dimension_unit IN ('cm', 'in', 'm'))");

        // Product Images Table
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->string('url');
            $table->string('alt_text')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index('product_id', 'product_images_product_id_index');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // Orders Table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('order_number')->unique();
            $table->string('status', 20)->default('pending');
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('shipping', 10, 2)->default(0.00);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);
            $table->string('shipping_first_name')->nullable();
            $table->string('shipping_last_name')->nullable();
            $table->string('shipping_email')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->text('shipping_address1')->nullable();
            $table->text('shipping_address2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_zip')->nullable();
            $table->string('shipping_country')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id', 'orders_user_id_index');
            $table->index('status', 'orders_status_index');
            $table->index('created_at', 'orders_created_at_index');
            $table->index(['status', 'created_at'], 'orders_status_created_at_index');
        });

        // Add CHECK constraint for orders status
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pending', 'processing', 'shipped', 'delivered', 'cancelled'))");

        // Order Items Table
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('product_id')->nullable();
            $table->string('product_name');
            $table->decimal('product_price', 10, 2);
            $table->integer('quantity');
            $table->decimal('total', 10, 2);
            $table->timestamps();

            $table->index('product_id', 'order_items_product_id_index');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });

        // Shopping Carts Table
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->unique();
            $table->text('session_id')->nullable();
            $table->timestamps();

            $table->index('user_id', 'carts_user_id_index');
        });

        // Cart Items Table
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->onDelete('cascade');
            $table->string('product_id');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->unique(['cart_id', 'product_id'], 'cart_items_cart_id_product_id_unique');
            $table->index('product_id', 'cart_items_product_id_index');
        });

        // Coupons Table
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('discount_type', 20)->default('percentage');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add CHECK constraint for coupons discount_type
        DB::statement("ALTER TABLE coupons ADD CONSTRAINT coupons_discount_type_check CHECK (discount_type IN ('percentage', 'fixed'))");

        // Coupon Usages Table
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->decimal('discount_amount', 10, 2);
            $table->timestamp('used_at');
            $table->timestamps();

            $table->index('user_id');
            $table->index('used_at');
        });

        // Settings Table (Key-Value Store)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('admin_sessions');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('users');
        Schema::dropIfExists('settings');
    }
};
