-- Consultas de observabilidad para la tabla ai_observability_logs.
-- Compatibles con SQLite salvo donde se indique lo contrario.

-- 1. Actividad reciente.
SELECT
    id,
    session_id,
    timestamp,
    was_blocked,
    ttft_ms,
    total_latency_ms,
    tokens_per_second,
    tools_executed
FROM ai_observability_logs
ORDER BY timestamp DESC
LIMIT 50;

-- 2. Resumen general.
SELECT
    COUNT(*) AS total_solicitudes,
    SUM(CASE WHEN was_blocked = 1 THEN 1 ELSE 0 END) AS solicitudes_bloqueadas,
    ROUND(AVG(ttft_ms), 2) AS ttft_promedio_ms,
    ROUND(AVG(total_latency_ms), 2) AS latencia_promedio_ms,
    ROUND(AVG(tokens_per_second), 2) AS tps_promedio
FROM ai_observability_logs;

-- 3. Percentiles aproximados no portables se omiten.
-- Distribución de latencia por rangos, portable y auditable.
SELECT
    CASE
        WHEN total_latency_ms IS NULL THEN 'sin_dato'
        WHEN total_latency_ms < 1000 THEN '<1s'
        WHEN total_latency_ms < 3000 THEN '1-3s'
        WHEN total_latency_ms < 10000 THEN '3-10s'
        ELSE '>=10s'
    END AS rango_latencia,
    COUNT(*) AS solicitudes
FROM ai_observability_logs
GROUP BY rango_latencia
ORDER BY MIN(COALESCE(total_latency_ms, -1));

-- 4. Solicitudes bloqueadas por guardrails.
SELECT
    id,
    session_id,
    timestamp,
    user_prompt,
    system_response,
    total_latency_ms
FROM ai_observability_logs
WHERE was_blocked = 1
ORDER BY timestamp DESC;

-- 5. Ejecuciones con métricas incompletas.
SELECT
    id,
    session_id,
    timestamp,
    ttft_ms,
    total_latency_ms,
    tokens_per_second,
    tools_executed
FROM ai_observability_logs
WHERE was_blocked = 0
  AND (
      ttft_ms IS NULL
      OR total_latency_ms IS NULL
      OR tokens_per_second IS NULL
      OR tools_executed IS NULL
  )
ORDER BY timestamp DESC;

-- 6. Conversaciones con más solicitudes.
SELECT
    session_id,
    COUNT(*) AS solicitudes,
    ROUND(AVG(total_latency_ms), 2) AS latencia_promedio_ms,
    MAX(timestamp) AS ultima_actividad
FROM ai_observability_logs
GROUP BY session_id
ORDER BY solicitudes DESC, ultima_actividad DESC;

-- 7. Estado de la herramienta almacenada como JSON (SQLite JSON1).
SELECT
    id,
    session_id,
    timestamp,
    json_extract(tools_executed, '$.name') AS herramienta,
    json_extract(tools_executed, '$.status') AS estado
FROM ai_observability_logs
WHERE tools_executed IS NOT NULL
ORDER BY timestamp DESC;

-- 8. Fallos de herramientas (SQLite JSON1).
SELECT
    id,
    session_id,
    timestamp,
    tools_executed
FROM ai_observability_logs
WHERE json_extract(tools_executed, '$.status') = 'ERROR'
ORDER BY timestamp DESC;

-- 9. Evidencia de una sesión concreta.
-- Sustituir :session_id por el identificador real.
SELECT *
FROM ai_observability_logs
WHERE session_id = :session_id
ORDER BY timestamp ASC;
