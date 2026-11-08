<?php

namespace App\Services;

namespace App\Http\Controllers;

use App\Services\MobileNetworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAccessController extends Controller
{
    public function status(Request $request, MobileNetworkService $network): JsonResponse
    {
        $port = (int) ($request->query('port', 8000));
        $primaryIp = $network->getPrimaryLocalIp();
        $primaryUrl = $network->getMobileUrl($primaryIp, $port);

        return response()->json([
            'primary_ip' => $primaryIp,
            'primary_url' => $primaryUrl,
            'port' => $port,
            'bound_to_all_interfaces' => $network->isBoundToAllInterfaces(),
            'firewall_rule_active' => $network->isFirewallRuleActive(),
            'available_ips' => $network->getLocalIpAddresses(),
            'qr_svg' => $network->getQrCodeSvg($primaryUrl, 220),
        ]);
    }

    public function enable(Request $request, MobileNetworkService $network): JsonResponse
    {
        $network->setBindHost('0.0.0.0');

        return response()->json([
            'success' => true,
            'message' => 'Configured server to bind on 0.0.0.0. Please restart background services or run scripts/enable-mobile-access.ps1.',
            'bound_to_all_interfaces' => true,
        ]);
    }
}
