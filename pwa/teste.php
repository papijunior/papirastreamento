<?php
header('Content-Type: text/plain; charset=utf-8');

echo "Dados POST:\n";
print_r($_POST);

$input = file_get_contents('php://input');
echo "\nRaw input:\n";
echo $input;

if (isset($_POST['latitude']) && isset($_POST['longitude'])) {
    echo "\nLatitude e Longitude via POST: {$_POST['latitude']}, {$_POST['longitude']}\n";
} else {
    // tenta parse do raw input
    parse_str($input, $parsed);
    if (isset($parsed['latitude']) && isset($parsed['longitude'])) {
        echo "\nLatitude e Longitude via raw input: {$parsed['latitude']}, {$parsed['longitude']}\n";
    } else {
        echo "\nDados incompletos.";
    }
}

