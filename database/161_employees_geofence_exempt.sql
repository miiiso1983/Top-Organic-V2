-- إضافة إعفاء الموظف من التحقق من الموقع الجغرافي (Geofence) عند تسجيل الحضور
ALTER TABLE employees
  ADD COLUMN geofence_exempt TINYINT(1) NOT NULL DEFAULT 0 AFTER attendance_type;
