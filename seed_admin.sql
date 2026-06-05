-- Seed default admin user (password: admin123)
USE installment_business;

INSERT INTO branches (id, name, phone, status, created_at) VALUES
(1, 'Head Office', '0300-1234567', 1, CURDATE());

INSERT INTO users (username, password, full_name, role, branch_id, status, created_at) VALUES
('admin', 'admin123', 'Administrator', 'admin', 1, 1, CURDATE());
