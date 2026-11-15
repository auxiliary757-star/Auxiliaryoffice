<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$autoloadFile = $projectRoot . '/vendor/autoload.php';
if (is_file($autoloadFile)) {
    require_once $autoloadFile;
}

$dotenvFile = $projectRoot . '/.env';
if (is_file($dotenvFile)) {
    if (!class_exists(Dotenv\Dotenv::class)) {
        throw new RuntimeException('Run composer install to load the project environment.');
    }
    Dotenv\Dotenv::createUnsafeImmutable($projectRoot)->safeLoad();
}

$timezone = getenv('APP_TIMEZONE') ?: 'Asia/Manila';
date_default_timezone_set($timezone);
