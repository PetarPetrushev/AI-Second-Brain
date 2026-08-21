<?php
/**
 * config.php — Central configuration for the Second Brain Engine.
 * Defines defaults via define() and provides get_config() for dynamic overrides
 * stored in data/settings.json (written by the Settings UI tab).
 */

@ini_set('max_execution_time', '300');
@set_time_limit(300);

// ─── OpenRouter AI ────────────────────────────────────────────────────────────
define('OPENROUTER_API_KEY',     'sk-or-v1-YOUR_KEY_HERE');
define('OPENROUTER_CHAT_MODEL',  'deepseek/deepseek-v4-flash-0731');
define('OPENROUTER_FAST_MODEL',  'deepseek/deepseek-v4-flash-0731');
define('OPENROUTER_EMBED_MODEL', 'perplexity/pplx-embed-v1-0.6b');

// ─── SMTP (SSL / port 465) ────────────────────────────────────────────────────
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      465);
define('SMTP_USER',      'you@gmail.com');
define('SMTP_PASS',      'your_app_password');
define('SMTP_FROM',      'you@gmail.com');
define('SMTP_FROM_NAME', 'Second Brain');
define('SMTP_TO',        'you@gmail.com');

// ─── Paths ────────────────────────────────────────────────────────────────────
define('DATA_DIR', __DIR__ . '/data');

// ─── Vector / RAG tuning ─────────────────────────────────────────────────────
define('MAX_SAFE_CHARS',        6000);
define('RECENCY_DECAY_LAMBDA',  0.015);
define('SEMANTIC_WEIGHT',       0.75);
define('RECENCY_WEIGHT',        0.25);

// ─── Authentication ───────────────────────────────────────────────────────────
define('AUTH_USERNAME',      'admin');
// Secure bcrypt hash for password (default is 'admin123'). Generate new hashes with password_hash('your_pass', PASSWORD_DEFAULT)
define('AUTH_PASSWORD_HASH', '$2y$10$LgnSRzW9MQFo/qsNBSMM5.HactNzqMLANx3Va2J1BVGX6zpfmllD.');
define('SESSION_LIFETIME',   604800); // 7 days (1 week) in seconds

// ─── Security ─────────────────────────────────────────────────────────────────
define('CRON_SECRET_KEY', 'change-this-to-a-random-secret-string');

// ─── Session & Auth Helper Functions ──────────────────────────────────────────

/**
 * Initialize PHP session with a 1-week lifetime and secure cookie settings.
 */
function init_auth_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = (int)SESSION_LIFETIME;
        @ini_set('session.gc_maxlifetime', (string)$lifetime);
        @ini_set('session.cookie_lifetime', (string)$lifetime);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Check if the current session is authenticated.
 */
function is_authenticated(): bool
{
    init_auth_session();
    return !empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Validate provided username and password against configured secure bcrypt hash.
 */
function check_credentials(string $user, string $pass): bool
{
    $expected_user = (string)get_config('AUTH_USERNAME', 'admin');
    $expected_hash = (string)get_config('AUTH_PASSWORD_HASH', '');

    // Fallback if legacy plaintext AUTH_PASSWORD was set
    if ($expected_hash === '') {
        $legacy_pass = (string)get_config('AUTH_PASSWORD', '');
        if ($legacy_pass !== '') {
            $expected_hash = password_hash($legacy_pass, PASSWORD_DEFAULT);
        }
    }

    if (!hash_equals($expected_user, $user)) {
        return false;
    }

    // Verify bcrypt/Argon2 password hash
    if ($expected_hash !== '' && password_verify($pass, $expected_hash)) {
        return true;
    }

    // Support fallback in case a raw string was provided without hashing
    if ($expected_hash !== '' && hash_equals($expected_hash, $pass)) {
        return true;
    }

    return false;
}

/**
 * Authenticate session on successful login.
 */
function login_user(string $user, string $pass): bool
{
    if (check_credentials($user, $pass)) {
        init_auth_session();
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['username']      = $user;
        $_SESSION['login_time']    = time();
        return true;
    }
    return false;
}

/**
 * Log out user and destroy session.
 */
function logout_user(): void
{
    init_auth_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

// ─── Dynamic settings cache ───────────────────────────────────────────────────
$_DYNAMIC_CONFIG_CACHE = null;

/**
 * Retrieve a configuration value, preferring dynamic overrides from settings.json
 * over the compiled-in defaults above.
 *
 * @param  string $key   Constant name (e.g. 'OPENROUTER_API_KEY')
 * @param  mixed  $default Fallback value if neither source has the key
 * @return mixed
 */
function get_config(string $key, mixed $default = null): mixed
{
    global $_DYNAMIC_CONFIG_CACHE;

    // Load settings.json once per request
    if ($_DYNAMIC_CONFIG_CACHE === null) {
        $settings_file = DATA_DIR . '/settings.json';
        if (file_exists($settings_file)) {
            $decoded = json_decode(file_get_contents($settings_file), true);
            $_DYNAMIC_CONFIG_CACHE = is_array($decoded) ? $decoded : [];
        } else {
            $_DYNAMIC_CONFIG_CACHE = [];
        }
    }

    // Dynamic override wins
    if (isset($_DYNAMIC_CONFIG_CACHE[$key]) && $_DYNAMIC_CONFIG_CACHE[$key] !== '') {
        return $_DYNAMIC_CONFIG_CACHE[$key];
    }

    // Fall back to compiled constant
    if (defined($key)) {
        return constant($key);
    }

    return $default;
}

/**
 * Bootstrap: ensure the core data directory tree exists.
 */
function bootstrap_data_dirs(): void
{
    $dirs = [
        DATA_DIR,
        DATA_DIR . '/entries',
        DATA_DIR . '/vectors',
        DATA_DIR . '/digests',
        DATA_DIR . '/chats',
        DATA_DIR . '/chats/sessions',
        DATA_DIR . '/logs',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // Protect data/ with .htaccess if missing
    $htaccess = DATA_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }

    // Initialise empty JSON stores if missing
    $stores = [
        DATA_DIR . '/memories.json'      => [],
        DATA_DIR . '/vectors/index.json' => [],
        DATA_DIR . '/settings.json'      => (object)[],
    ];
    foreach ($stores as $path => $empty) {
        if (!file_exists($path)) {
            file_put_contents($path, json_encode($empty, JSON_PRETTY_PRINT));
        }
    }
}

bootstrap_data_dirs();
