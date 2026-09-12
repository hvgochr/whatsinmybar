<?php

// Runs only in a disposable image, with no persistent volumes or external network.
$config = $argv[1] ?? '/etc/frankenphp/Caddyfile';
$port = (int) ($argv[2] ?? 8080);
$name = str_repeat('a', 32).'.png';
foreach (['avatars', 'recipes'] as $directory) {
    mkdir('/app/public/uploads/'.$directory, 0775, true);
    file_put_contents('/app/public/uploads/'.$directory.'/'.$name, 'routing-test');
    file_put_contents('/app/public/uploads/'.$directory.'/.'.$name.'.tmp', 'private-temporary');
}
$server = proc_open(['frankenphp', 'run', '--config', $config, '--adapter', 'caddyfile'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/tmp/server.log', 'a'], 2 => ['file', '/tmp/server.log', 'a']], $pipes);
if (!is_resource($server)) {
    throw new RuntimeException('Cannot start FrankenPHP.');
}
try {
    $ready = false;
    for ($attempt = 0; $attempt < 100; ++$attempt) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
        if (false !== $socket) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        throw new RuntimeException('FrankenPHP did not start: '.file_get_contents('/tmp/server.log'));
    }
    $paths = [
        '/uploads/avatars/'.$name => 200,
        '/uploads/recipes/'.$name => 404,
        '/uploads/%72ecipes/'.$name => 404,
        '/uploads/avatars/../recipes/'.$name => 404,
        '/uploads/avatars/.'.$name.'.tmp' => 404,
        '/uploads/recipes/.'.$name.'.tmp' => 404,
    ];
    foreach ($paths as $path => $expected) {
        foreach (['GET', 'HEAD'] as $method) {
            $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'timeout' => 2]]);
            file_get_contents('http://127.0.0.1:'.$port.$path, false, $context);
            $headers = http_get_last_response_headers();
            if (null === $headers || !str_contains($headers[0], ' '.$expected.' ')) {
                throw new RuntimeException($method.' '.$path.' expected '.$expected.', got '.json_encode($headers));
            }
        }
    }
    echo 'Upload routing passed for '.$config." (12 requests).\n";
} finally {
    proc_terminate($server);
    proc_close($server);
}
