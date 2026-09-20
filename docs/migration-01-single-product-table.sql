-- ============================================================
-- Spajanje product_variants / product_images / product_players
-- u jednu `products` tablicu.
--
-- Pokrenuti RUČNO u Supabase SQL Editoru. Podaci se čuvaju:
-- varijante se agregiraju u products.sizes, slike u products.images.
--
-- VAŽNO: nakon ovog SQL-a stara verzija aplikacije više ne radi.
-- Pokreni ga neposredno prije deploya nove verzije.
-- ============================================================

BEGIN;

-- ------------------------------------------------------------
-- 1. products: sizes i images kao jsonb nizovi
-- ------------------------------------------------------------
ALTER TABLE products
    ADD COLUMN sizes  jsonb NOT NULL DEFAULT '[]'::jsonb,
    ADD COLUMN images jsonb NOT NULL DEFAULT '[]'::jsonb;

-- Veličine iz varijanti (zaliha se namjerno odbacuje — sve je uvijek dostupno).
UPDATE products p
SET sizes = COALESCE((
    SELECT jsonb_agg(v.size ORDER BY v.id)
    FROM product_variants v
    WHERE v.product_id = p.id
), '[]'::jsonb);

-- Slike: primarna prva, ostale po sort_order.
UPDATE products p
SET images = COALESCE((
    SELECT jsonb_agg(i.url ORDER BY i.is_primary DESC, i.sort_order, i.id)
    FROM product_images i
    WHERE i.product_id = p.id
), '[]'::jsonb);

-- ------------------------------------------------------------
-- 2. products: category f1 -> formula, audience -> type
-- ------------------------------------------------------------
ALTER TYPE product_category RENAME VALUE 'f1' TO 'formula';

CREATE TYPE product_type AS ENUM ('kids', 'adult');

ALTER TABLE products ADD COLUMN type product_type NOT NULL DEFAULT 'adult';

-- men/women/unisex se svi spajaju u 'adult'.
UPDATE products
SET type = CASE WHEN audience = 'kids' THEN 'kids'::product_type
                ELSE 'adult'::product_type END;

ALTER TABLE products
    DROP COLUMN audience,
    DROP COLUMN kit_type,
    DROP COLUMN personalization;

-- ------------------------------------------------------------
-- 3. order_items: veza ide na proizvod, veličina je snapshot
-- ------------------------------------------------------------
ALTER TABLE order_items
    ADD COLUMN product_id bigint REFERENCES products(id),
    ADD COLUMN size       varchar(10);

UPDATE order_items oi
SET product_id = v.product_id,
    size       = v.size
FROM product_variants v
WHERE v.id = oi.product_variant_id;

-- Ime igrača s gotove liste postaje običan upisani tekst.
UPDATE order_items oi
SET custom_player_name   = pp.player_name,
    custom_player_number = COALESCE(oi.custom_player_number, pp.player_number)
FROM product_players pp
WHERE pp.id = oi.product_player_id
  AND oi.custom_player_name IS NULL;

ALTER TABLE order_items
    ALTER COLUMN product_id SET NOT NULL,
    ALTER COLUMN size       SET NOT NULL;

ALTER TABLE order_items DROP CONSTRAINT IF EXISTS chk_single_personalization;

ALTER TABLE order_items
    DROP COLUMN product_player_id,
    DROP COLUMN product_variant_id;

CREATE INDEX idx_order_items_product_id ON order_items (product_id);

-- ------------------------------------------------------------
-- 4. Stare tablice i tipovi
-- ------------------------------------------------------------
DROP TABLE product_players;
DROP TABLE product_variants;
DROP TABLE product_images;

DROP TYPE kit_type;
DROP TYPE audience_type;
DROP TYPE personalization_type;

-- ------------------------------------------------------------
-- 5. Knjigovodstvo migracija (Laravel ne smije ovo ponavljati)
-- ------------------------------------------------------------
INSERT INTO migrations (migration, batch)
VALUES ('2026_09_20_000000_merge_product_tables',
        (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations));

COMMIT;
