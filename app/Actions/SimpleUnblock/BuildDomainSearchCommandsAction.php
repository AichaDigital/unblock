<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Build Domain Search Commands Action
 *
 * Constructs SSH commands to search for domain in server logs.
 * Commands are panel-specific and properly escaped for security.
 *
 * Search targets:
 * - Apache access logs (last 7 days)
 * - Nginx access logs (last 7 days)
 * - Exim mail logs (last 7 days)
 * - cPanel domlogs (if panel is cpanel)
 */
class BuildDomainSearchCommandsAction
{
    use AsAction;

    /**
     * Build domain search commands for SSH execution
     *
     * @return array<string> Array of grep commands (will be joined with OR)
     */
    public function handle(string $ip, string $domain, string $panelType): array
    {
        $ipEscaped = escapeshellarg($ip);
        $domainEscaped = escapeshellarg($domain);

        $commands = [
            // Apache access logs (last 7 days)
            $this->buildApacheCommand($ipEscaped, $domainEscaped),

            // Nginx access logs (last 7 days)
            $this->buildNginxCommand($ipEscaped, $domainEscaped),

            // Exim mail logs (last 7 days)
            $this->buildEximCommand($ipEscaped, $domainEscaped),
        ];

        // Add panel-specific commands
        if ($panelType === 'cpanel') {
            $commands[] = $this->buildCpanelDomlogsCommand($ipEscaped, $domainEscaped);
        }

        return $commands;
    }

    /**
     * Build command for Apache logs
     */
    private function buildApacheCommand(string $ipEscaped, string $domainEscaped): string
    {
        return "find /var/log/apache2 -name 'access.log*' -mtime -7 -type f -exec grep -l {$ipEscaped} {} \\; 2>/dev/null | xargs grep -i {$domainEscaped} 2>/dev/null | head -1";
    }

    /**
     * Build command for Nginx logs
     */
    private function buildNginxCommand(string $ipEscaped, string $domainEscaped): string
    {
        return "find /var/log/nginx -name 'access.log*' -mtime -7 -type f -exec grep -l {$ipEscaped} {} \\; 2>/dev/null | xargs grep -i {$domainEscaped} 2>/dev/null | head -1";
    }

    /**
     * Build command for Exim logs
     */
    private function buildEximCommand(string $ipEscaped, string $domainEscaped): string
    {
        return "find /var/log/exim -name 'mainlog*' -mtime -7 -type f -exec grep -l {$ipEscaped} {} \\; 2>/dev/null | xargs grep -i {$domainEscaped} 2>/dev/null | head -1";
    }

    /**
     * Build command for cPanel domlogs
     */
    private function buildCpanelDomlogsCommand(string $ipEscaped, string $domainEscaped): string
    {
        return "find /usr/local/apache/domlogs -name '*{$domainEscaped}*' -mtime -7 -type f -exec grep {$ipEscaped} {} \\; 2>/dev/null | head -1";
    }
}
