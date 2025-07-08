spl_autoload_register(function ($class) {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (!file_exists($file)) {
        // Thử chuyển sang lowercase nếu không tìm thấy file
        $file = strtolower($file);
    }
    require_once $file;
});