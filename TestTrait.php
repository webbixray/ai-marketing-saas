<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasPermissions;

class TestTrait extends Model
{
    use HasPermissions;
    
    protected $table = 'test_traits'; // dummy table
}

