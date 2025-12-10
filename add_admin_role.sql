-- Migration: add optional admin_role column to users to support role-based widgets.
-- Run this once (after backing up your DB).
ALTER TABLE `users`
  ADD COLUMN `admin_role` VARCHAR(50) DEFAULT NULL;

-- Example: set admin_role for existing admin users (customize usernames as needed)
UPDATE `users` SET admin_role = 'superadmin' WHERE username = 'brock';
-- Set other admins to manager or analyst as desired:
-- UPDATE `users` SET admin_role = 'manager' WHERE username = 'alice';
-- UPDATE `users` SET admin_role = 'analyst' WHERE username = 'bob';