<?php
/**
 * ============================================================================
 * StudyFlow - Router
 * Student Self-Teaching App
 *
 * Handles URL pattern matching with named parameters, supports GET/POST
 * methods, middleware execution, and dispatches to controller methods.
 * Includes 404 handling and route name-based URL generation.
 * ============================================================================
 */

class Router
{
    /**
     * Registered routes organized by HTTP method.
     * @var array
     */
    private array $routes = [
        'GET'  => [],
        'POST' => [],
    ];

    /**
     * Named routes for URL generation.
     * @var array
     */
    private array $namedRoutes = [];

    /**
     * The current Request object.
     * @var Request
     */
    private Request $request;

    /**
     * The current Response object.
     * @var Response
     */
    private Response $response;

    /**
     * The current Session object.
     * @var Session
     */
    private Session $session;

    /**
     * Route parameters extracted from the URL.
     * @var array
     */
    private array $params = [];

    /**
     * The base path of the application (for subdirectory installations).
     * @var string
     */
    private string $basePath = '';

    /**
     * Constructor.
     *
     * @param Request  $request  The request object
     * @param Response $response The response object
     * @param Session  $session  The session object
     */
    public function __construct(Request $request, Response $response, Session $session)
    {
        $this->request  = $request;
        $this->response = $response;
        $this->session  = $session;

        // Determine base path from script location
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $this->basePath = ($scriptDir !== '/' && $scriptDir !== '\\') ? $scriptDir : '';
    }

    /**
     * Register a route.
     *
     * @param string       $method     HTTP method (GET, POST)
     * @param string       $pattern    URL pattern with optional {param} placeholders
     * @param string       $handler    Controller@method string
     * @param array        $middleware Array of middleware names to apply
     * @param string       $name       Optional route name for URL generation
     * @return self
     */
    public function addRoute(string $method, string $pattern, string $handler, array $middleware = [], string $name = ''): self
    {
        $method = strtoupper($method);

        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        $route = [
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $middleware,
            'name'       => $name,
            'regex'      => $this->patternToRegex($pattern),
        ];

        $this->routes[$method][] = $route;

        if ($name) {
            $this->namedRoutes[$name] = $route;
        }

        return $this;
    }

    /**
     * Register a GET route.
     *
     * @param string $pattern    URL pattern
     * @param string $handler    Controller@method string
     * @param array  $middleware Middleware array
     * @param string $name       Route name
     * @return self
     */
    public function get(string $pattern, string $handler, array $middleware = [], string $name = ''): self
    {
        return $this->addRoute('GET', $pattern, $handler, $middleware, $name);
    }

    /**
     * Register a POST route.
     *
     * @param string $pattern    URL pattern
     * @param string $handler    Controller@method string
     * @param array  $middleware Middleware array
     * @param string $name       Route name
     * @return self
     */
    public function post(string $pattern, string $handler, array $middleware = [], string $name = ''): self
    {
        return $this->addRoute('POST', $pattern, $handler, $middleware, $name);
    }

    /**
     * Convert a URL pattern to a regex pattern.
     *
     * Converts patterns like /subjects/{id}/topics/{topic_id}
     * to regex patterns that capture named groups.
     *
     * @param string $pattern URL pattern with {param} placeholders
     * @return string Regex pattern
     */
    private function patternToRegex(string $pattern): string
    {
        // Escape forward slashes
        $regex = str_replace('/', '\/', $pattern);

        // Replace {param} with named capture groups
        // Matches alphanumeric characters, hyphens, and underscores
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[a-zA-Z0-9_-]+)', $regex);

        return '/^' . $regex . '$/';
    }

    /**
     * Get the current request path relative to the base path.
     *
     * @return string Clean request path
     */
    private function getRequestPath(): string
    {
        $path = $this->request->path();

        // Remove base path prefix if set
        if ($this->basePath && strpos($path, $this->basePath) === 0) {
            $path = substr($path, strlen($this->basePath));
        }

        // Ensure path starts with /
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }

        // Remove trailing slash (except for root)
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /**
     * Match the current request against registered routes.
     *
     * @return array|null Matched route or null
     */
    private function matchRoute(): ?array
    {
        $method = $this->request->method();
        $path   = $this->getRequestPath();

        // Check if we have routes for this HTTP method
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                // Extract named parameters (filter out numeric keys)
                $this->params = array_filter($matches, function ($key) {
                    return !is_int($key);
                }, ARRAY_FILTER_USE_KEY);

                return $route;
            }
        }

        return null;
    }

    /**
     * Dispatch the current request to the matched route handler.
     *
     * Executes middleware checks, instantiates the controller,
     * and calls the appropriate method with extracted parameters.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $route = $this->matchRoute();

        if ($route === null) {
            $this->handleNotFound();
            return;
        }

        // Execute middleware
        if (!$this->executeMiddleware($route['middleware'])) {
            return; // Middleware handled the response (redirect, etc.)
        }

        // Parse handler string (Controller@method)
        $handler = $this->parseHandler($route['handler']);

        if ($handler === null) {
            $this->handleServerError('Invalid route handler: ' . $route['handler']);
            return;
        }

        [$controllerClass, $method] = $handler;

        // Load and instantiate the controller
        $controller = $this->loadController($controllerClass);

        if ($controller === null) {
            $this->handleServerError('Controller not found: ' . $controllerClass);
            return;
        }

        // Verify the method exists
        if (!method_exists($controller, $method)) {
            $this->handleServerError("Method '{$method}' not found in controller '{$controllerClass}'");
            return;
        }

        // Make route params available to the request
        $this->request->setRouteParams($this->params);

        // Call the controller method with parameters
        try {
            call_user_func_array([$controller, $method], array_values($this->params));
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Execute middleware stack for a route.
     *
     * @param array $middlewareList List of middleware names
     * @return bool True if all middleware passed, false if request was handled
     */
    private function executeMiddleware(array $middlewareList): bool
    {
        foreach ($middlewareList as $middlewareName) {
            $result = $this->runMiddleware($middlewareName);

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Run a single middleware by name.
     *
     * @param string $name Middleware name
     * @return bool True if middleware passed
     */
    private function runMiddleware(string $name): bool
    {
        switch ($name) {
            case 'auth':
                return Middleware::auth($this->session, $this->response);

            case 'guest':
                return Middleware::guest($this->session, $this->response);

            case 'csrf':
                return Middleware::csrf($this->request, $this->session, $this->response);

            default:
                // Check for a custom middleware class
                $className = ucfirst($name) . 'Middleware';
                if (class_exists($className) && method_exists($className, 'handle')) {
                    return $className::handle($this->request, $this->response, $this->session);
                }

                // Unknown middleware - log warning but allow through
                error_log("Warning: Unknown middleware '{$name}' - skipping");
                return true;
        }
    }

    /**
     * Parse a Controller@method handler string.
     *
     * @param string $handler Handler string (e.g., "SubjectController@view")
     * @return array|null [controllerClass, method] or null if invalid
     */
    private function parseHandler(string $handler): ?array
    {
        if (strpos($handler, '@') === false) {
            return null;
        }

        $parts = explode('@', $handler, 2);

        if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
            return null;
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * Load and instantiate a controller class.
     *
     * @param string $className Controller class name
     * @return object|null Controller instance or null if not found
     */
    private function loadController(string $className): ?object
    {
        // Try to load the controller file if class doesn't exist yet
        if (!class_exists($className)) {
            $filePath = CONTROLLER_PATH . '/' . $className . '.php';

            if (!file_exists($filePath)) {
                return null;
            }

            require_once $filePath;

            if (!class_exists($className)) {
                return null;
            }
        }

        // Instantiate with core dependencies
        return new $className($this->request, $this->response, $this->session);
    }

    /**
     * Handle a 404 Not Found response.
     *
     * @return void
     */
    private function handleNotFound(): void
    {
        http_response_code(404);

        if ($this->request->isAjax()) {
            $this->response->json([
                'success' => false,
                'error'   => 'The requested resource was not found.',
            ], 404);
            return;
        }

        $errorView = VIEW_PATH . '/errors/404.php';
        if (file_exists($errorView)) {
            $view = new View();
            $view->render('errors/404', [
                'title'   => '404 - Page Not Found',
                'message' => 'The page you are looking for does not exist.',
            ]);
        } else {
            echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head>';
            echo '<body style="font-family:sans-serif;text-align:center;padding:50px;">';
            echo '<h1>404 - Page Not Found</h1>';
            echo '<p>The page you are looking for does not exist.</p>';
            echo '<a href="/">Go to Dashboard</a>';
            echo '</body></html>';
        }
    }

    /**
     * Handle a 500 Server Error response.
     *
     * @param string $message Error message
     * @return void
     */
    private function handleServerError(string $message): void
    {
        error_log('[Router] ' . $message);
        http_response_code(500);

        $config = $GLOBALS['config'] ?? [];
        $isDebug = $config['debug'] ?? false;

        if ($this->request->isAjax()) {
            $this->response->json([
                'success' => false,
                'error'   => $isDebug ? $message : 'An internal error occurred.',
            ], 500);
            return;
        }

        $errorView = VIEW_PATH . '/errors/500.php';
        if (file_exists($errorView)) {
            $view = new View();
            $view->render('errors/500', [
                'title'   => '500 - Server Error',
                'message' => $isDebug ? $message : 'Something went wrong. Please try again later.',
            ]);
        } else {
            echo '<!DOCTYPE html><html><head><title>500 Server Error</title></head>';
            echo '<body style="font-family:sans-serif;text-align:center;padding:50px;">';
            echo '<h1>500 - Server Error</h1>';
            echo '<p>' . htmlspecialchars($isDebug ? $message : 'Something went wrong.') . '</p>';
            echo '</body></html>';
        }
    }

    /**
     * Generate a URL for a named route.
     *
     * @param string $name   Route name
     * @param array  $params Parameters to substitute into the pattern
     * @return string|null Generated URL or null if route not found
     */
    public function url(string $name, array $params = []): ?string
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }

        $pattern = $this->namedRoutes[$name]['pattern'];

        // Replace placeholders with provided parameters
        foreach ($params as $key => $value) {
            $pattern = str_replace('{' . $key . '}', rawurlencode($value), $pattern);
        }

        $config = $GLOBALS['config'] ?? [];
        $baseUrl = $config['base_url'] ?? '';

        return $baseUrl . $pattern;
    }

    /**
     * Get all registered routes (for debugging/listing).
     *
     * @return array All routes organized by HTTP method
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get the currently matched route parameters.
     *
     * @return array Route parameters
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Check if a named route exists.
     *
     * @param string $name Route name
     * @return bool
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Get the current base path.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
