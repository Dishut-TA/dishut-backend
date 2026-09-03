<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('donation_id')->constrained('donations')->onDelete('cascade');
            // $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donor_id')->nullable()->constrained('donors')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->timestamp('transaction_date')->useCurrent();
            $table->string('proof_path', 255)->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->enum('status', ['Pending', 'Success', 'Rejected'])->default('Pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
