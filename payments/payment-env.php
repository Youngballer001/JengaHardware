<?php

$values = [];
$env_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

if (is_file($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read the application environment file.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            continue;
        }

        $value = trim(substr($line, $separator + 1));
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && substr($value, -1) === '"') ||
            ($value[0] === "'" && substr($value, -1) === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        $values[$name] = $value;
    }
}

return static function ($name, $default = '') use ($values) {
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $values[$name] ?? $default;
};
