-- Promotion de départ : reprend la bande « Offres spéciales » de l'accueil (prix barrés déjà saisis sur les produits)
INSERT INTO promotions (name, badge, title, description, discount_percent, is_active, show_on_home)
SELECT 'Offres de la semaine', 'OFFRES SPÉCIALES', 'Jusqu''à −20 % sur une sélection du terroir', 'Fonio, bouye, mangue séchée… profitez de nos promotions de la semaine.', 0, 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM promotions);

INSERT IGNORE INTO promotion_products (promotion_id, product_id, position)
SELECT (SELECT MIN(id) FROM promotions), id, 0 FROM products WHERE old_price IS NOT NULL AND is_active = 1
