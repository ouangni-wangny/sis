<?php

namespace App\Support;

final class PdfBrand
{
    /**
     * Données de marque partagées par tous les PDF Dompdf.
     *
     * @return array{
     *   company_name: string,
     *   company_legal: string,
     *   tagline: string,
     *   logo_src: string|null,
     *   colors: array<string, string>
     * }
     */
    public static function data(): array
    {
        $logoPath = public_path('logo.png');
        if (! is_file($logoPath)) {
            $logoPath = public_path('logo.jpeg');
        }

        $logoSrc = null;
        if (is_file($logoPath)) {
            $mime = str_ends_with(strtolower($logoPath), '.png') ? 'png' : 'jpeg';
            $logoSrc = 'data:image/'.$mime.';base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        return [
            'company_name' => (string) config('sis.company.name', 'Société Ivoirienne de Sécurité'),
            'company_legal' => (string) config(
                'sis.company.legal',
                '08 BP 902 ABIDJAN 08 SARL au capital de 5 millions de francs CFA RC N° 20.681, CC: 7407073 P Compte BNI N° 027154730005'
            ),
            'tagline' => 'Opérations · Gardiennage · Sécurité',
            'logo_src' => $logoSrc,
            'colors' => [
                'teal' => '#2f3a24',
                'teal_dark' => '#1e2714',
                'teal_light' => '#4a5c38',
                'accent' => '#311f38',
                'ink' => '#141a10',
                'ink_muted' => '#4a5340',
                'ink_faint' => '#7a8470',
                'paper' => '#f4f5f2',
                'paper_muted' => '#e8eae4',
                'border' => '#d2d6cc',
                'white' => '#ffffff',
            ],
        ];
    }
}
