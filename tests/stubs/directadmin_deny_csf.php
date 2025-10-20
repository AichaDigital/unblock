<?php

// Command: csf -g 192.0.2.123

return [
    'csf' => <<<'EOD'
    Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
    No matches found for 192.0.2.123 in iptables


    IPSET: Set:chain_DENY Match:192.0.2.123 Setting: File:/etc/csf/csf.deny


    ip6tables:

    Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
    No matches found for 192.0.2.123 in ip6tables

    csf.deny: 192.0.2.123 # BFM: dovecot1=31 (blocked-ip.example.net) - Sun Dec  1 10:33:35 2024
    EOD,
];
