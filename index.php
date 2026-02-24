<?php
/**
 * ============================================================================
 * StudyFlow - Front Controller
 * Student Self-Teaching App
 *
 * This is the single entry point for all HTTP requests. It bootstraps the
 * application by loading configuration, setting up autoloading, initializing
 * the session, and dispatching the request through the router.
 *
 * All clean URLs are routed here via Apache mod_rewrite (.htaccess).
 * ============================================================================
 */

// -------------------------------------------------------------------------
// Error Reporting (controlled by environment)
// -------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// -------------------------------------------------------------------------
// Define Base Path Constants
// -------------------------------------------------------------------------
define('BASE_PATH', __DIR__);
define('CONFIG_PATH', BASE_PATH . '/config');
define('CORE_PATH', BASE_PATH . '/core');
define('CONTROLLER_PATH', BASE_PATH . '/controllers');
define('VIEW_PATH', BASE_PATH . '/views');
define('SERVICE_PATH', BASE_PATH . '/services');
define('UTIL_PATH', BASE_PATH . '/utils');
define('DATA_PATH', BASE_PATH . '/data');
define('ASSET_PATH', BASE_PATH . '/assets');

// -------------------------------------------------------------------------
// Load Configuration Files
// -------------------------------------------------------------------------
$config = require CONFIG_PATH . '/app.php';
require CONFIG_PATH . '/constants.php';

// -------------------------------------------------------------------------
// Environment Setup
// -------------------------------------------------------------------------
$isDebug = $config['debug'] ?? false;
if ($isDebug) {
    ini_set('display_errors', '1');
}

// Set default timezone
date_default_timezone_set($config['timezone'] ?? 'UTC');

// -------------------------------------------------------------------------
// Custom Error Handler
// -------------------------------------------------------------------------
set_error_handler(function ($severity, $message, $file, $line) use ($isDebug) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $errorType = match ($severity) {
        E_WARNING         => 'Warning',
        E_NOTICE          => 'Notice',
        E_STRICT          => 'Strict',
        E_DEPRECATED      => 'Deprecated',
        E_USER_ERROR      => 'User Error',
        E_USER_WARNING    => 'User Warning',
        E_USER_NOTICE     => 'User Notice',
        E_USER_DEPRECATED => 'User Deprecated',
        default           => 'Unknown Error',
    };

    $logMessage = sprintf("[%s] %s: %s in %s on line %d", date('Y-m-d H:i:s'), $errorType, $message, $file, $line);
    error_log($logMessage);

    if ($severity === E_USER_ERROR) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    return true;
});

// -------------------------------------------------------------------------
// Custom Exception Handler
// -------------------------------------------------------------------------
set_exception_handler(function (Throwable $e) use ($isDebug) {
    $logMessage = sprintf(
        "[%s] Uncaught %s: %s in %s on line %d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($logMessage);

    $isAjax = (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        isset($_SERVER['HTTP_ACCEPT']) &&
        strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
    );

    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $isDebug ? $e->getMessage() : 'An internal error occurred.',
        ]);
    } else {
        http_response_code(500);
        if ($isDebug) {
            echo '<h1>Application Error</h1>';
            echo '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>';
            echo '<p>File: ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        } else {
            if (file_exists(VIEW_PATH . '/errors/500.php')) {
                include VIEW_PATH . '/errors/500.php';
            } else {
                echo '<h1>500 - Internal Server Error</h1>';
                echo '<p>Something went wrong. Please try again later.</p>';
            }
        }
    }
    exit(1);
});

// -------------------------------------------------------------------------
// Shutdown Handler (catch fatal errors)
// -------------------------------------------------------------------------
register_shutdown_function(function () use ($isDebug) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        $logMessage = sprintf(
            "[%s] Fatal Error: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $error['message'],
            $error['file'],
            $error['line']
        );
        error_log($logMessage);

        if (!headers_sent()) {
            http_response_code(500);
        }

        if ($isDebug) {
            echo '<h1>Fatal Error</h1>';
            echo '<p>' . htmlspecialchars($error['message']) . '</p>';
        }
    }
});

// -------------------------------------------------------------------------
// Autoloader: Load core classes, controllers, services, and utilities
// -------------------------------------------------------------------------
spl_autoload_register(function ($className) {
    // Map of directory prefixes for class loading
    $directories = [
        CORE_PATH,
        CONTROLLER_PATH,
        SERVICE_PATH,
        UTIL_PATH,
    ];

    // Convert namespace separators and try each directory
    $fileName = str_replace(['\\', '_'], '/', $className) . '.php';

    foreach ($directories as $directory) {
        $filePath = $directory . '/' . $fileName;
        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        }
    }

    // Try base name only (no namespace path)
    $baseName = basename($fileName);
    foreach ($directories as $directory) {
        $filePath = $directory . '/' . $baseName;
        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        }
    }

    return false;
});

// -------------------------------------------------------------------------
// Ensure Required Data Directories Exist
// -------------------------------------------------------------------------
$dataDirs = [
    DATA_PATH,
    DATA_PATH . '/users',
    DATA_PATH . '/subjects',
    DATA_PATH . '/study_sessions',
    DATA_PATH . '/writing',
    DATA_PATH . '/flashcards',
    DATA_PATH . '/quizzes',
    DATA_PATH . '/notes',
    DATA_PATH . '/progress',
    DATA_PATH . '/planner',
    DATA_PATH . '/uploads',
];

foreach ($dataDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// -------------------------------------------------------------------------
// Initialize Core Components
// -------------------------------------------------------------------------

// Start the session manager
$session = new Session();
$session->start($config['session'] ?? []);

// Create the Request object
$request = new Request();

// Create the Response object
$response = new Response();

// Make config globally accessible
$GLOBALS['config'] = $config;
$GLOBALS['session'] = $session;
$GLOBALS['request'] = $request;
$GLOBALS['response'] = $response;

// -------------------------------------------------------------------------
// Handle Error Page Requests (from .htaccess ErrorDocument)
// -------------------------------------------------------------------------
if (isset($_GET['error'])) {
    $errorCode = (int) $_GET['error'];
    $validErrors = [403, 404, 500];

    if (in_array($errorCode, $validErrors, true)) {
        http_response_code($errorCode);
        $errorView = VIEW_PATH . '/errors/' . $errorCode . '.php';
        if (file_exists($errorView)) {
            include $errorView;
        } else {
            echo "<h1>{$errorCode} Error</h1>";
        }
        exit;
    }
}

// -------------------------------------------------------------------------
// Load Routes and Initialize Router
// -------------------------------------------------------------------------
$router = new Router($request, $response, $session);

// Load route definitions
$routeDefinitions = require CONFIG_PATH . '/routes.php';

// Register all routes
foreach ($routeDefinitions as $route) {
    $method     = $route['method'] ?? 'GET';
    $pattern    = $route['pattern'] ?? '';
    $handler    = $route['handler'] ?? '';
    $middleware = $route['middleware'] ?? [];
    $name       = $route['name'] ?? '';

    if ($pattern && $handler) {
        $router->addRoute($method, $pattern, $handler, $middleware, $name);
    }
}

// -------------------------------------------------------------------------
// Dispatch the Request
// -------------------------------------------------------------------------
try {
    $router->dispatch();
} catch (Throwable $e) {
    // Re-throw to let the exception handler deal with it
    throw $e;
}
