<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class DummyTest extends TestCase
{
    public function test_database_connection()
    {
        $this->assertTrue(true);
        // Try to connect to the database and run a simple query
        $result = DB::connection()->selectOne('select 1 as test');
        $this->assertEquals(1, $result->test);
    }
}
