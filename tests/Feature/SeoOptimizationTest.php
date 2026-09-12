<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SeoOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_contains_all_seo_tags_and_metadata(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Verification meta tag
        $response->assertSee('<meta name="google-site-verification" content="3zfiir-_Gwcyr_0ib9dhYTGhjAuQkaI1yjTE1PKG5oI"', false);

        // SEO Title & Description
        $response->assertSee('<title>TSO Market — The Settlers Online Market & Combat Simulator</title>', false);
        $response->assertSee('<meta name="description" content="TSO Market for The Settlers Online — market price history, price analysis and a smart combat simulator that optimizes attacks using your generals.">', false);

        // Robots & Canonical
        $response->assertSee('<meta name="robots" content="index, follow">', false);
        $response->assertSee('<link rel="canonical" href="https://tso-market.vercel.app/">', false);

        // Open Graph
        $response->assertSee('<meta property="og:type" content="website">', false);
        $response->assertSee('<meta property="og:title" content="TSO Market — The Settlers Online Market & Combat Simulator">', false);
        $response->assertSee('<meta property="og:description" content="Track market prices, analyze price history and optimize attacks with your generals in The Settlers Online.">', false);
        $response->assertSee('<meta property="og:url" content="https://tso-market.vercel.app/">', false);
        $response->assertSee('<meta property="og:site_name" content="TSO Market">', false);
        $response->assertSee('<meta property="og:image" content="https://tso-market.vercel.app/android-chrome-512x512.png">', false);

        // Twitter Card
        $response->assertSee('<meta name="twitter:card" content="summary">', false);
        $response->assertSee('<meta name="twitter:title" content="TSO Market — The Settlers Online Market & Combat Simulator">', false);

        // JSON-LD WebApplication
        $response->assertSee('https://schema.org', false);
        $response->assertSee('WebApplication', false);
        $response->assertSee('TSO Market', false);
        $response->assertSee('GameApplication', false);

        // Semantic initial crawlable content for non-JS bots
        $response->assertSee('<h1>TSO Market — The Settlers Online Market &amp; Combat Simulator</h1>', false);
        $response->assertSee('<h2>Market Price Tracker</h2>', false);
        $response->assertSee('<h2>Smart Combat Simulator</h2>', false);
        $response->assertSee('The smart combat simulator helps you build optimized attacks using your available generals', false);
    }

    public function test_admin_pages_contain_noindex_robots_directive(): void
    {
        // 1. Authenticated admin portal renders app.blade.php with noindex
        $response = $this->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        // 2. Unauthenticated login screen renders auth/login.blade.php with noindex
        auth()->forgetGuards();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $loginResponse = $this->get('/admin/login');
        $loginResponse->assertStatus(200);
        $loginResponse->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_robots_txt_file_is_served_properly(): void
    {
        $robotsContent = file_get_contents(public_path('robots.txt'));
        $this->assertIsString($robotsContent);

        $this->assertStringContainsString('User-agent: *', $robotsContent);
        $this->assertStringContainsString('Allow: /', $robotsContent);
        $this->assertStringContainsString('Sitemap: https://tso-market.vercel.app/sitemap.xml', $robotsContent);
    }

    public function test_sitemap_xml_file_is_valid_and_contains_canonical_url(): void
    {
        $sitemapContent = file_get_contents(public_path('sitemap.xml'));
        $this->assertIsString($sitemapContent);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $sitemapContent);
        $this->assertStringContainsString('<loc>https://tso-market.vercel.app/</loc>', $sitemapContent);

        // Validate XML format
        $xml = simplexml_load_string($sitemapContent);
        $this->assertNotFalse($xml, 'Sitemap should be valid XML');
        $this->assertEquals('https://tso-market.vercel.app/', (string) $xml->url->loc);
    }
}
