-- Create table for Member Apps FAQs
CREATE TABLE IF NOT EXISTS `member_apps_faqs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert FAQ data
INSERT INTO `member_apps_faqs` (`question`, `answer`, `is_active`, `created_at`, `updated_at`) VALUES
('Do JUST-Points expire?', 'Every Just-Point you earn is valid for one full year from the end of the earning month. Example: Points earned on 13 January 2019 will remain valid until 31 January 2020 at 11:59 PM.', 1, NOW(), NOW()),
('How many times can I claim my birthday benefits?', 'The Birthday Treat may be redeemed once per brand and is valid only on the customer''s birthday date. The Birthday Week Discount may be used once at each outlet and is valid from the birthday date up to six (6) days after.', 1, NOW(), NOW()),
('What is JUST-REWARDS?', 'JUST-REWARDS is the exclusive loyalty program of The Justus Group, designed to give every visit more value. Members earn Just-Points with every transaction, unlock bonus points by completing challenges, and redeem their points for a variety of exciting rewards. Enjoy exclusive benefits from the moment you join—and unlock even greater privileges as you move up through the membership tiers.', 1, NOW(), NOW()),
('How much does it cost to become a JUST-REWARDS member?', 'JUST-REWARDS membership is free.', 1, NOW(), NOW()),
('Will I be given a membership card after joining JUST-REWARDS loyalty program?', 'The Just Rewards loyalty program is completely cardless, everything is managed through the Justus Group mobile app. Simply register, verify your mobile number, and you''re all set! When visiting any of our outlets, just provide your mobile number or member ID to our staff to enjoy your rewards.', 1, NOW(), NOW()),
('Where can I use this membership?', 'You can enjoy the benefits of your JUST REWARDS membership at all establishments under The Justus Group.', 1, NOW(), NOW()),
('What are my benefits as a JUST-REWARDS member?', 'Membership benefits differ based on the member''s level. All benefits can be accessed through: a. The Justus Group Mobile App by selecting Profile > Benefits. b. The Justus Group Website under the JUST-REWARDS section, where the complete list is displayed at the bottom of the page.', 1, NOW(), NOW()),
('How many levels of membership are there in JUST-REWARDS and what do I need to do to achieve the higher level?', 'Here are three membership tiers in JUST-REWARDS: Classic, Elite, and Royale. 1. Upon registration, members are automatically enrolled as Classic. 2. To achieve Elite status, members must spend a total of IDR 15,000,001 - IDR 40,000,000 within one calendar year. 3. To achieve Royale status, members must spend above IDR 40,000,001 within one calendar year. 4. Once the required spending threshold is met, the membership tier will be upgraded automatically. Spending is calculated on a cumulative basis within one year, and tier status may be downgraded if the total spending in the last 12 months does not meet the required amount.', 1, NOW(), NOW()),
('How do I check my transactions?', 'You can view your transaction history by logging into your JUST-REWARDS account on the Justus Group mobile app and following these steps: 1. Go to your "Profile". 2. Click on "History".', 1, NOW(), NOW()),
('My membership level has been downgraded. Why and how to maintain my membership level?', 'JUST-REWARDS membership levels are reviewed every 12 months from the member''s joining date. To maintain your current tier and continue enjoying its exclusive benefits, you must meet the annual spending requirements: Elite: IDR 15,000,000 - IDR 40,000,000 Royale: Above IDR 40,000,001 If your total spending over the past 12 months does not meet the required amount, your tier status may be downgraded.', 1, NOW(), NOW()),
('How to change my account password?', 'You can change your account password through the Justus Group mobile app by following these steps: 1. Go to Profile 2. Select Settings 3. Tap on Change Password', 1, NOW(), NOW()),
('What should I do if I forgot the password to my account?', 'You can reset your password by following these steps: 1. Open the login page on the Justus Group mobile app or website. 2. Select "Forgot Password". 3. Enter your registered mobile number, then click "Reset Password". 4. A reset link will be sent to your mobile number, allowing you to create a new password.', 1, NOW(), NOW()),
('Does my JUST-REWARDS account expire?', 'You are fully responsible for maintaining the confidentiality of your JUST-REWARDS membership information.', 1, NOW(), NOW()),
('Can someone else use my JUST-REWARDS account to earn JUST-Points?', 'Just-Points can be earned by providing your valid member ID or registered mobile number at the time of transaction.', 1, NOW(), NOW()),
('What should I do if my account can''t be used at the time of purchase?', 'If you experience any issues with your account during a purchase, our staff will assist you in completing a form to be submitted to our Customer Service. Alternatively, you may contact our Customer Service directly at +62 811-2180-880 (available Monday to Friday, 10:00 AM - 6:00 PM).', 1, NOW(), NOW()),
('What is JUST-Points?', 'Just-Points are the official reward currency of the JUST-REWARDS loyalty program, granted by Justus Group for every member purchase.', 1, NOW(), NOW()),
('How to earn JUST-Points?', 'Members earn 1 (one) Just-Point for every IDR 10,000 spent at all Justus Group establishments. Points are calculated based on the transaction subtotal before tax and service charges. In addition to purchases, members can also collect Just-Points by completing challenges. Accumulated Just-Points may be redeemed for exclusive rewards.', 1, NOW(), NOW());

