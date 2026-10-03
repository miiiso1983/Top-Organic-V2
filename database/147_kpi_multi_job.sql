-- =====================================================
-- 147: ربط مؤشرات الأداء بعدة وظائف (علاقة متعددة إلى متعددة)
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS performance_kpi_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kpi_id INT NOT NULL,
    job_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_kpi_job (kpi_id, job_id),
    CONSTRAINT fk_pkj_kpi FOREIGN KEY (kpi_id) REFERENCES performance_kpis(id) ON DELETE CASCADE,
    CONSTRAINT fk_pkj_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO performance_kpi_jobs (kpi_id, job_id)
SELECT id, job_id FROM performance_kpis WHERE job_id IS NOT NULL;
