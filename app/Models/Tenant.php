<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'domain'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // You can add other relations as needed (e.g., campaigns, etc.)
}
