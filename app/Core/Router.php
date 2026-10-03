<?php
namespace App\Core;

/**
 * موجّه الطلبات: يدعم GET/POST ومعاملات ديناميكية {id}
 */
class Router
{
    private array $routes = [];

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, $handler, array $middleware): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method'     => $method,
            'pattern'    => '#^' . $pattern . '$#',
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(): void
    {
        // إعادة تحميل الصلاحيات من قاعدة البيانات في بداية كل طلب
        // للمستخدمين المصدقين حتى تظهر أي تعديلات على الصلاحيات مباشرة
        if (Auth::check()) {
            Auth::refresh();
        }

        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'];

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if (!preg_match($route['pattern'], $uri, $matches)) continue;

            foreach ($route['middleware'] as $mw) {
                if ($mw === 'auth' && !Auth::check()) {
                    $this->isApi($uri) ? Response::json(['error' => 'unauthorized'], 401)
                                       : Response::redirect('/login');
                    return;
                }
                if (str_starts_with($mw, 'perm:')) {
                    $perms = array_filter(explode(',', substr($mw, 5)), 'trim');
                    if (!Auth::canAny($perms)) {
                        $this->isApi($uri) ? Response::json(['error' => 'forbidden'], 403)
                                           : Response::abort(403, 'ليس لديك صلاحية للوصول لهذه الصفحة');
                        return;
                    }
                }
                if ($mw === 'api') {
                    if (!ApiAuth::check()) {
                        Response::json(['error' => 'unauthorized'], 401);
                        return;
                    }
                    // مزامنة جلسة مؤقتة كي تعمل خدمات الويب الداخلية
                    // (Auth::id/branchId/can, ApprovalService, StockService...) مع طلبات API
                    $apiUser = ApiAuth::user();
                    $_SESSION['user_id']   = $apiUser['id'];
                    $_SESSION['user_name'] = $apiUser['full_name'];
                    $_SESSION['role_id']   = $apiUser['role_id'];
                    $_SESSION['role_name'] = $apiUser['role_name'];
                    $_SESSION['branch_id'] = $apiUser['branch_id'];
                    $_SESSION['perms']     = Auth::effectivePermissions((int)$apiUser['role_id'], (string)$apiUser['role_name']);
                }
                // للمزامنة الهجينة بين الفروع والخادم المركزي: مفتاح سري مشترك بدل جلسة مستخدم
                if ($mw === 'api_or_synckey') {
                    $key = $_SERVER['HTTP_X_SYNC_KEY'] ?? '';
                    $expected = (string) (App::config('sync')['remote_key'] ?? '');
                    $validSyncKey = $expected !== '' && hash_equals($expected, $key);
                    if (!$validSyncKey && !ApiAuth::check()) {
                        Response::json(['error' => 'unauthorized'], 401);
                        return;
                    }
                }
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler = $route['handler'];
            if (is_callable($handler) && !is_array($handler)) {
                $handler(...array_values($params));
            } else {
                [$class, $action] = $handler;
                $controller = new $class();
                $controller->$action(...array_values($params));
            }
            return;
        }

        $this->isApi($uri) ? Response::json(['error' => 'not found'], 404)
                           : Response::abort(404, 'الصفحة غير موجودة');
    }

    private function isApi(string $uri): bool
    {
        return str_starts_with($uri, '/api/');
    }
}
