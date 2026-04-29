-- Block 1: warehouse picker workplace metadata without breaking old flow.
ALTER TABLE `b24_sale_requests`
  ADD COLUMN `picker_status` enum('new','picked','confirmed','shipped','cancelled') NOT NULL DEFAULT 'new' AFTER `status`,
  ADD COLUMN `picker_problem_text` text DEFAULT NULL AFTER `picker_status`,
  ADD COLUMN `picker_meta_json` text DEFAULT NULL AFTER `picker_problem_text`,
  ADD COLUMN `picked_at` datetime DEFAULT NULL AFTER `picker_meta_json`,
  ADD COLUMN `confirmed_at` datetime DEFAULT NULL AFTER `picked_at`,
  ADD COLUMN `shipped_at` datetime DEFAULT NULL AFTER `confirmed_at`,
  ADD COLUMN `cancelled_at` datetime DEFAULT NULL AFTER `shipped_at`,
  ADD KEY `idx_b24_sale_requests_picker_status` (`picker_status`);
