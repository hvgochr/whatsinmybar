<?php

// Exercise the actual edge proxy rules in a disposable container without networking.
// Only listener/upstream addresses change; forwarding directives stay intact.
foreach (['/tmp/edge.Caddyfile', '/tmp/edge.prod.Caddyfile'] as $source) {
    $config = file_get_contents($source);
    $config = strtr($config, [
        ':80 {' => 'http://127.0.0.1:19080 {',
        'whatsinmybar.charradehugo.com {' => 'http://127.0.0.1:19080 {',
        'whatsinmybar-api:8080' => '127.0.0.1:19081',
        'api:80' => '127.0.0.1:19081',
        'whatsinmybar-web:3000' => '127.0.0.1:19081',
        'web:3000' => '127.0.0.1:19081',
    ]);
    $config .= <<<'CADDY'

        http://127.0.0.1:19081 {
            respond "{http.request.header.X-Forwarded-For}|{http.request.header.Forwarded}|{http.request.header.X-Real-IP}"
        }
        CADDY;
    file_put_contents('/tmp/proxy-test.Caddyfile', $config);
    $server = proc_open(['frankenphp', 'run', '--config', '/tmp/proxy-test.Caddyfile', '--adapter', 'caddyfile'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/tmp/proxy-test.log', 'a'], 2 => ['file', '/tmp/proxy-test.log', 'a']], $pipes);
    if (!is_resource($server)) {
        throw new RuntimeException('Cannot start Caddy.');
    }
    try {
        $ready = false;
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $socket = @fsockopen('127.0.0.1', 19080, $errno, $error, 0.1);
            if (false !== $socket) {
                fclose($socket);
                $ready = true;
                break;
            }
            usleep(100000);
        }
        if (!$ready) {
            throw new RuntimeException('Caddy did not start: '.file_get_contents('/tmp/proxy-test.log'));
        }
        foreach (['/api/auth/login', '/recipes'] as $path) {
            $context = stream_context_create(['http' => ['timeout' => 2, 'header' => "X-Forwarded-For: 198.51.100.1, 203.0.113.2\r\nForwarded: for=198.51.100.3\r\nX-Real-IP: 198.51.100.4\r\n"]]);
            $body = file_get_contents('http://127.0.0.1:19080'.$path, false, $context);
            if ('127.0.0.1||' !== $body) {
                throw new RuntimeException($source.' '.$path.' leaked spoofed headers: '.$body);
            }
        }
        echo 'Proxy header isolation passed for '.$source.".\n";
    } finally {
        proc_terminate($server);
        proc_close($server);
    }
}
