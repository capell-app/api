<?php

declare(strict_types=1);

return [
    'headers' => [
        'failed' => 'Die API-Resolve-Route hat nicht die erwarteten Versions- und Cache-Tag-Header ausgegeben.',
        'label' => 'API: Antwort-Header konfiguriert',
        'passed' => 'Die API-Resolve-Route gibt Versions- und Cache-Tag-Header aus.',
        'remediation' => 'Stellen Sie sicher, dass die Route weiterhin über ResolvePageController aufgelöst wird und jede JSON-Antwort X-Capell-Api-Version und X-Capell-Cache-Tags enthält.',
    ],
    'middleware' => [
        'failed' => 'Die API-Middleware-Konfiguration enthält ungültige Werte: :failures.',
        'failure' => [
            'invalid_value' => ':key muss null, false, ein Middleware-String oder eine Liste von Middleware-Strings sein.',
            'missing_route_middleware' => 'Der v1-Resolve-Route fehlt die konfigurierte Middleware: :middleware.',
        ],
        'label' => 'API: Anfragebehandlung konfiguriert',
        'passed' => 'Die API-Middleware-Konfiguration kann von der Host-App sicher veröffentlicht werden.',
        'remediation' => 'Aktualisieren Sie config/capell-api.php, sodass jede Middleware-Option null, false, ein Middleware-String oder eine Liste von Middleware-Strings ist.',
    ],
    'route' => [
        'failed' => 'Die API-v1-Route zur Seitenauflösung ist nicht verwendbar: :failures.',
        'failure' => [
            'controller' => 'Die Route verweist nicht auf :controller.',
            'method' => 'Die Route akzeptiert keine GET-Anfragen.',
            'missing' => 'Die benannte Route :route fehlt.',
            'not_installed' => ':package ist nicht als installiert markiert.',
            'uri' => 'Die Route-URI ist nicht :uri.',
        ],
        'label' => 'API: Route zur Seitensuche registriert',
        'passed' => 'Die API-v1-Route zur Seitenauflösung ist registriert und verweist auf den Resolve-Controller.',
        'remediation' => 'Stellen Sie sicher, dass ApiServiceProvider startet, solange capell-app/api installiert ist und seine Paketrouten geladen sind.',
    ],
];
