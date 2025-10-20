<?php

// Command: cat /usr/local/directadmin/data/admin/ip_blacklist | grep IP

return [
    'da_bfm_with_ip' => <<<'EOD'
192.0.2.123 20241201103335
EOD,

    'da_bfm_detailed_entry' => <<<'EOD'
216.81.248.88 20241201103335 BFM: Failed login attempts from whitelisted IP
EOD,

    'da_bfm_multiple_entries' => <<<'EOD'
192.0.2.123 20241201103335
203.0.113.45 20241201104512 BFM: WordPress brute force
216.81.248.88 20241201105020 BFM: Persistent auth failures despite whitelist
EOD,

    'da_bfm_empty' => '',

    'da_bfm_no_match' => '',
];
