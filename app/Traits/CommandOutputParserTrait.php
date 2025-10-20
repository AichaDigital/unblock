<?php

namespace App\Traits;

use App\Notifications\Admin\ErrorParsingNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Log, Notification};

trait CommandOutputParserTrait
{
    public function parseOutput(string $output): array
    {
        $decodedJson = json_decode($output, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decodedJson;
        }

        $lines = explode("\n", $output);
        $data = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $data[] = $line;
        }

        return $data;
    }

    public function searchDeny(array $data, array $needles = ['csf.deny', 'Temporary Blocks']): array
    {
        $ip = '';
        $date = '';

        foreach ($data as $line) {
            // Check if the line contains any of the needles
            if ($this->containsAny($line, $needles)) {

                // Extract the IP address using a regular expression
                if (preg_match('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', $line, $ipMatches)) {
                    $ip = $ipMatches[0];
                } else {
                    Log::error('IP not found in line: '.$line);
                    // continue; // If no IP is found, skip this line
                }

                // Extract the date using a regular expression
                $date = $this->parseDateFromLine($line);
            }
        }

        // Notification admin
        if (empty($ip) || empty($date)) {
            $logDetails = "IP: $ip, Date: $date, Line: ".json_encode($data);
            // Notification admin
            Notification::route('mail', config('unblock.admin_email'))
                ->notify(new ErrorParsingNotification($logDetails));
        }

        if (empty($ip) && empty($date)) {
            return [];
        }

        return [
            'ip' => $ip,
            'date' => $date,
        ];
    }

    public function containsAny(string $line, array $needles): bool
    {
        return ! empty(array_filter($needles, fn ($needle) => str_contains($line, $needle)));
    }

    public function parseDateFromLine(string $line): string
    {
        // Attempt to match the date pattern
        if (preg_match('/\b[A-Za-z]{3}\s+[A-Za-z]{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}\s+\d{4}\b/', $line, $dateMatches)) {
            try {
                // Use Carbon to parse the date
                $date = Carbon::parse($dateMatches[0]);

                return $date->toDateTimeString(); // Return the date in a standard format
            } catch (\Exception $e) {
                Log::error("Exception occurred during date parsing: {$line} ".$e->getMessage());

                return '';
            }
        }

        return '';
    }
}
