-- ============================================
-- Webshop database schema (Postgres / Supabase)
-- ============================================

-- ENUM types
CREATE TYPE product_category AS ENUM ('football', 'basketball', 'formula');
CREATE TYPE product_status AS ENUM ('active', 'sold_out', 'draft');
CREATE TYPE product_type AS ENUM ('kids', 'adult');
-- Naplata je zasebna os od order_status: narudžba nastaje 'unpaid' i tek
-- Stripe webhook je prebaci u 'paid'.
CREATE TYPE payment_status AS ENUM ('unpaid', 'paid', 'failed', 'refunded');
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
    type            product_type NOT NULL DEFAULT 'adult',   -- dječji ili za odrasle
    season          VARCHAR(50),              -- npr. "2026/27", "stara sezona"
    price           NUMERIC(10, 2) NOT NULL,
    description     TEXT,
    -- Veličine koje dres nudi, npr. ["S","M","L"] ili dječje ["128","140"].
    -- Zalihe nema: sve navedeno je uvijek dostupno, nedostupno se makne iz niza.
    sizes           JSONB NOT NULL DEFAULT '[]'::jsonb,
    -- Vanjski URL-ovi slika; redoslijed je izvor istine, prva je primarna.
    images          JSONB NOT NULL DEFAULT '[]'::jsonb,
    model_3d_url    VARCHAR(500),             -- link na Blender/GLB model za 360 prikaz
    status          product_status NOT NULL DEFAULT 'draft',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_products_club ON products(club_or_team);
CREATE INDEX idx_products_type ON products(type);

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
    payment_status              payment_status NOT NULL DEFAULT 'unpaid',
    stripe_checkout_session_id  VARCHAR(255),           -- cs_..., veza za checkout.session.* evente
    stripe_payment_intent_id    VARCHAR(255),           -- pi_..., upisuje se kad plaćanje prođe
    created_at                  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX idx_orders_reference ON orders(order_reference);
CREATE INDEX idx_orders_customer_id ON orders(customer_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_payment_status ON orders(payment_status);
-- UNIQUE je siguran: Postgres dopušta više NULL-ova u unique indeksu.
CREATE UNIQUE INDEX idx_orders_stripe_checkout_session_id ON orders(stripe_checkout_session_id);
CREATE INDEX idx_orders_stripe_payment_intent_id ON orders(stripe_payment_intent_id);

-- ============================================
-- ORDER ITEMS
-- ============================================
CREATE TABLE order_items (
    id                      BIGSERIAL PRIMARY KEY,
    order_id                BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id              BIGINT NOT NULL REFERENCES products(id),
    -- Snapshot: proizvod smije kasnije prestati nuditi ovu veličinu.
    size                    VARCHAR(10) NOT NULL,
    quantity                INTEGER NOT NULL CHECK (quantity > 0),
    price_at_purchase       NUMERIC(10, 2) NOT NULL,
    -- Tisak na dresu, slobodan upis kupca. Liste igrača backend ne poznaje —
    -- frontend ih vuče s vanjskog API-ja.
    custom_player_name      VARCHAR(255),
    custom_player_number    VARCHAR(10),
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);

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
