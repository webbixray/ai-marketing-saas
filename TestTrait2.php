<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Guard;

class TestTrait2 extends Model
{
    use HasPermissions;
    
    protected $table = 'test_traits2'; // dummy table
    
    public function getDefaultGuardName(): string
    {
        return Guard::getDefaultName($this);
    }
}

