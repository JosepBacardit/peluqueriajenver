<?php

namespace App\Http\Controllers;

class SitemapController extends Controller
{
    public function index()
    {
        $urls = [
            ['url' => url('/'), 'priority' => 1.0, 'changefreq' => 'weekly'],
            ['url' => route('color-mechas'), 'priority' => 0.9, 'changefreq' => 'monthly'],
            ['url' => route('corte-tratamientos'), 'priority' => 0.9, 'changefreq' => 'monthly'],
            ['url' => route('peinados-eventos'), 'priority' => 0.9, 'changefreq' => 'monthly'],
            ['url' => route('belleza-estetica'), 'priority' => 0.8, 'changefreq' => 'monthly'],
            ['url' => route('privacidad'), 'priority' => 0.5, 'changefreq' => 'yearly'],
            ['url' => route('avisos-legales'), 'priority' => 0.5, 'changefreq' => 'yearly'],
            ['url' => route('cookies'), 'priority' => 0.5, 'changefreq' => 'yearly'],
        ];

        return response()->view('sitemap', ['urls' => $urls], 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
