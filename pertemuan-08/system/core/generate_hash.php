<?php

$hash = password_hash('admin', PASSWORD_DEFAULT);

echo '<pre>';
echo "Hash password admin:\n\n";
echo $hash;
echo '</pre>';