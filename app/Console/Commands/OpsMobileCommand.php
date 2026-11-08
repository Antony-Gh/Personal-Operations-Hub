<?php

namespace App\Console\Commands;

use App\Services\MobileNetworkService;
use Illuminate\Console\Command;

class OpsMobileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:mobile
                            {--port=8000 : Port number Laravel HTTP server is running on}
                            {--no-qr : Suppress the console QR code display}
                            {--json : Output information as a JSON object}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display mobile phone connection URLs, local LAN IP addresses, firewall status, and QR code';

    public function handle(MobileNetworkService $network): int
    {
        $port = (int) $this->option('port');
        $ips = $network->getLocalIpAddresses();
        $primaryIp = $network->getPrimaryLocalIp();
        $primaryUrl = $network->getMobileUrl($primaryIp, $port);
        $boundAll = $network->isBoundToAllInterfaces();
        $firewallOk = $network->isFirewallRuleActive();

        if ($this->option('json')) {
            $this->line(json_encode([
                'primary_ip' => $primaryIp,
                'primary_url' => $primaryUrl,
                'port' => $port,
                'bound_to_all_interfaces' => $boundAll,
                'firewall_rule_active' => $firewallOk,
                'available_ips' => $ips,
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>==========================================================</>');
        $this->line('<fg=cyan;options=bold>   Personal Operations Hub - Mobile Phone Connection      </>');
        $this->line('<fg=cyan;options=bold>==========================================================</>');
        $this->newLine();

        $this->line("  <fg=white;options=bold>Primary Mobile URL:</> <fg=green;options=bold>{$primaryUrl}</>");
        $this->newLine();

        $this->line('  <fg=white;options=bold>Network Status:</>');
        if ($boundAll) {
            $this->line('    [OK] Server configured to bind on all network interfaces (0.0.0.0).');
        } else {
            $this->line('    <fg=yellow>[!] Server currently defaults to localhost (127.0.0.1).</>');
            $this->line('        Run <fg=yellow>.\scripts\enable-mobile-access.ps1</> to enable local network access.');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            if ($firewallOk) {
                $this->line("    [OK] Windows Firewall inbound rule for port {$port} is active.");
            } else {
                $this->line("    <fg=yellow>[!] Windows Firewall rule not detected for port {$port}.</>");
                $this->line("        Run <fg=yellow>.\scripts\enable-mobile-access.ps1</> to add the rule automatically.");
            }
        }

        $this->newLine();
        $this->line('  <fg=white;options=bold>Detected Network Interfaces:</>');
        foreach ($ips as $entry) {
            $star = $entry['is_primary'] ? ' <fg=green>(Primary)</>' : '';
            $this->line(sprintf('    - %-16s [%s] %s%s', $entry['ip'], $entry['type'], $entry['name'], $star));
        }

        if (! $this->option('no-qr')) {
            $this->newLine();
            $this->line('  <fg=white;options=bold>Scan with Mobile Phone Camera (Same Wi-Fi):</>');
            $this->newLine();

            try {
                $qrTerminal = $network->getTerminalQrCode($primaryUrl);
                // Indent QR code for clean display
                $qrLines = explode("\n", $qrTerminal);
                foreach ($qrLines as $qLine) {
                    $this->line('    '.$qLine);
                }
            } catch (\Throwable $e) {
                $this->line("    (QR code generation: {$e->getMessage()})");
            }
        }

        $this->newLine();
        $this->line('  <fg=white;options=bold>How to Install on Mobile:</>');
        $this->line('    1. Connect your phone to the <fg=cyan>same Wi-Fi network</> as this computer.');
        $this->line("    2. Scan the QR code above or open <fg=cyan>{$primaryUrl}</> in your mobile browser.");
        $this->line('    3. <fg=yellow>iPhone (Safari):</> Tap Share button -> "Add to Home Screen".');
        $this->line('    4. <fg=yellow>Android (Chrome):</> Tap 3 dots menu -> "Install App" or "Add to Home screen".');
        $this->newLine();

        return Command::SUCCESS;
    }
}
