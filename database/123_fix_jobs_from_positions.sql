-- Populate missing jobs from employee positions and fix broken job_id references
-- Safe to re-run: inserts only missing rows and updates only broken/null references.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- Insert distinct employee positions that are not already in jobs table
INSERT INTO jobs (title, department_id, code, is_active)
SELECT DISTINCT e.position, e.department_id, NULL, 1
FROM employees e
LEFT JOIN jobs j
  ON j.title = e.position
  AND (j.department_id = e.department_id OR (j.department_id IS NULL AND e.department_id IS NULL))
WHERE e.position IS NOT NULL AND e.position != '' AND j.id IS NULL;

-- Update employees whose job_id is null or points to a missing job
UPDATE employees e
JOIN jobs j
  ON j.title = e.position
  AND (j.department_id = e.department_id OR (j.department_id IS NULL AND e.department_id IS NULL))
SET e.job_id = j.id
WHERE e.job_id IS NULL OR e.job_id NOT IN (SELECT id FROM jobs);

SET FOREIGN_KEY_CHECKS=1;
