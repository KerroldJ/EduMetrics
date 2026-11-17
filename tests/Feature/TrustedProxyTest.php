<?php

test('home redirects use the scheme reported by trusted localhost proxies', function (string $proxyAddress, ?string $forwardedScheme, string $expectedScheme) {
    $serverVariables = ['REMOTE_ADDR' => $proxyAddress];

    if ($forwardedScheme !== null) {
        $serverVariables['HTTP_X_FORWARDED_PROTO'] = $forwardedScheme;
    }

    $response = $this->withServerVariables($serverVariables)->get('http://localhost/');

    $response->assertRedirect($expectedScheme.'://localhost/login');
})->with([
    'IPv4 localhost proxy' => ['127.0.0.1', 'https', 'https'],
    'IPv6 localhost proxy' => ['::1', 'https', 'https'],
    'untrusted proxy' => ['192.0.2.10', 'https', 'http'],
    'direct localhost request' => ['127.0.0.1', null, 'http'],
]);
