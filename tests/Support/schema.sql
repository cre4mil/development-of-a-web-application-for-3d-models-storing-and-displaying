-- SQLite schema mirroring database/schema.sql (only the columns the application uses).
CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password TEXT NOT NULL,
  is_admin INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE models (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  title TEXT NOT NULL,
  filename TEXT NOT NULL,
  file_gltf TEXT, file_glb TEXT, file_usdz TEXT, file_obj TEXT,
  thumb TEXT NOT NULL DEFAULT '',
  description TEXT,
  is_public INTEGER NOT NULL DEFAULT 1,
  license TEXT NOT NULL DEFAULT 'CC BY',
  price REAL NOT NULL DEFAULT 0,
  view_count INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE);
CREATE TABLE model_tags (model_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, PRIMARY KEY (model_id, tag_id));
CREATE TABLE likes (user_id INTEGER NOT NULL, model_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, model_id));
CREATE TABLE collections (user_id INTEGER NOT NULL, model_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, model_id));
CREATE TABLE follows (follower_id INTEGER NOT NULL, followee_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (follower_id, followee_id));
CREATE TABLE comments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  model_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  body TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_ref TEXT NOT NULL UNIQUE,
  buyer_id INTEGER NOT NULL,
  total_amount REAL NOT NULL DEFAULT 0,
  payment_slip_url TEXT,
  payment_status TEXT NOT NULL DEFAULT 'pending',
  admin_note TEXT,
  admin_approved_by INTEGER,
  approved_at TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL,
  model_id INTEGER NOT NULL,
  creator_id INTEGER NOT NULL,
  price REAL NOT NULL DEFAULT 0,
  platform_fee REAL NOT NULL DEFAULT 0,
  creator_earning REAL NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE creator_wallets (
  creator_id INTEGER PRIMARY KEY,
  available_balance REAL NOT NULL DEFAULT 0,
  total_earned REAL NOT NULL DEFAULT 0,
  pending_payout REAL NOT NULL DEFAULT 0,
  bank_name TEXT, bank_account_no TEXT, bank_account_name TEXT
);
CREATE TABLE payout_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  creator_id INTEGER NOT NULL,
  amount REAL NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  admin_transfer_slip TEXT,
  admin_note TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  transferred_at TEXT,
  admin_id INTEGER
);
CREATE TABLE platform_settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_by INTEGER
);
