<?php
namespace App\Core;

/**
 * المتحكم الأساسي: عرض القوالب + أدوات مساعدة
 */
abstract class Controller
{
    protected function view(string $viewTemplate, array $data = []): void
    {
        extract($data);
        $viewFile = BASE_PATH . '/app/Views/' . $viewTemplate . '.php';
        if (!file_exists($viewFile)) {
            Response::abort(500, 'القالب غير موجود: ' . $viewTemplate);
        }
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        ob_start();
        require BASE_PATH . '/app/Views/layouts/main.php';
        $output = ob_get_clean();
        // Ensure DOCTYPE is the very first token to prevent Quirks Mode
        $pos = stripos($output, '<!DOCTYPE');
        if ($pos !== false && $pos > 0) {
            $output = substr($output, $pos);
        }
        echo $output;
    }

    protected function viewBare(string $template, array $data = []): void
    {
        extract($data);
        require BASE_PATH . '/app/Views/' . $template . '.php';
    }

    protected function partial(string $template, array $data = []): string
    {
        extract($data);
        ob_start();
        require BASE_PATH . '/app/Views/' . $template . '.php';
        return ob_get_clean();
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function jsonInput(): array
    {
        $body = json_decode(file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}
