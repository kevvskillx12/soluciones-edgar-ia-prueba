#!/usr/bin/env python
"""
Seeder de estrés para Semana 07.

Puebla SQLite con registros ficticios realistas usando transacciones por lote.
Uso:
  python scripts/seed_stress_data.py --records 10000
  python scripts/seed_stress_data.py --records 50000
"""

from __future__ import annotations

import argparse
import random
import sqlite3
import string
import time
from datetime import UTC, datetime, timedelta
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DB = ROOT / "database" / "database.sqlite"


SERVICES = [
    "CURP Actualizada",
    "Acta de Nacimiento",
    "CSF con RFC y IDCIF",
    "Localizar NSS con CURP",
    "Constancia fiscal",
    "Antecedentes no Penales",
]

STATUSES = ["pending", "processing", "completed", "rejected"]
NAMES = ["Kevin", "Luis", "Alejandra", "María", "Carlos", "Ana", "Edgar", "Sofía"]
LASTNAMES = ["Montero", "Ek", "Hernández", "García", "López", "Pérez", "Ramírez", "Cruz"]
STATES = ["YN", "DF", "MC", "JC", "NL", "PL", "QR", "BC"]


def fake_curp(index: int) -> str:
    letters = "".join(random.choice(string.ascii_uppercase) for _ in range(4))
    date = f"{random.randint(70, 99):02d}{random.randint(1, 12):02d}{random.randint(1, 28):02d}"
    sex = random.choice(["H", "M"])
    state = random.choice(STATES)
    cons = "".join(random.choice(string.ascii_uppercase) for _ in range(3))
    suffix = f"{index % 100:02d}"
    return f"{letters}{date}{sex}{state}{cons}{suffix}"


def ensure_schema(conn: sqlite3.Connection) -> None:
    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS ai_stress_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            external_id TEXT NOT NULL UNIQUE,
            customer_name TEXT NOT NULL,
            customer_email TEXT NOT NULL,
            service_name TEXT NOT NULL,
            status TEXT NOT NULL,
            curp TEXT NOT NULL,
            scheduled_at TEXT NOT NULL,
            notes TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
        """
    )
    conn.execute("CREATE INDEX IF NOT EXISTS idx_ai_stress_service_status ON ai_stress_records(service_name, status)")
    conn.execute("CREATE INDEX IF NOT EXISTS idx_ai_stress_curp ON ai_stress_records(curp)")
    conn.execute("CREATE INDEX IF NOT EXISTS idx_ai_stress_scheduled ON ai_stress_records(scheduled_at)")


def build_rows(start: int, count: int) -> list[tuple[str, str, str, str, str, str, str, str, str, str]]:
    now = datetime.now(UTC).replace(tzinfo=None)
    rows = []

    for offset in range(count):
        index = start + offset
        first = random.choice(NAMES)
        last = random.choice(LASTNAMES)
        second_last = random.choice(LASTNAMES)
        name = f"{first} {last} {second_last}"
        service = random.choice(SERVICES)
        status = random.choice(STATUSES)
        scheduled = now + timedelta(minutes=index % 120000)
        email = f"cliente{index:05d}@example.test"
        curp = fake_curp(index)
        external_id = f"AI-STRESS-{index:06d}"
        notes = f"Registro sintetico para {service} de {name}."
        timestamp = now.isoformat(timespec="seconds")

        rows.append((external_id, name, email, service, status, curp, scheduled.isoformat(timespec="seconds"), notes, timestamp, timestamp))

    return rows


def seed(db_path: Path, records: int, batch_size: int) -> dict[str, float | int | str]:
    db_path.parent.mkdir(parents=True, exist_ok=True)
    started = time.perf_counter()

    with sqlite3.connect(db_path) as conn:
        ensure_schema(conn)
        existing = conn.execute("SELECT COUNT(*) FROM ai_stress_records").fetchone()[0]
        remaining = max(0, records - existing)
        inserted = 0

        for start in range(existing + 1, records + 1, batch_size):
            count = min(batch_size, records - start + 1)
            rows = build_rows(start, count)
            with conn:
                conn.executemany(
                    """
                    INSERT OR IGNORE INTO ai_stress_records (
                        external_id, customer_name, customer_email, service_name, status,
                        curp, scheduled_at, notes, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """,
                    rows,
                )
            inserted += count

        total = conn.execute("SELECT COUNT(*) FROM ai_stress_records").fetchone()[0]

    elapsed = time.perf_counter() - started
    return {
        "database": str(db_path),
        "requested_records": records,
        "previous_records": existing,
        "inserted_attempted": inserted if remaining else 0,
        "total_records": total,
        "elapsed_seconds": round(elapsed, 3),
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--database", default=str(DEFAULT_DB), help="Ruta al SQLite del proyecto.")
    parser.add_argument("--records", type=int, default=10000, help="Cantidad mínima deseada de registros.")
    parser.add_argument("--batch-size", type=int, default=1000, help="Tamaño de lote para executemany.")
    args = parser.parse_args()

    result = seed(Path(args.database), args.records, args.batch_size)
    for key, value in result.items():
        print(f"{key}: {value}")


if __name__ == "__main__":
    main()
