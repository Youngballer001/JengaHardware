<?php
function productImageUrl($storedPath) {
    $placeholder = 'assets/images/placeholder.svg';
    $storedPath = trim((string)$storedPath);

    if ($storedPath === '') {
        return $placeholder;
    }

    $root = realpath(dirname(__DIR__));
    if ($root === false) {
        return $placeholder;
    }

    $rootPath = str_replace('\\', '/', $root);
    $normalizedPath = str_replace('\\', '/', $storedPath);

    if (preg_match('/^[A-Za-z]:\//', $normalizedPath)) {
        $filePath = realpath($normalizedPath);
    } else {
        if (strpos($normalizedPath, 'uploads/products/') === 0) {
            $normalizedPath = 'assets/images/products/' . substr($normalizedPath, strlen('uploads/products/'));
        }

        $normalizedPath = ltrim($normalizedPath, '/');
        if (strpos($normalizedPath, '..') !== false) {
            return $placeholder;
        }

        $filePath = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath));
    }

    if ($filePath === false || !is_file($filePath)) {
        return $placeholder;
    }

    $filePath = str_replace('\\', '/', $filePath);
    $rootPrefix = rtrim($rootPath, '/') . '/';
    if (strncasecmp($filePath, $rootPrefix, strlen($rootPrefix)) !== 0) {
        return $placeholder;
    }

    $relativePath = substr($filePath, strlen($rootPrefix));
    return implode('/', array_map('rawurlencode', explode('/', $relativePath)));
}
