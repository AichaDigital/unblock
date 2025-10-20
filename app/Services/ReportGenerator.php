<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Host, Report, User};
use App\Services\Firewall\FirewallAnalysisResult;
use Illuminate\Support\Facades\Log;

/**
 * Report Generator - Single Responsibility Pattern
 *
 * Handles all report generation operations including:
 * - Creating database records
 * - Formatting analysis results
 * - Preparing data for notifications
 * - Report status management
 */
class ReportGenerator
{
    /**
     * Generate a comprehensive firewall report
     */
    public function generateReport(
        string $ipAddress,
        User $user,
        Host $host,
        FirewallAnalysisResult $analysisResult,
        ?array $unblockResults = null
    ): Report {
        try {
            $reportData = $this->formatReportData($analysisResult, $unblockResults);
            $blockSources = $this->extractBlockSources($analysisResult->getLogs());

            $report = Report::create([
                'ip' => $ipAddress,  // Correct column name
                'user_id' => $user->id,
                'host_id' => $host->id,
                'analysis' => $reportData['analysis'],  // JSON field containing was_blocked
                'logs' => $reportData['logs'],  // JSON field for logs
            ]);

            Log::info('Firewall report generated successfully', [
                'report_id' => $report->id,
                'ip_address' => $ipAddress,
                'user_id' => $user->id,
                'host_id' => $host->id,
                'was_blocked' => $analysisResult->isBlocked(),
                'block_sources' => $blockSources,
            ]);

            return $report;

        } catch (\Exception $e) {
            Log::error('Failed to generate firewall report', [
                'ip_address' => $ipAddress,
                'user_id' => $user->id,
                'host_id' => $host->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Format analysis and logs data for storage
     */
    private function formatReportData(FirewallAnalysisResult $analysisResult, ?array $unblockResults): array
    {
        // CORRECCION: Usar block_sources del analysis ya calculado por el analyzer
        $analysis = $analysisResult->getAnalysis();
        $blockSources = $analysis['block_sources'] ?? $this->extractBlockSources($analysisResult->getLogs());

        $analysisData = [
            'was_blocked' => $analysisResult->isBlocked(),
            'block_sources' => $blockSources,
            'analysis_timestamp' => now()->toISOString(),
        ];

        // Add specific blocking details if IP was blocked
        if ($analysisResult->isBlocked()) {
            $analysisData['blocking_details'] = $this->extractBlockingDetails($analysisResult->getLogs());
        }

        // Add unblock operation status if performed
        if ($unblockResults) {
            $analysisData['unblock_performed'] = true;
            $analysisData['unblock_status'] = $unblockResults;
        }

        $logsData = $this->formatLogsData($analysisResult->getLogs());

        return [
            'analysis' => $analysisData,
            'logs' => $logsData,
        ];
    }

    /**
     * Extract block sources from logs analysis
     */
    private function extractBlockSources(array $logs): array
    {
        $sources = [];

        // Check for CSF blocks
        if (isset($logs['csf']) && $this->containsBlockingPatterns($logs['csf'], 'csf')) {
            $sources[] = 'csf';
        }

        // Check for BFM blocks
        if (isset($logs['da_bfm_check']) && $this->containsBlockingPatterns($logs['da_bfm_check'], 'bfm')) {
            $sources[] = 'da_bfm';
        }

        // Check service logs for blocks
        $serviceMap = [
            'exim_directadmin' => 'exim',
            'dovecot_directadmin' => 'dovecot',
            'mod_security_da' => 'modsecurity',
        ];

        foreach ($serviceMap as $logKey => $serviceName) {
            if (isset($logs[$logKey]) && $this->containsBlockingPatterns($logs[$logKey], $serviceName)) {
                $sources[] = $serviceName;
            }
        }

        return $sources;
    }

    /**
     * Check if logs contain blocking patterns for a specific source
     */
    private function containsBlockingPatterns(string $logContent, string $source): bool
    {
        if (empty($logContent)) {
            return false;
        }

        switch ($source) {
            case 'csf':
                return str_contains($logContent, 'DENYIN') ||
                       str_contains($logContent, 'DENYOUT') ||
                       str_contains($logContent, 'Temporary');

            case 'bfm':
                // BFM blacklist contains IP entries, any non-empty content indicates a block
                return trim($logContent) !== '' && ! str_contains($logContent, 'No matches');

            case 'exim':
                return stripos($logContent, 'rejected') !== false ||
                       stripos($logContent, 'denied') !== false ||
                       stripos($logContent, 'authentication failed') !== false;

            case 'dovecot':
                return stripos($logContent, 'auth failed') !== false ||
                       stripos($logContent, 'authentication failure') !== false ||
                       stripos($logContent, 'login failed') !== false;

            case 'modsecurity':
                return stripos($logContent, 'access denied') !== false ||
                       stripos($logContent, 'attack detected') !== false ||
                       stripos($logContent, 'rule triggered') !== false;
        }

        return false;
    }

    /**
     * Extract detailed blocking information from logs
     */
    private function extractBlockingDetails(array $logs): array
    {
        $details = [];
        $blockSources = $this->extractBlockSources($logs);

        foreach ($blockSources as $source) {
            switch ($source) {
                case 'csf':
                    $details['csf'] = $this->extractCsfDetails($logs);
                    break;
                case 'da_bfm':
                    $details['bfm'] = $this->extractBfmDetails($logs);
                    break;
                case 'exim':
                    $details['exim'] = $this->extractServiceDetails($logs, 'exim');
                    break;
                case 'dovecot':
                    $details['dovecot'] = $this->extractServiceDetails($logs, 'dovecot');
                    break;
                case 'modsecurity':
                    $details['modsecurity'] = $this->extractServiceDetails($logs, 'modsecurity');
                    break;
            }
        }

        return $details;
    }

    /**
     * Extract CSF-specific blocking details
     */
    private function extractCsfDetails(array $rawLogs): array
    {
        $csfData = $rawLogs['csf'] ?? '';

        return [
            'type' => $this->detectCsfBlockType($csfData),
            'rules' => $this->extractCsfRules($csfData),
            'timestamp' => $this->extractTimestamp($csfData),
        ];
    }

    /**
     * Extract BFM-specific blocking details
     */
    private function extractBfmDetails(array $rawLogs): array
    {
        $bfmData = $rawLogs['da_bfm_check'] ?? '';

        return [
            'blacklist_entry' => $this->extractBfmEntry($bfmData),
            'timestamp' => $this->extractBfmTimestamp($bfmData),
        ];
    }

    /**
     * Extract service-specific blocking details
     */
    private function extractServiceDetails(array $rawLogs, string $service): array
    {
        $serviceKey = $this->getServiceLogKey($service);
        $serviceData = $rawLogs[$serviceKey] ?? '';

        return [
            'service' => $service,
            'log_entries' => $this->parseServiceLogs($serviceData),
            'block_patterns' => $this->identifyBlockPatterns($serviceData, $service),
        ];
    }

    /**
     * Format logs data for human-readable display
     */
    private function formatLogsData(array $rawLogs): array
    {
        $formatted = [];

        foreach ($rawLogs as $logType => $logContent) {
            if (empty($logContent)) {
                continue;
            }

            // PROCESAMIENTO ESPECIAL PARA MOD_SECURITY: Convertir JSON a estructura esperada
            if (str_contains($logType, 'mod_security') && $this->isJsonContent($logContent)) {
                $processed = $this->processModSecurityJson($logContent);
                if (! empty($processed)) {
                    $formatted[$logType] = $processed;

                    continue;
                }
            }

            $formatted[$logType] = [
                'type' => $logType,
                'content' => $this->sanitizeLogContent($logContent),
                'line_count' => $this->countLogLines($logContent),
                'size' => strlen($logContent),
            ];
        }

        return $formatted;
    }

    /**
     * Determine the overall status of the report
     */
    private function determineReportStatus(FirewallAnalysisResult $analysisResult, ?array $unblockResults): string
    {
        if (! $analysisResult->isBlocked()) {
            return 'no_blocks_found';
        }

        if ($unblockResults) {
            $success = true;
            foreach ($unblockResults as $result) {
                if (isset($result['success']) && ! $result['success']) {
                    $success = false;
                    break;
                }
            }

            return $success ? 'unblocked_successfully' : 'unblock_failed';
        }

        return 'blocks_detected';
    }

    /**
     * Helper methods for data extraction
     */
    private function detectCsfBlockType(string $csfData): string
    {
        if (str_contains($csfData, 'DENYIN')) {
            return 'deny_input';
        }
        if (str_contains($csfData, 'DENYOUT')) {
            return 'deny_output';
        }
        if (str_contains($csfData, 'Temporary')) {
            return 'temporary';
        }

        return 'unknown';
    }

    private function extractCsfRules(string $csfData): array
    {
        preg_match_all('/Chain\s+(\w+).*?target\s+(\w+)/i', $csfData, $matches);

        return array_combine($matches[1] ?? [], $matches[2] ?? []);
    }

    private function extractTimestamp(string $data): ?string
    {
        preg_match('/\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}/', $data, $matches);

        return $matches[0] ?? null;
    }

    private function extractBfmEntry(string $bfmData): ?string
    {
        $lines = explode("\n", $bfmData);

        return trim($lines[0] ?? '');
    }

    private function extractBfmTimestamp(string $bfmData): ?string
    {
        preg_match('/(\d{14})/', $bfmData, $matches);
        if (isset($matches[1])) {
            return \DateTime::createFromFormat('YmdHis', $matches[1])->format('Y-m-d H:i:s');
        }

        return null;
    }

    private function getServiceLogKey(string $service): string
    {
        $keyMap = [
            'exim' => 'exim_directadmin',
            'dovecot' => 'dovecot_directadmin',
            'modsecurity' => 'mod_security_da',
        ];

        return $keyMap[$service] ?? $service;
    }

    private function parseServiceLogs(string $serviceData): array
    {
        return array_filter(explode("\n", $serviceData));
    }

    private function identifyBlockPatterns(string $serviceData, string $service): array
    {
        $patterns = [];
        $lines = explode("\n", $serviceData);

        foreach ($lines as $line) {
            if ($this->isBlockingPattern($line, $service)) {
                $patterns[] = trim($line);
            }
        }

        return array_unique($patterns);
    }

    private function isBlockingPattern(string $line, string $service): bool
    {
        $blockingKeywords = [
            'exim' => ['rejected', 'denied', 'blocked', 'authentication failed'],
            'dovecot' => ['auth failed', 'authentication failure', 'login failed'],
            'modsecurity' => ['access denied', 'attack detected', 'rule triggered'],
        ];

        $keywords = $blockingKeywords[$service] ?? [];

        foreach ($keywords as $keyword) {
            if (stripos($line, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeLogContent(string $content): string
    {
        // Remove sensitive information and normalize line endings
        $sanitized = preg_replace('/password[=:]\s*\S+/i', 'password=***', $content);

        return str_replace(["\r\n", "\r"], "\n", $sanitized);
    }

    private function countLogLines(string $content): int
    {
        return count(array_filter(explode("\n", $content)));
    }

    /**
     * Check if content appears to be JSON
     */
    private function isJsonContent(string $content): bool
    {
        $trimmed = trim($content);

        return str_starts_with($trimmed, '{') && str_contains($trimmed, '"transaction"');
    }

    /**
     * Process ModSecurity JSON into the expected array structure for the view
     */
    private function processModSecurityJson(string $jsonContent): array
    {
        $lines = explode("\n", trim($jsonContent));
        $processed = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            try {
                $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                // Extract fields expected by the view
                if (isset($data['transaction'])) {
                    $transaction = $data['transaction'];

                    $entry = [
                        'cliente_ip' => $transaction['client_ip'] ?? 'N/A',
                        'time_stamp' => $transaction['time_stamp'] ?? 'N/A',
                        'uri' => $transaction['request']['uri'] ?? 'N/A',
                        'method' => $transaction['request']['method'] ?? 'N/A',
                        'host' => $transaction['request']['headers']['Host'] ?? 'N/A',
                        'http_code' => $transaction['response']['http_code'] ?? 'N/A',
                    ];

                    // Extract message and ruleId from first message if available
                    if (isset($data['messages'][0])) {
                        $message = $data['messages'][0];
                        $entry['message'] = $message['message'] ?? 'N/A';
                        $entry['ruleId'] = $message['details']['ruleId'] ?? 'N/A';
                    } else {
                        $entry['message'] = 'N/A';
                        $entry['ruleId'] = 'N/A';
                    }

                    $processed[] = $entry;
                }
            } catch (\JsonException $e) {
                Log::error('Error parsing ModSecurity JSON in report: '.$e->getMessage(), [
                    'line' => $line,
                ]);
            }
        }

        return $processed;
    }
}
