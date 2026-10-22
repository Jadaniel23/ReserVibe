<?php
// src/Config/config.php
//
// Single source of truth for tunable settings. Anything a defense might ask
// "where is that number set?" about belongs here, not hardcoded in a view.

/**
 * Reads a key from the project .env file.
 *
 * The name is deliberately not getEnv(): PHP function names are
 * case-insensitive, so getEnv() *is* the built-in getenv(). The
 * function_exists() guard below would then always be true, silently skip the
 * definition, and every lookup would fall through to PHP's getenv() — which
 * takes a bool as its second argument and returns false for a missing key.
 * That is why this helper is named reservibe_env().
 */
if (!function_exists('reservibe_env')) {
    function reservibe_env(string $key, $default = null)
    {
        static $env = null;

        if ($env === null) {
            $envPath = __DIR__ . '/../../.env';
            $parsed = file_exists($envPath) ? parse_ini_file($envPath) : false;
            $env = is_array($parsed) ? $parsed : [];
        }

        if (isset($env[$key]) && $env[$key] !== '') {
            return $env[$key];
        }

        // Fall back to real environment variables, so hosting can override
        // .env without editing files.
        $fromSystem = getenv($key);

        if ($fromSystem !== false && $fromSystem !== '') {
            return $fromSystem;
        }

        return $default;
    }
}

$root = dirname(__DIR__, 2);

// The resort is in the Philippines; hold countdowns and timestamps must read in
// resort-local time rather than whatever the hosting default happens to be.
$timezone = (string) reservibe_env('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set($timezone);

return [
    'timezone' => $timezone,

    'db' => [
        'host' => reservibe_env('DB_HOST', 'localhost'),
        'name' => reservibe_env('DB_NAME', 'reservibe'),
        'user' => reservibe_env('DB_USER', 'root'),
        'pass' => reservibe_env('DB_PASS', ''),
    ],

    'app_name' => 'ReserVibe Prototype',

    // Module 3 — cost calculation
    'currency' => 'PHP',
    'currency_symbol' => "\u{20B1}",           // Peso sign
    'deposit_rate' => 0.50,                    // required down payment

    // Module 4 — temporary holds
    'hold_minutes' => 90,                      // slot lock lifetime before payment proof

    // Add-on catalogue. The client sends add-on *codes*; the server decides
    // what they cost, so a tampered request cannot change the price.
    'addons' => [
        ['code' => 'videoke',  'label' => 'Videoke Machine',  'rate' => 800.0],
        ['code' => 'grilling', 'label' => 'Grilling Station', 'rate' => 300.0],
    ],

    // Module 5 — payment proof uploads
    'uploads' => [
        'dir' => $root . '/public/uploads/payment-proofs',
        'max_bytes' => 5 * 1024 * 1024,        // 5 MB
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    // Module 5 — how the guest settles the deposit.
    //
    // The single source of truth for "what can I pay with?". The booking view
    // renders these options from here, and the API validates whatever the
    // client sends against this same list, so the two can never disagree.
    //
    //   requires_proof     guest pays outside the system, then uploads a receipt
    //   redirect           in production this hands off to an online gateway
    //   settles_on_arrival nothing is collected online; the desk collects it
    'payment_methods' => [
        [
            'code' => 'gcash',
            'label' => 'GCash',
            'group' => 'E-Wallet',
            'requires_proof' => true,
            'instructions' => 'School demo only. No live GCash QR or payment processing is configured. Do not send money.',
        ],
        [
            'code' => 'maya',
            'label' => 'Maya',
            'group' => 'E-Wallet',
            'requires_proof' => true,
            'instructions' => 'School demo only. No live Maya QR or payment processing is configured. Do not send money.',
        ],
        [
            'code' => 'bank_transfer',
            'label' => 'Bank Transfer',
            'group' => 'Bank',
            'requires_proof' => true,
            'instructions' => 'School demo only. No bank account or payment processing is configured. Do not send money.',
        ],
        [
            'code' => 'paypal',
            'label' => 'PayPal',
            'group' => 'Online',
            'requires_proof' => false,
            'redirect' => true,
            'instructions' => 'School demo only. PayPal is not connected and no payment will be processed.',
        ],
        [
            'code' => 'card',
            'label' => 'Credit / Debit Card',
            'group' => 'Card',
            'requires_proof' => false,
            'redirect' => true,
            'instructions' => 'School demo only. Card processing is not connected and no payment will be processed.',
        ],
        [
            'code' => 'cash',
            'label' => 'Cash at the Resort',
            'group' => 'On-site',
            'requires_proof' => false,
            'settles_on_arrival' => true,
            'instructions' => 'School demo only. This records a cash-at-resort choice; it does not confirm a real reservation or collect money.',
        ],
    ],

    'paths' => [
        'root' => $root,
        'data' => $root . '/data',
        'lock' => $root . '/data/.booking.lock',
    ],
];
