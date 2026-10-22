<?php

require_once __DIR__ . '/vendor/autoload.php';

$user = new App\Models\User();
echo "User class: " . get_class($user) . "\n";

$methods = get_class_methods($user);
echo "Methods containing 'guard':\n";
foreach ($methods as $method) {
    if (strpos($method, 'guard') !== false) {
        echo "  - $method\n";
    }
}

echo "\nChecking if getGuardName exists: ";
if (method_exists($user, 'getGuardName')) {
    echo "YES\n";
    echo "Result: " . $user->getGuardName() . "\n";
} else {
    echo "NO\n";
}
