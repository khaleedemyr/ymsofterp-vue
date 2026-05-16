-- Mode harga: manual (default) atau auto (dari Food Good Receive terakhir + markup di backend)
ALTER TABLE item_prices
ADD COLUMN pricing_mode VARCHAR(16) NOT NULL DEFAULT 'manual';
