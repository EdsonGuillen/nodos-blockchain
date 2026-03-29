<?php

return [
    // Permitir todas las rutas — necesario para comunicación entre nodos
    'paths' => ['api/*', '*'],

    'allowed_methods' => ['*'],

    // En producción restringir a IPs del equipo; en desarrollo dejar abierto
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
