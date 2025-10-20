<?php

return [
    'csf' => <<<'EOD'

    Table  Chain            num   pkts bytes target     prot opt in     out     source               destination         

    No matches found for 209.38.235.141 in iptables

    IPSET: Set:chain_DENY Match:209.38.235.141 Setting: File:/etc/csf/csf.deny

    ip6tables:

    Table  Chain            num   pkts bytes target     prot opt in     out     source               destination         

    No matches found for 209.38.235.141 in ip6tables
    csf.deny: 209.38.235.141 # BFM: exim2=30 (DE/Germany/-) - Fri Dec  6 10:30:58 2024

    EOD,
    'exim_directadmin' => <<<'EOD'
    2024-12-01 00:46:21 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 01:53:08 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 03:00:28 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 04:07:54 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 05:15:16 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 06:21:18 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 07:25:21 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 08:27:34 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 09:29:13 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 10:31:10 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 18:43:55 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 19:44:50 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 20:45:40 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 21:46:19 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 22:46:56 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-01 23:46:59 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 00:47:16 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 01:47:43 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 02:48:23 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 03:48:03 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 04:48:22 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 05:49:35 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 06:52:51 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 07:55:53 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 08:59:20 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 10:02:32 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 11:04:48 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 12:07:51 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    2024-12-02 13:11:57 login authenticator failed for (ADMIN) [209.38.235.141]: 535 Incorrect authentication data (set_id=secretaria@bizkaiatletismo.eu)
    EOD,
    'dovecot_directadmin' => '',
    'mod_security_da' => <<<EOD
    [

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "autosportpaez.com",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "baytuka.ovh",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "biofilmtest.es",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "biofilmtest.eu",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "bizkaiatletismo.com",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "bizkaiatletismo.eu",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "blog.aerolugo.com",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "blog.elvallin.es",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "blog.viozoex.com",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "brqx.carpones.com",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "calcentro.com",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "calcetinesmarbe.com",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "carpdiem.carpones.com",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "carpones.com",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        },

        {

            "client_ip": "209.38.235.141",

            "uri": "\/.env",

            "host": "casasvargas.es",

            "message": "Restricted File Access Attempt",

            "ruleId": "930130"

        }

    ]
    EOD
];
