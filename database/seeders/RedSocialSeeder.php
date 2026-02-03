<?php

namespace Database\Seeders;

use App\Models\Catalogos\RedSocial;
use Illuminate\Database\Seeder;

class RedSocialSeeder extends Seeder
{
    public function run(): void
    {
        $redes = [
            [
                'nombre' => 'Facebook',
                'icono' => 'fab fa-facebook',
                'url_base' => 'https://facebook.com/',
            ],
            [
                'nombre' => 'Instagram',
                'icono' => 'fab fa-instagram',
                'url_base' => 'https://instagram.com/',
            ],
            [
                'nombre' => 'Twitter/X',
                'icono' => 'fab fa-x-twitter',
                'url_base' => 'https://x.com/',
            ],
            [
                'nombre' => 'LinkedIn',
                'icono' => 'fab fa-linkedin',
                'url_base' => 'https://linkedin.com/in/',
            ],
            [
                'nombre' => 'TikTok',
                'icono' => 'fab fa-tiktok',
                'url_base' => 'https://tiktok.com/@',
            ],
            [
                'nombre' => 'WhatsApp',
                'icono' => 'fab fa-whatsapp',
                'url_base' => 'https://wa.me/',
            ],
            [
                'nombre' => 'YouTube',
                'icono' => 'fab fa-youtube',
                'url_base' => 'https://youtube.com/@',
            ],
        ];

        foreach ($redes as $red) {
            RedSocial::firstOrCreate(
                ['nombre' => $red['nombre']],
                [
                    'icono' => $red['icono'],
                    'url_base' => $red['url_base'],
                ]
            );
        }
    }
}
