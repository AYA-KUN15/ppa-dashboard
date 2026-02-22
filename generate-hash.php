<?php
$plain_password = 'testovcrdes';  // ← change this to whatever you want to use
$hash = password_hash($plain_password, PASSWORD_DEFAULT);
echo "<pre>";
echo "Plain password: $plain_password\n";
echo "Hash: $hash\n";
echo "</pre>";