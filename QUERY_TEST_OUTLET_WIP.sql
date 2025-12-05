-- Query untuk test item di Outlet WIP Production
-- Query ini digunakan di method index(), create(), dan edit()

-- Query dengan filter categories.show_pos = 0
SELECT 
    items.*,
    small_unit.name as small_unit_name,
    medium_unit.name as medium_unit_name,
    large_unit.name as large_unit_name
FROM items
LEFT JOIN units as small_unit ON items.small_unit_id = small_unit.id
LEFT JOIN units as medium_unit ON items.medium_unit_id = medium_unit.id
LEFT JOIN units as large_unit ON items.large_unit_id = large_unit.id
INNER JOIN categories ON items.category_id = categories.id
WHERE items.composition_type = 'composed'
  AND items.status = 'active'
  AND categories.show_pos = 0;

-- ============================================
-- QUERY UNTUK DEBUG - Cek data yang ada
-- ============================================

-- 1. Cek apakah ada items dengan composition_type = 'composed' dan status = 'active'
SELECT COUNT(*) as total_composed_active
FROM items
WHERE items.composition_type = 'composed'
  AND items.status = 'active';

-- 2. Cek apakah items punya category_id yang valid
SELECT 
    COUNT(*) as total_with_category,
    COUNT(DISTINCT items.category_id) as unique_categories
FROM items
WHERE items.composition_type = 'composed'
  AND items.status = 'active'
  AND items.category_id IS NOT NULL;

-- 3. Cek categories yang punya show_pos = 0
SELECT 
    id,
    name,
    type,
    show_pos
FROM categories
WHERE show_pos = 0;

-- 4. Cek items yang join dengan categories dan show_pos = 0
SELECT 
    items.id,
    items.name as item_name,
    items.composition_type,
    items.status,
    items.category_id,
    categories.name as category_name,
    categories.type as category_type,
    categories.show_pos
FROM items
INNER JOIN categories ON items.category_id = categories.id
WHERE items.composition_type = 'composed'
  AND items.status = 'active'
  AND categories.show_pos = 0
LIMIT 10;

-- 5. Cek semua items dengan category (tanpa filter show_pos)
SELECT 
    items.id,
    items.name as item_name,
    items.composition_type,
    items.status,
    items.category_id,
    categories.name as category_name,
    categories.type as category_type,
    categories.show_pos
FROM items
INNER JOIN categories ON items.category_id = categories.id
WHERE items.composition_type = 'composed'
  AND items.status = 'active'
LIMIT 10;

-- 6. Cek apakah ada items yang category_id = NULL
SELECT 
    COUNT(*) as total_null_category
FROM items
WHERE items.composition_type = 'composed'
  AND items.status = 'active'
  AND items.category_id IS NULL;

-- ============================================
-- QUERY ALTERNATIF - Jika perlu LEFT JOIN
-- ============================================

-- Jika ingin include items yang category_id = NULL (dengan LEFT JOIN)
SELECT 
    items.*,
    small_unit.name as small_unit_name,
    medium_unit.name as medium_unit_name,
    large_unit.name as large_unit_name
FROM items
LEFT JOIN units as small_unit ON items.small_unit_id = small_unit.id
LEFT JOIN units as medium_unit ON items.medium_unit_id = medium_unit.id
LEFT JOIN units as large_unit ON items.large_unit_id = large_unit.id
LEFT JOIN categories ON items.category_id = categories.id
WHERE items.composition_type = 'composed'
  AND items.status = 'active'
  AND (categories.show_pos = 0 OR categories.show_pos IS NULL);

