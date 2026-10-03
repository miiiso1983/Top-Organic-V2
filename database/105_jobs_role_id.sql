-- ربط الدور/الصلاحيات بالوظيفة
ALTER TABLE jobs ADD COLUMN role_id INT DEFAULT NULL AFTER department_id;
ALTER TABLE jobs ADD CONSTRAINT fk_jobs_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;
