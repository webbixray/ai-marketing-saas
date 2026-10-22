<?php

require __DIR__.'/vendor/autoload.php';

$userRole = Spatie\Permission\Models\Role::where('name', 'user')
    ->where('guard_name', 'api')
    ->first();

echo "User role: ";
if ($userRole) {
    echo "Found - Name: ".$userRole->name.", Guard: ".$userRole->guard_name.PHP_EOL;
} else {
    echo "Not found\n";
}

echo "All roles:\n";
$roles = Spatie\Permission\Models\Role::get();
foreach($roles as $r) {
    echo " - Name: ".$r->name.", Guard: ".$r->guard_name.PHP_EOL;
}
