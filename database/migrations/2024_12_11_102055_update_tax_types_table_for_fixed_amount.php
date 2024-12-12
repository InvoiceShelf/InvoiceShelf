<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tax_types', function (Blueprint $table) {
            $table->enum('calculation_type', ['percentage', 'fixed'])->default('percentage')->after('name');
            $table->integer('fixed_amount')->nullable()->after('percent');
            $table->decimal('percent', 5, 2)->nullable()->change();
        });

        Schema::table('taxes', function (Blueprint $table) {
            $table->enum('calculation_type', ['percentage', 'fixed'])->default('percentage')->after('name');
            $table->integer('fixed_amount')->nullable()->after('percent');
            $table->decimal('percent', 5, 2)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('tax_types', function (Blueprint $table) {
            $table->dropColumn(['calculation_type', 'fixed_amount']);
            $table->decimal('percent', 5, 2)->change();
        });

        Schema::table('taxes', function (Blueprint $table) {
            $table->dropColumn(['calculation_type', 'fixed_amount']);
            $table->decimal('percent', 5, 2)->change();
        });
    }
};
