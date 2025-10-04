-- Insert Purchase Requisition Categories Only
-- Simple script to insert categories into purchase_requisition_categories table

INSERT INTO purchase_requisition_categories (name, division, subcategory, budget_limit, description, created_at, updated_at) VALUES
-- MARKETING Categories
('Regular Marketing Expenses', 'MARKETING', 'Printing - Non Enhancement', 40000000.00, 'Flyer, baligho, Impraboard, Menu (once every 6 months), Coordination Fee Expenses, Advertising / Promotion (offline & online)', NOW(), NOW()),
('Marketing Enhancement Expenses', 'MARKETING', 'Menu Printing Enhancement', 40000000.00, 'Menu Printing or based on R&D Requirement', NOW(), NOW()),

-- MAINTENANCE Categories  
('Reserved PMT Expenses', 'MAINTENANCE', 'Preventive Maintenance', 30000000.00, 'Reserved budget for preventive maintenance tasks', NOW(), NOW()),
('Daily Regular Expenses', 'MAINTENANCE', 'Daily Operations', 60000000.00, 'Daily regular maintenance expenses', NOW(), NOW()),
('MT OE Spare Part', 'MAINTENANCE', 'Machine Tools Spare Parts', 5000000.00, 'Machine tools and operational equipment spare parts', NOW(), NOW()),
('IT PMT & Spare Part', 'MAINTENANCE', 'IT Equipment', 5000000.00, 'IT preventive maintenance and spare parts', NOW(), NOW()),

-- ASSET Categories
('Daily Regular Expenses', 'ASSET', 'Asset Operations', 30000000.00, 'Daily regular asset operational expenses', NOW(), NOW()),
('Operational Enhancement', 'ASSET', 'R&D Based Enhancement', 30000000.00, 'Operational enhancement based on R&D requirement', NOW(), NOW()),

-- PROJECT ENHANCEMENT Categories
('Project Enhancement', 'PROJECT_ENHANCEMENT', 'General Enhancement', 30000000.00, 'General project enhancement expenses', NOW(), NOW());
