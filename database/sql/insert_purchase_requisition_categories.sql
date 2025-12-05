-- Insert Purchase Requisition Categories
-- This script inserts the default categories for Purchase Requisition Ops system

-- Insert Purchase Requisition Categories
INSERT INTO purchase_requisition_categories (name, division, subcategory, budget_limit, description, created_at, updated_at) VALUES
-- MARKETING Categories (Total: Rp. 80,000,000)
('Regular Marketing Expenses', 'MARKETING', 'Printing - Non Enhancement', 40000000.00, 'Flyer, baligho, Impraboard, Menu (once every 6 months), Coordination Fee Expenses, Advertising / Promotion (offline & online)', NOW(), NOW()),
('Marketing Enhancement Expenses', 'MARKETING', 'Menu Printing Enhancement', 40000000.00, 'Menu Printing or based on R&D Requirement', NOW(), NOW()),

-- MAINTENANCE Categories (Total: Rp. 100,000,000)
('Reserved PMT Expenses', 'MAINTENANCE', 'Preventive Maintenance', 30000000.00, 'Reserved budget for preventive maintenance tasks', NOW(), NOW()),
('Daily Regular Expenses', 'MAINTENANCE', 'Daily Operations', 60000000.00, 'Daily regular maintenance expenses', NOW(), NOW()),
('MT OE Spare Part', 'MAINTENANCE', 'Machine Tools Spare Parts', 5000000.00, 'Machine tools and operational equipment spare parts', NOW(), NOW()),
('IT PMT & Spare Part', 'MAINTENANCE', 'IT Equipment', 5000000.00, 'IT preventive maintenance and spare parts', NOW(), NOW()),

-- ASSET Categories (Total: Rp. 60,000,000)
('Daily Regular Expenses', 'ASSET', 'Asset Operations', 30000000.00, 'Daily regular asset operational expenses', NOW(), NOW()),
('Operational Enhancement', 'ASSET', 'R&D Based Enhancement', 30000000.00, 'Operational enhancement based on R&D requirement', NOW(), NOW()),

-- PROJECT ENHANCEMENT Categories (Total: Rp. 30,000,000)
('Project Enhancement', 'PROJECT_ENHANCEMENT', 'General Enhancement', 30000000.00, 'General project enhancement expenses', NOW(), NOW());

-- Insert Division Budgets for Current Year (2025)
INSERT INTO division_budgets (division, year, total_budget, used_budget, remaining_budget, created_at, updated_at) VALUES
('MARKETING', 2025, 80000000.00, 0.00, 80000000.00, NOW(), NOW()),
('MAINTENANCE', 2025, 100000000.00, 0.00, 100000000.00, NOW(), NOW()),
('ASSET', 2025, 60000000.00, 0.00, 60000000.00, NOW(), NOW()),
('PROJECT_ENHANCEMENT', 2025, 30000000.00, 0.00, 30000000.00, NOW(), NOW());
