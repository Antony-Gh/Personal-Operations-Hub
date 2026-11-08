<?php

use App\Models\User;
use App\Services\MobileNetworkService;
use App\Services\QrCodeGenerator;

test('qr code generator creates valid svg for url', function () {
    $url = 'http://192.168.1.50:8000';
    $svg = QrCodeGenerator::toSvg($url, 220);

    expect($svg)
        ->toContain('<svg')
        ->toContain('viewBox="0 0')
        ->toContain('fill="#1e1b4b"')
        ->toContain('</svg>');
});

test('qr code generator creates terminal ascii string', function () {
    $url = 'http://192.168.1.50:8000';
    $terminal = QrCodeGenerator::toTerminal($url);

    expect($terminal)->toBeString()
        ->and(strlen($terminal))->toBeGreaterThan(50);
});

test('mobile network service resolves local ip addresses', function () {
    $service = app(MobileNetworkService::class);
    $ips = $service->getLocalIpAddresses();

    expect($ips)->toBeArray();

    $primaryIp = $service->getPrimaryLocalIp();
    expect($primaryIp)->toBeString();
    expect(filter_var($primaryIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))->not->toBeFalse();

    $mobileUrl = $service->getMobileUrl($primaryIp, 8000);
    expect($mobileUrl)->toBe("http://{$primaryIp}:8000");
});

test('mobile network service can get and set bind host config', function () {
    $service = app(MobileNetworkService::class);
    $configPath = base_path('.run/bind-host.txt');
    $original = file_exists($configPath) ? file_get_contents($configPath) : null;

    try {
        $service->setBindHost('0.0.0.0');
        expect($service->isBoundToAllInterfaces())->toBeTrue();

        $service->setBindHost('127.0.0.1');
        expect($service->isBoundToAllInterfaces())->toBeFalse();
    } finally {
        if ($original !== null) {
            file_put_contents($configPath, $original);
        } else {
            @unlink($configPath);
        }
    }
});

test('ops:mobile artisan command executes and outputs json', function () {
    $this->artisan('ops:mobile', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('primary_ip');
});

test('authenticated user can fetch mobile status json', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('mobile.status'));

    $response->assertOk()
        ->assertJsonStructure([
            'primary_ip',
            'primary_url',
            'port',
            'bound_to_all_interfaces',
            'firewall_rule_active',
            'available_ips',
            'qr_svg',
        ]);
});

test('authenticated user can toggle bind host to all interfaces', function () {
    $user = User::factory()->create();
    $configPath = base_path('.run/bind-host.txt');
    $original = file_exists($configPath) ? file_get_contents($configPath) : null;

    try {
        $response = $this->actingAs($user)->postJson(route('mobile.enable'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'bound_to_all_interfaces' => true,
            ]);
    } finally {
        if ($original !== null) {
            file_put_contents($configPath, $original);
        } else {
            @unlink($configPath);
        }
    }
});

test('mobile requests render production assets and never reference localhost 5173 when hot file is absent', function () {
    $hotPath = public_path('hot');
    $hadHot = file_exists($hotPath);
    if ($hadHot) {
        @unlink($hotPath);
    }

    try {
        $response = $this->withServerVariables([
            'HTTP_HOST' => '192.168.1.50:8000',
            'SERVER_NAME' => '192.168.1.50',
            'SERVER_PORT' => '8000',
        ])->get('/login');

        $response->assertOk();
        $content = $response->getContent();

        expect($content)->not->toContain('127.0.0.1:5173')
            ->not->toContain('localhost:5173')
            ->toContain('/build/assets/');
    } finally {
        if ($hadHot) {
            file_put_contents($hotPath, "http://127.0.0.1:5173\n");
        }
    }
});
