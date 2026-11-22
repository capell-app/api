<?php

declare(strict_types=1);

return [
    'webhook_health_label' => 'Konfiguration der Headless-Revalidierungs-Webhooks',
    'webhook_health_passed' => 'Webhook-Endpunkte und Warteschlangenkonfiguration sind bereit.',
    'webhook_health_failed' => 'Webhook-Endpunkte oder die asynchrone Warteschlange sind nicht korrekt konfiguriert.',
    'webhook_health_remediation' => 'Legen Sie für jeden Endpunkt eine Website, eine HTTPS-URL und ein unabhängiges Secret mit mindestens 32 Zeichen fest und betreiben Sie einen überwachten asynchronen Queue-Worker.',
    'not_found' => 'Seite nicht gefunden.',
    'forbidden' => 'Mit diesem Token kann diese Vorschau nicht angezeigt werden.',
    'invalid_query' => 'Prüfen Sie die Abfrageparameter der API und versuchen Sie es erneut.',
    'invalid_cursor' => 'Dieser Cursor ist abgelaufen oder gehört zu einer anderen Anfrage. Beginnen Sie erneut ohne Cursor.',
    'unavailable' => 'Die Prüfung der Inhaltsberechtigung ist vorübergehend nicht verfügbar. Versuchen Sie es in Kürze erneut.',
    'too_large' => 'Die API-Antwort überschreitet das konfigurierte Byte-Limit.',
];
