<?php
/**
 * ============================================================================
 * StudyFlow - View Renderer
 * Student Self-Teaching App
 *
 * Renders PHP view templates within a layout, supports partials, and manages
 * page metadata (title, scripts, styles). Uses output buffering to capture
 * view content and inject it into the layout.
 *
 * Directory Structure:
 *   views/
 *     layouts/
 *       main.php          - Primary layout with HTML skeleton
 *     partials/
 *       header.php        - Site header/navigation
 *       footer.php        - Site footer
 *       sidebar.php       - Sidebar navigation
 *       flash.php         - Flash message display
 *     auth/
 *       login.php         - Login page
 *       register.php      - Registration page
 *     dashboard/
 *       index.php         - Dashboard view
 *     errors/
 *       404.php           - Not found page
 *       500.php           - Server error page
 *     ... (other view directories)
 * ============================================================================
 */

class View
{
    /**
     * Base directory for view files.
     * @var string
     */
    private string $viewDir;

    /**
     * Default layout file name (without extension).
     * @var string
     */
    private string $layout;

    /**
     * View file extension.
     * @var string
     */
    private string $extension;

    /**
     * Page title.
     * @var string
     */
    private string $title = '';

    /**
     * Additional CSS files to include.
     * @var array
     */
    private array $styles = [];

    /**
     * Additional JavaScript files to include.
     * @var array
     */
    private array $scripts = [];

    /**
     * Inline JavaScript to include.
     * @var array
     */
    private array $inlineScripts = [];

    /**
     * Inline CSS to include.
     * @var array
     */
    private array $inlineStyles = [];

    /**
     * Meta tags for the page.
     * @var array
     */
    private array $metaTags = [];

    /**
     * Data to pass to all views (shared data).
     * @var array
     */
    private static array $sharedData = [];

    /**
     * Named content sections captured during rendering.
     * @var array
     */
    private array $sections = [];

    /**
     * Stack of sections currently being captured.
     * @var array
     */
    private array $sectionStack = [];

    /**
     * Whether to use a layout.
     * @var bool
     */
    private bool $useLayout = true;

    /**
     * Constructor.
     *
     * Initializes view settings from application configuration.
     */
    public function __construct()
    {
        $config = $GLOBALS['config'] ?? [];
        $viewConfig = $config['views'] ?? [];

        $this->viewDir   = $viewConfig['directory'] ?? (defined('VIEW_PATH') ? VIEW_PATH : __DIR__ . '/../views');
        $this->layout    = $viewConfig['layout'] ?? 'layouts/main';
        $this->extension = $viewConfig['extension'] ?? '.php';

        // Set default page title
        $appName = $config['app_name'] ?? 'StudyFlow';
        $this->title = $appName;
    }

    // =========================================================================
    // Core Rendering
    // =========================================================================

    /**
     * Render a view within the layout.
     *
     * Captures the view output via output buffering, then includes
     * the layout file which receives the captured content.
     *
     * @param string      $view    View file path relative to views directory (without extension)
     * @param array       $data    Data to pass to the view (extracted as variables)
     * @param string|null $layout  Override the default layout (null = use default, '' = no layout)
     * @return void
     */
    public function render(string $view, array $data = [], ?string $layout = null): void
    {
        // Merge shared data with view-specific data (view data takes priority)
        $data = array_merge(self::$sharedData, $data);

        // Set title if provided in data
        if (isset($data['title'])) {
            $this->setTitle($data['title']);
        }

        // Add commonly needed variables
        $data['_session'] = $GLOBALS['session'] ?? null;
        $data['_config']  = $GLOBALS['config'] ?? [];
        $data['_view']    = $this;

        // Resolve the view file path
        $viewFile = $this->resolveViewPath($view);

        if (!file_exists($viewFile)) {
            error_log("[View] View file not found: {$viewFile}");
            throw new RuntimeException("View file not found: {$view}");
        }

        // Capture the view content using output buffering
        $content = $this->capture($viewFile, $data);

        // Determine layout
        $useLayout = $this->useLayout;
        if ($layout === '') {
            $useLayout = false;
        } elseif ($layout !== null) {
            $this->layout = $layout;
        }

        // Render within layout or output directly
        if ($useLayout) {
            $layoutFile = $this->resolveViewPath($this->layout);

            if (!file_exists($layoutFile)) {
                error_log("[View] Layout file not found: {$layoutFile}");
                echo $content;
                return;
            }

            // Pass content and metadata to the layout
            $layoutData = array_merge($data, [
                'content'       => $content,
                'pageTitle'     => $this->title,
                'styles'        => $this->styles,
                'scripts'       => $this->scripts,
                'inlineScripts' => $this->inlineScripts,
                'inlineStyles'  => $this->inlineStyles,
                'metaTags'      => $this->metaTags,
                'sections'      => $this->sections,
            ]);

            echo $this->capture($layoutFile, $layoutData);
        } else {
            echo $content;
        }
    }

    /**
     * Render a view without a layout.
     *
     * @param string $view View file path
     * @param array  $data Data to pass to the view
     * @return void
     */
    public function renderPartial(string $view, array $data = []): void
    {
        $this->useLayout = false;
        $this->render($view, $data, '');
    }

    /**
     * Capture a view file's output using output buffering.
     *
     * Extracts the data array into individual variables available
     * in the view file's scope.
     *
     * @param string $__file View file path (absolute)
     * @param array  $__data Data to extract into the view scope
     * @return string Captured output
     */
    private function capture(string $__file, array $__data): string
    {
        // Extract data into local variables
        extract($__data, EXTR_SKIP);

        ob_start();

        try {
            include $__file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }

    /**
     * Resolve a view name to an absolute file path.
     *
     * @param string $view View name (e.g., "auth/login", "layouts/main")
     * @return string Absolute file path
     */
    private function resolveViewPath(string $view): string
    {
        // Remove extension if already present
        $view = preg_replace('/\.php$/', '', $view);

        // Convert dot notation to directory separators
        $view = str_replace('.', '/', $view);

        return $this->viewDir . '/' . $view . $this->extension;
    }

    // =========================================================================
    // Partials
    // =========================================================================

    /**
     * Render a partial view (a reusable view fragment).
     *
     * Partials are typically stored in views/partials/ but can be
     * anywhere within the views directory.
     *
     * @param string $partial Partial view path (e.g., "partials/header")
     * @param array  $data    Data to pass to the partial
     * @return string Rendered partial HTML
     */
    public function partial(string $partial, array $data = []): string
    {
        $data = array_merge(self::$sharedData, $data);
        $data['_session'] = $GLOBALS['session'] ?? null;
        $data['_config']  = $GLOBALS['config'] ?? [];
        $data['_view']    = $this;

        $partialFile = $this->resolveViewPath($partial);

        if (!file_exists($partialFile)) {
            error_log("[View] Partial not found: {$partialFile}");
            return "<!-- Partial not found: {$partial} -->";
        }

        return $this->capture($partialFile, $data);
    }

    /**
     * Include and echo a partial view directly.
     *
     * @param string $partial Partial view path
     * @param array  $data    Data to pass to the partial
     * @return void
     */
    public function include(string $partial, array $data = []): void
    {
        echo $this->partial($partial, $data);
    }

    // =========================================================================
    // Sections
    // =========================================================================

    /**
     * Start capturing a named section.
     *
     * @param string $name Section name
     * @return void
     */
    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    /**
     * End capturing the current section.
     *
     * @return void
     */
    public function endSection(): void
    {
        if (empty($this->sectionStack)) {
            throw new RuntimeException('Cannot end section: no section has been started.');
        }

        $name = array_pop($this->sectionStack);
        $this->sections[$name] = ob_get_clean();
    }

    /**
     * Get the content of a named section.
     *
     * @param string $name    Section name
     * @param string $default Default content if section doesn't exist
     * @return string Section content
     */
    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Check if a section has content.
     *
     * @param string $name Section name
     * @return bool
     */
    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && !empty($this->sections[$name]);
    }

    // =========================================================================
    // Page Metadata
    // =========================================================================

    /**
     * Set the page title.
     *
     * @param string $title     Page title
     * @param bool   $append    Whether to append to the app name
     * @return self
     */
    public function setTitle(string $title, bool $append = true): self
    {
        $config = $GLOBALS['config'] ?? [];
        $appName = $config['app_name'] ?? 'StudyFlow';

        if ($append && !empty($title)) {
            $this->title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' | ' . $appName;
        } else {
            $this->title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        }

        return $this;
    }

    /**
     * Get the page title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Add a CSS file to be included in the page.
     *
     * @param string $url   CSS file URL
     * @param array  $attrs Additional attributes (e.g., media, integrity)
     * @return self
     */
    public function addStyle(string $url, array $attrs = []): self
    {
        $this->styles[] = [
            'url'   => $url,
            'attrs' => $attrs,
        ];
        return $this;
    }

    /**
     * Add inline CSS to the page.
     *
     * @param string $css CSS content
     * @return self
     */
    public function addInlineStyle(string $css): self
    {
        $this->inlineStyles[] = $css;
        return $this;
    }

    /**
     * Add a JavaScript file to be included in the page.
     *
     * @param string $url   JavaScript file URL
     * @param array  $attrs Additional attributes (e.g., defer, async, type)
     * @return self
     */
    public function addScript(string $url, array $attrs = []): self
    {
        $this->scripts[] = [
            'url'   => $url,
            'attrs' => $attrs,
        ];
        return $this;
    }

    /**
     * Add inline JavaScript to the page.
     *
     * @param string $js JavaScript content
     * @return self
     */
    public function addInlineScript(string $js): self
    {
        $this->inlineScripts[] = $js;
        return $this;
    }

    /**
     * Add a meta tag to the page.
     *
     * @param string $name    Meta tag name
     * @param string $content Meta tag content
     * @return self
     */
    public function addMeta(string $name, string $content): self
    {
        $this->metaTags[] = [
            'name'    => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
        ];
        return $this;
    }

    // =========================================================================
    // Render Helpers (for use in layout files)
    // =========================================================================

    /**
     * Render all queued CSS link tags.
     *
     * @return string HTML link elements
     */
    public function renderStyles(): string
    {
        $html = '';

        foreach ($this->styles as $style) {
            $attrs = '';
            foreach ($style['attrs'] as $key => $value) {
                $attrs .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
                    . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            }

            $html .= '<link rel="stylesheet" href="'
                . htmlspecialchars($style['url'], ENT_QUOTES, 'UTF-8')
                . '"' . $attrs . '>' . "\n";
        }

        foreach ($this->inlineStyles as $css) {
            $html .= '<style>' . $css . '</style>' . "\n";
        }

        return $html;
    }

    /**
     * Render all queued JavaScript script tags.
     *
     * @return string HTML script elements
     */
    public function renderScripts(): string
    {
        $html = '';

        foreach ($this->scripts as $script) {
            $attrs = '';
            foreach ($script['attrs'] as $key => $value) {
                if ($value === true) {
                    $attrs .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                } else {
                    $attrs .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
                        . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                }
            }

            $html .= '<script src="'
                . htmlspecialchars($script['url'], ENT_QUOTES, 'UTF-8')
                . '"' . $attrs . '></script>' . "\n";
        }

        foreach ($this->inlineScripts as $js) {
            $html .= '<script>' . $js . '</script>' . "\n";
        }

        return $html;
    }

    /**
     * Render all queued meta tags.
     *
     * @return string HTML meta elements
     */
    public function renderMeta(): string
    {
        $html = '';

        foreach ($this->metaTags as $meta) {
            $html .= '<meta name="' . $meta['name'] . '" content="' . $meta['content'] . '">' . "\n";
        }

        return $html;
    }

    // =========================================================================
    // Shared Data
    // =========================================================================

    /**
     * Share data with all views.
     *
     * Shared data is merged into every render() call, making it available
     * in all views and layouts without explicitly passing it each time.
     *
     * @param string|array $key   Key name or associative array of key => value
     * @param mixed        $value Value (only used if $key is a string)
     * @return void
     */
    public static function share(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            self::$sharedData = array_merge(self::$sharedData, $key);
        } else {
            self::$sharedData[$key] = $value;
        }
    }

    /**
     * Get all shared data.
     *
     * @return array
     */
    public static function getSharedData(): array
    {
        return self::$sharedData;
    }

    // =========================================================================
    // Utility Helpers (available as $this->method() in views)
    // =========================================================================

    /**
     * Escape a string for safe HTML output.
     *
     * @param string|null $value String to escape
     * @return string Escaped string
     */
    public function escape(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Alias for escape().
     *
     * @param string|null $value String to escape
     * @return string
     */
    public function e(?string $value): string
    {
        return $this->escape($value);
    }

    /**
     * Generate the full URL for an asset file.
     *
     * @param string $path Asset path relative to assets/ directory
     * @return string Full asset URL
     */
    public function asset(string $path): string
    {
        $config = $GLOBALS['config'] ?? [];
        $baseUrl = $config['base_url'] ?? '';

        return $baseUrl . '/assets/' . ltrim($path, '/');
    }

    /**
     * Generate a URL for the application.
     *
     * @param string $path URL path
     * @return string Full URL
     */
    public function url(string $path = ''): string
    {
        $config = $GLOBALS['config'] ?? [];
        $baseUrl = $config['base_url'] ?? '';

        return $baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * Get the CSRF token field for forms.
     *
     * @return string HTML hidden input
     */
    public function csrfField(): string
    {
        $session = $GLOBALS['session'] ?? null;

        if ($session instanceof Session) {
            return $session->csrfField();
        }

        return '';
    }

    /**
     * Get the CSRF meta tag for AJAX requests.
     *
     * @return string HTML meta tag
     */
    public function csrfMeta(): string
    {
        $session = $GLOBALS['session'] ?? null;

        if ($session instanceof Session) {
            return $session->csrfMeta();
        }

        return '';
    }

    /**
     * Get flash message data.
     *
     * @param string $key Flash message key
     * @return mixed|null Flash data or null
     */
    public function flash(string $key): mixed
    {
        $session = $GLOBALS['session'] ?? null;

        if ($session instanceof Session) {
            return $session->getFlash($key);
        }

        return null;
    }

    /**
     * Get old input value (for form repopulation after validation errors).
     *
     * @param string $key     Input field name
     * @param string $default Default value
     * @return string
     */
    public function old(string $key, string $default = ''): string
    {
        $session = $GLOBALS['session'] ?? null;

        if ($session instanceof Session) {
            $oldInput = $session->getFlash('old_input');
            if (is_array($oldInput) && isset($oldInput[$key])) {
                return htmlspecialchars((string) $oldInput[$key], ENT_QUOTES, 'UTF-8');
            }
        }

        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Check if the current path matches the given pattern.
     * Useful for highlighting active navigation links.
     *
     * @param string $pattern URL pattern to match (supports * wildcard)
     * @return bool
     */
    public function isActive(string $pattern): bool
    {
        $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
        $currentPath = parse_url($currentPath, PHP_URL_PATH) ?: '/';

        // Exact match
        if ($currentPath === $pattern) {
            return true;
        }

        // Wildcard match
        if (strpos($pattern, '*') !== false) {
            $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
            return (bool) preg_match('/^' . $regex . '$/', $currentPath);
        }

        // Prefix match (for sections)
        return strpos($currentPath, $pattern) === 0;
    }

    /**
     * Get the active CSS class if the pattern matches.
     *
     * @param string $pattern      URL pattern to match
     * @param string $activeClass  CSS class to return if active
     * @param string $defaultClass CSS class to return if not active
     * @return string
     */
    public function activeClass(string $pattern, string $activeClass = 'active', string $defaultClass = ''): string
    {
        return $this->isActive($pattern) ? $activeClass : $defaultClass;
    }

    /**
     * Set the layout for this view rendering.
     *
     * @param string $layout Layout name (e.g., "layouts/main")
     * @return self
     */
    public function setLayout(string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Disable the layout for this rendering.
     *
     * @return self
     */
    public function noLayout(): self
    {
        $this->useLayout = false;
        return $this;
    }

    /**
     * Format a date for display.
     *
     * @param string $date   Date string
     * @param string $format Output format
     * @return string Formatted date
     */
    public function formatDate(string $date, string $format = ''): string
    {
        if (empty($format)) {
            $format = defined('DATE_FORMAT_DISPLAY') ? DATE_FORMAT_DISPLAY : 'M j, Y';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : $date;
    }

    /**
     * Truncate text to a specified length.
     *
     * @param string $text   Text to truncate
     * @param int    $length Maximum length
     * @param string $suffix Suffix to append when truncated
     * @return string
     */
    public function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }
}
