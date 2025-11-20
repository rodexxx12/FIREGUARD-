<?php
// reCAPTCHA configuration per domain. Update these with your actual keys.
// Map hostnames to their respective site/secret keys.
return [
    'domains' => [
        // Development
        'localhost' => [
            'site_key' => '', // TODO: add your localhost site key
            'secret_key' => '' // TODO: add your localhost secret key
        ],
        '127.0.0.1' => [
            'site_key' => '',
            'secret_key' => ''
        ],

        // Example production domain (replace with your real domain)
        'your-domain.com' => [
            'site_key' => '',
            'secret_key' => ''
        ],
    ],

    // Fallback used when current host isn't listed above.
    // Pre-populated with the existing key found in the code so current behavior is preserved.
    'default' => [
        'site_key' => '6LfbevgrAAAAACwWI_mLHlm-imp6R8BISH--kEqO',
        'secret_key' => ''
    ],
];

//6LcueRIsAAAAANGY2K15h5wZbFSyOX9-LAsdaea8 - online
//LfbevgrAAAAACwWI_mLHlm-imp6R8BISH--kEqO - offline


