<?php

return [
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
    'no_sandbox' => (bool) env('BROWSERSHOT_NO_SANDBOX', false),
];
