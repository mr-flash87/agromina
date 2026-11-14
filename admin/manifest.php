<?php
/**
 * Manifeste de l'application installable « AgroMina Admin ».
 * Généré en PHP pour suivre l'adresse du site (domaine ou sous-dossier).
 * Public : le navigateur le télécharge sans être connecté.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$admin = url('admin/');
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode([
    'id' => $admin,
    'name' => 'AgroMina Admin',
    'short_name' => 'AgroMina Admin',
    'description' => 'Gestion de la boutique AgroMina : commandes, produits, clients et équipe.',
    'lang' => 'fr',
    'start_url' => $admin,
    'scope' => url(''),
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#ffffff',
    'theme_color' => '#126333',
    'icons' => [
        ['src' => img('admin-icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => img('admin-icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => img('admin-icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => 'Commandes', 'url' => url('admin/commandes'), 'icons' => [['src' => img('admin-icon-192.png'), 'sizes' => '192x192']]],
        ['name' => 'Produits', 'url' => url('admin/produits'), 'icons' => [['src' => img('admin-icon-192.png'), 'sizes' => '192x192']]],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
