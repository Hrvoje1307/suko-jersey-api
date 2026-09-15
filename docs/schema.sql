-- ============================================
-- Webshop database schema (Postgres / Supabase)
-- ============================================

-- ENUM types
CREATE TYPE product_category AS ENUM ('football', 'f1', 'basketball');
CREATE TYPE product_status AS ENUM ('active', 'sold_out', 'draft');
CREATE TYPE kit_type AS ENUM ('home', 'away', 'third', 'fourth');
CREATE TYPE audience_type AS ENUM ('kids', 'men', 'women', 'unisex');
CREATE TYPE personalization_type AS ENUM ('none', 'preset_only', 'custom_text', 'both');
CREATE TYPE order_status AS ENUM (
    'ordered',           -- kupac naručio
    'sent_to_supplier',  -- ti naručio kod dobavljača
    'arrived_hr',        -- paket stigao u Hrvatsku
    'shipped',           -- poslano kupcu (pravi tracking postoji od ovdje)
    'delivered'          -- dostavljeno
);

-- ============================================
-- PRODUCTS
-- ============================================
CREATE TABLE products (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    club_or_team    VARCHAR(255) NOT NULL,
    category        product_category NOT NULL,
    season          VARCHAR(50),              -- npr. "2026/27", "stara sezona"
    kit_type        kit_type NOT NULL DEFAULT 'home',   -- domaći/gostujući/treći/četvrti (nogomet/košarka)
    audience        audience_type NOT NULL DEFAULT 'unisex', -- dječji/muški/ženski/unisex
    personalization personalization_type NOT NULL DEFAULT 'none', -- može li kupac dodati ime/broj igrača ili vozača
    model_3d_url    VARCHAR(500),             -- link na Blender/GLB model za 360 prikaz (nullable dok se ne doda)
    price           NUMERIC(10, 2) NOT NULL,
    description     TEXT,
    status          product_status NOT NULL DEFAULT 'draft',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_products_club ON products(club_or_team);
CREATE INDEX idx_products_kit_type ON products(kit_type);
CREATE INDEX idx_products_audience ON products(audience);

-- ============================================
-- PRODUCT IMAGES
-- ============================================
CREATE TABLE product_images (
    id              BIGSERIAL PRIMARY KEY,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    url             VARCHAR(500) NOT NULL,
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    is_primary      BOOLEAN NOT NULL DEFAULT false,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_product_images_product_id ON product_images(product_id);

-- ============================================
-- PRODUCT VARIANTS (veličine + zaliha)
-- ============================================
CREATE TABLE product_variants (
    id              BIGSERIAL PRIMARY KEY,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    size            VARCHAR(10) NOT NULL,      -- S, M, L, XL, XXL...
    stock_quantity  INTEGER NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (product_id, size)
);

CREATE INDEX idx_product_variants_product_id ON product_variants(product_id);

-- ============================================
-- PRODUCT PLAYERS (gotova lista imena/brojeva koje dobavljač već ima,
-- relevantno kad je products.personalization = 'preset_only' ili 'both')
-- ============================================
CREATE TABLE product_players (
    id              BIGSERIAL PRIMARY KEY,
    product_id      BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    player_name     VARCHAR(255) NOT NULL,     -- npr. "Modrić", "Verstappen", "James"
    player_number   VARCHAR(10),                -- npr. "10", nullable (F1 vozači obično nemaju broj na dresu)
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_product_players_product_id ON product_players(product_id);

-- ============================================
-- CUSTOMERS
-- ============================================
CREATE TABLE customers (
    id              BIGSERIAL PRIMARY KEY,
    email           VARCHAR(255) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    phone           VARCHAR(50),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX idx_customers_email ON customers(email);

-- ============================================
-- ORDERS
-- ============================================
CREATE TABLE orders (
    id                          BIGSERIAL PRIMARY KEY,
    customer_id                 BIGINT NOT NULL REFERENCES customers(id),
    status                      order_status NOT NULL DEFAULT 'ordered',
    shipping_address_line1      VARCHAR(255) NOT NULL,
    shipping_address_line2      VARCHAR(255),
    shipping_city                VARCHAR(120) NOT NULL,
    shipping_postal_code        VARCHAR(20) NOT NULL,
    shipping_country            VARCHAR(2) NOT NULL,   -- ISO 3166-1 alpha-2
    total_price                 NUMERIC(10, 2) NOT NULL,
    tracking_number_internal    VARCHAR(100),           -- HR -> kupac tracking (samo kad status = shipped)
    order_reference             VARCHAR(20) NOT NULL,   -- javni kod za kupca da provjeri status, npr. "ORD-8F3K2"
    created_at                  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX idx_orders_reference ON orders(order_reference);
CREATE INDEX idx_orders_customer_id ON orders(customer_id);
CREATE INDEX idx_orders_status ON orders(status);

-- ============================================
-- ORDER ITEMS
-- ============================================
CREATE TABLE order_items (
    id                      BIGSERIAL PRIMARY KEY,
    order_id                BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_variant_id      BIGINT NOT NULL REFERENCES product_variants(id),
    quantity                INTEGER NOT NULL CHECK (quantity > 0),
    price_at_purchase       NUMERIC(10, 2) NOT NULL,
    product_player_id       BIGINT REFERENCES product_players(id), -- odabran gotov igrač/vozač s liste (nullable)
    custom_player_name      VARCHAR(255),          -- slobodan upis imena ako je personalization = 'custom_text'/'both'
    custom_player_number    VARCHAR(10),           -- slobodan upis broja
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_single_personalization CHECK (
        NOT (product_player_id IS NOT NULL AND custom_player_name IS NOT NULL)
    )
);

CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_variant_id ON order_items(product_variant_id);
CREATE INDEX idx_order_items_product_player_id ON order_items(product_player_id);

-- ============================================
-- ADMIN USERS (Laravel Sanctum)
-- ============================================
CREATE TABLE admin_users (
    id              BIGSERIAL PRIMARY KEY,
    email           VARCHAR(255) NOT NULL,
    password        VARCHAR(255) NOT NULL,   -- bcrypt hash
    name            VARCHAR(255),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX idx_admin_users_email ON admin_users(email);

-- Note: Laravel Sanctum's personal_access_tokens table is created
-- automatically by its own migration (php artisan install:api or
-- vendor:publish sanctum-migrations) — no need to hand-write it here.
