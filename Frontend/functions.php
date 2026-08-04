<?php

/**

 * Fungsi bantu bersama untuk seluruh SSO Authentication Web Service.

 */



const SESSION_LIFETIME_MINUTES = 30;



/**

 * Membuat token acak untuk sesi global (mewakili langkah

 * "Create Token, Autentikasi Token and Global Session" pada alur SSO).

 */

function generate_token(): string

{

    return bin2hex(random_bytes(32));

}



/**

 * Katalog aplikasi yang tersedia lewat SSO, sesuai kotak highlight

 * pada flowchart (SIMRS, AMINO Mobile, LAPOR AMINO, WBS).

 * Kode di sini harus cocok dengan nilai pada kolom `hak_akses` di tabel users.

 */

function get_app_catalog(): array

{

    return [

        'SIMRS' => [

            'name' => 'SIMRS',

            'desc' => 'Sistem informasi manajemen rumah sakit.',

            'icon' => '<path d="M12 2v20M2 12h20"/>',

        ],

        'AMINO_MOBILE' => [

            'name' => 'AMINO Mobile',

            'desc' => 'Layanan mobile untuk staf dan pasien.',

            'icon' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',

        ],

        'LAPOR_AMINO' => [

            'name' => 'LAPOR AMINO',

            'desc' => 'Kanal pelaporan dan pengaduan internal.',

            'icon' => '<path d="M3 21l1.5-4.5A8 8 0 1 1 8 20.5L3 21z"/>',

        ],

        'WBS' => [

            'name' => 'WBS',

            'desc' => 'Whistleblowing system untuk pelaporan pelanggaran.',

            'icon' => '<path d="M4 21V4a1 1 0 0 1 1-1h9l-2 5 2 5H6"/>',

        ],

    ];

}



/**

 * Membaca hak akses menu user (mewakili langkah "Membaca Hak Akses menu user"

 * yang bersumber dari Table User) dan mencocokkannya dengan katalog aplikasi.

 */

function get_user_apps(string $hakAksesCsv): array

{

    $catalog = get_app_catalog();

    $allowedCodes = array_map('trim', explode(',', $hakAksesCsv));



    $apps = [];

    foreach ($allowedCodes as $code) {

        if (isset($catalog[$code])) {

            $apps[$code] = $catalog[$code];

        }

    }



    return $apps;

}



function format_mmss(int $seconds): string

{

    $seconds = max(0, $seconds);

    return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);

}

