<?php
/**
 * generatestrukturdirektorifile.php
 * Cara pakai (CLI):
 *   php generatestrukturdirektorifile.php "C:\laragon\www\oralokal\bestpractice" > struktur.txt
 * atau akses via browser:
 *   https://localhost/oralokal/bestpractice/generatestrukturdirektorifile.php
 */
ini_set('display_errors', 0);
$base = isset($argv[1]) ? $argv[1] : __DIR__;

function tree($dir, $prefix = '')
{
  $items = @scandir($dir);
  if ($items === false)
    return;
  $items = array_values(array_diff($items, ['.', '..']));

  $count = count($items);
  foreach ($items as $i => $name) {
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    $isLast = ($i === $count - 1);
    $connector = $isLast ? '└─ ' : '├─ ';
    echo $prefix . $connector . $name . PHP_EOL;

    if (is_dir($path)) {
      $nextPrefix = $prefix . ($isLast ? '   ' : '│  ');
      tree($path, $nextPrefix);
    }
  }
}

header('Content-Type: text/plain; charset=UTF-8');
echo "BASE: " . $base . PHP_EOL;
tree($base);
