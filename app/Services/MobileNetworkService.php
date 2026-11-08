<?php

namespace App\Services;

class MobileNetworkService
{
    private ?array $cachedIps = null;

    private ?bool $cachedFirewall = null;

    /**
     * Get list of usable local IPv4 addresses on the host machine.
     *
     * @return array<int, array{ip: string, name: string, type: string, is_primary: bool}>
     */
    public function getLocalIpAddresses(): array
    {
        if ($this->cachedIps !== null) {
            return $this->cachedIps;
        }

        $addresses = [];

        // Try PowerShell Get-NetIPAddress on Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Get-NetIPAddress -AddressFamily IPv4 | Select-Object IPAddress, InterfaceAlias | ConvertTo-Json -Compress"';
            $output = @shell_exec($cmd);

            if ($output) {
                $decoded = json_decode(trim($output), true);
                if (isset($decoded['IPAddress'])) {
                    $decoded = [$decoded];
                }

                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        $ip = $item['IPAddress'] ?? '';
                        $alias = $item['InterfaceAlias'] ?? 'Local Area Network';

                        if ($this->isValidLanIp($ip, $alias)) {
                            $lower = strtolower($alias);
                            $type = str_contains($lower, 'wi-fi') || str_contains($lower, 'wifi') || str_contains($lower, 'wlan') || str_contains($lower, 'wireless')
                                ? 'Wi-Fi'
                                : 'Ethernet';

                            $addresses[] = [
                                'ip' => $ip,
                                'name' => $alias,
                                'type' => $type,
                                'is_primary' => false,
                            ];
                        }
                    }
                }
            }
        }

        // Fallback to gethostbynamel / gethostname if PowerShell returned nothing or non-Windows
        if (empty($addresses)) {
            $host = gethostname();
            $ips = gethostbynamel($host) ?: [];
            foreach ($ips as $ip) {
                if ($this->isValidLanIp($ip, '')) {
                    $addresses[] = [
                        'ip' => $ip,
                        'name' => 'Local Network',
                        'type' => 'LAN',
                        'is_primary' => false,
                    ];
                }
            }
        }

        // Mark best primary candidate (prefer Wi-Fi, then Ethernet, then 192.168.x.x)
        if (! empty($addresses)) {
            usort($addresses, function ($a, $b) {
                if ($a['type'] === 'Wi-Fi' && $b['type'] !== 'Wi-Fi') {
                    return -1;
                }
                if ($b['type'] === 'Wi-Fi' && $a['type'] !== 'Wi-Fi') {
                    return 1;
                }
                if (str_starts_with($a['ip'], '192.168.') && ! str_starts_with($b['ip'], '192.168.')) {
                    return -1;
                }
                if (! str_starts_with($a['ip'], '192.168.') && str_starts_with($b['ip'], '192.168.')) {
                    return 1;
                }

                return strcmp($a['ip'], $b['ip']);
            });

            $addresses[0]['is_primary'] = true;
        }

        $this->cachedIps = $addresses;

        return $addresses;
    }

    /**
     * Get the best primary local IP address, or fallback to 127.0.0.1.
     */
    public function getPrimaryLocalIp(): string
    {
        $list = $this->getLocalIpAddresses();
        foreach ($list as $entry) {
            if ($entry['is_primary']) {
                return $entry['ip'];
            }
        }

        return ! empty($list) ? $list[0]['ip'] : '127.0.0.1';
    }

    /**
     * Get mobile access URL.
     */
    public function getMobileUrl(?string $ip = null, int $port = 8000): string
    {
        $targetIp = $ip ?: $this->getPrimaryLocalIp();

        return "http://{$targetIp}:{$port}";
    }

    /**
     * Check if the persistent bind-host configuration is set to listen on all interfaces.
     */
    public function isBoundToAllInterfaces(): bool
    {
        $configPath = base_path('.run/bind-host.txt');
        if (file_exists($configPath)) {
            $content = trim((string) file_get_contents($configPath));
            if ($content === '0.0.0.0') {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the persistent bind-host configuration.
     */
    public function setBindHost(string $host): void
    {
        $runDir = base_path('.run');
        if (! is_dir($runDir)) {
            @mkdir($runDir, 0755, true);
        }
        file_put_contents($runDir.'/bind-host.txt', $host);
    }

    /**
     * Check whether Windows Firewall has port 8000 allowed for incoming connections.
     */
    public function isFirewallRuleActive(): bool
    {
        if ($this->cachedFirewall !== null) {
            return $this->cachedFirewall;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->cachedFirewall = true;

            return true;
        }

        $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Get-NetFirewallRule -DisplayName \'Personal Operations Hub (Port 8000)\' -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Enabled"';
        $out = @shell_exec($cmd);
        $this->cachedFirewall = (trim((string) $out) === 'True');

        return $this->cachedFirewall;
    }

    /**
     * Generate SVG QR code for mobile connection URL.
     */
    public function getQrCodeSvg(?string $url = null, int $size = 220): string
    {
        $targetUrl = $url ?: $this->getMobileUrl();

        return QrCodeGenerator::toSvg($targetUrl, $size);
    }

    /**
     * Generate Terminal ASCII/Unicode QR code.
     */
    public function getTerminalQrCode(?string $url = null): string
    {
        $targetUrl = $url ?: $this->getMobileUrl();

        return QrCodeGenerator::toTerminal($targetUrl);
    }

    /**
     * Check if an IP address is a usable local area network IPv4 address.
     */
    private function isValidLanIp(string $ip, string $alias): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        // Exclude loopback and link-local
        if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
            return false;
        }

        // Exclude virtual/container interfaces
        $lowerAlias = strtolower($alias);
        $virtualKeywords = ['docker', 'vethernet', 'wsl', 'hyper-v', 'virtual', 'vmware', 'loopback', 'bluetooth'];
        foreach ($virtualKeywords as $kw) {
            if (str_contains($lowerAlias, $kw)) {
                return false;
            }
        }

        // Private ranges: 192.168.x.x, 10.x.x.x, 172.16-31.x.x
        return str_starts_with($ip, '192.168.') ||
            str_starts_with($ip, '10.') ||
            preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip) === 1;
    }
}
