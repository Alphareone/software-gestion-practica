<?php
class App {
    use TrackActivityTrait;

    protected $controller = 'AuthController';
    protected $method = 'index';
    protected $params = [];

    public function __construct() {
        $url = $this->parseUrl();

        // Redirect /ml/* to /connections/* (except OAuth callback and notifications)
        if (isset($url[0]) && strtolower($url[0]) === 'ml'
            && !(isset($url[1]) && in_array(strtolower($url[1]), ['callback', 'notifications']))) {
            $redirect = URLROOT . '/connections';
            if (isset($url[1])) $redirect .= '/' . $url[1];
            for ($i = 2; isset($url[$i]); $i++) $redirect .= '/' . $url[$i];
            header('Location: ' . $redirect, true, 301);
            exit;
        }

        if (!isset($url[0]) || !$this->isSafeSegment($url[0])) {
            $url[0] = 'Auth';
        } else {
            $url[0] = ucfirst(strtolower($url[0]));
        }

        if (file_exists(dirname(__DIR__) . '/Controllers/' . $url[0] . 'Controller.php')) {
            $this->controller = $url[0] . 'Controller';
            unset($url[0]);
        }

        $this->controller = new $this->controller;

        if (isset($url[1])) {
            $methodName = strtolower($url[1]);
            if ($this->isSafeSegment($methodName)) {
                // Case-insensitive method matching
                $methods = get_class_methods($this->controller);
                $foundMethod = null;
                foreach ($methods as $m) {
                    if (strtolower($m) === $methodName) {
                        $foundMethod = $m;
                        break;
                    }
                }
                if ($foundMethod) {
                    $this->method = $foundMethod;
                    unset($url[1]);
                }
            }
        }

        $this->params = $url ? array_values($url) : [];

        // Check session idle timeout before dispatching (covers all routes)
        if (defined('SESSION_IDLE_TIMEOUT') && SESSION_IDLE_TIMEOUT > 0) {
            if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
                if ((time() - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
                    session_destroy();
                    header('Location: ' . URLROOT . '/auth?reason=idle_timeout');
                    exit;
                }
                $_SESSION['last_activity'] = time();
            }
        }

        call_user_func_array([$this->controller, $this->method], $this->params);

        // Track user activity if logged in (fallback for controllers that don't call it)
        $this->trackActivity();
    }

    public function parseUrl() {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }
        return ['Auth'];
    }

    private function isSafeSegment($segment) {
        if (!is_string($segment)) {
            return false;
        }
        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $segment) === 1;
    }

}