<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'transaction_date' => 'date',
        'post_date' => 'date',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    // $table->string('hash')->nullable();
    // $table->dateTime('transaction_date')->nullable();
    // $table->dateTime('post_date')->nullable();
    // $table->string('description')->nullable();
    // $table->string('category')->nullable();
    // $table->string('type')->nullable();
    // $table->decimal('amount', total: 10, places: 2)->default(0);
    // $table->string('memo')->nullable();
}
