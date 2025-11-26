<?php
$config = json_decode(file_get_contents('config.json'), true);
$storagePath = realpath($config['storage_path']);

if (isset($_GET['path'])) {
    $filePath = urldecode($_GET['path']);
    $realFilePath = realpath($filePath);

    if ($realFilePath && is_file($realFilePath) && strpos($realFilePath, $storagePath) === 0) {
        
        $ext = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));
        $textContentExtensions = ['txt', 'csv', 'log', 'md', 'json', 'php', 'js', 'css', 'html', 'lua'];

        if (in_array($ext, $textContentExtensions)) {
            header('Content-Type: text/html; charset=utf-8');
            
            $content = file_get_contents($realFilePath);
            
            echo '<pre style="white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($content) . '</pre>';
        } else {
            header("HTTP/1.1 415 Unsupported Media Type");
            echo '<p class="p-5">Preview for this file type is not supported.</p>';
        }
    } else {
        header("HTTP/1.1 404 Not Found");
        echo '<p class="p-5">File not found or access denied.</p>';
    }
} else {
    header("HTTP/1.1 400 Bad Request");
    echo '<p class="p-5">No file path provided.</p>';
}
exit;
?>