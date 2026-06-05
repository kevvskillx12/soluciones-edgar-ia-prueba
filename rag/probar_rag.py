"""
Script de pruebas rápidas RAG — Soluciones Edgar
Ejecuta varias preguntas contra rag_bridge.py y muestra si respondio correctamente.
"""

import subprocess
import json
import sys
import os

RAG_BRIDGE = os.path.join(os.path.dirname(__file__), "rag_bridge.py")
PYTHON     = sys.executable

PREGUNTAS = [
    "¿Qué servicios ofrece Soluciones Edgar?",
    "¿Cómo funciona el saldo del usuario?",
    "¿Cuáles son los estados de un pedido?",
    "¿Cómo se aprueba un depósito?",
    "¿Qué puede hacer el administrador?",
    "¿Cómo se descarga el resultado de un trámite?",
    "¿Por qué está justificado el uso de IA en el sistema?",
]

def probar_pregunta(pregunta):
    try:
        resultado = subprocess.run(
            [PYTHON, RAG_BRIDGE, pregunta],
            capture_output=True, text=True, timeout=130, encoding="utf-8"
        )
        salida = resultado.stdout.strip()

        if not salida:
            return {"estado": "ERROR", "detalle": "Sin salida del script"}

        datos = json.loads(salida)

        if datos.get("success") and datos.get("respuesta"):
            respuesta = datos["respuesta"]
            return {
                "estado": "OK",
                "respuesta": respuesta[:200] + "..." if len(respuesta) > 200 else respuesta
            }
        else:
            return {"estado": "ERROR", "detalle": datos.get("error", "Respuesta vacia")}

    except json.JSONDecodeError as e:
        return {"estado": "ERROR", "detalle": f"JSON invalido: {e}. Salida: {salida[:200] if 'salida' in dir() else 'N/A'}"}
    except subprocess.TimeoutExpired:
        return {"estado": "ERROR", "detalle": "Timeout (mas de 130s)"}
    except Exception as e:
        return {"estado": "ERROR", "detalle": str(e)}


def main():
    print("=" * 60)
    print("  PRUEBAS RAPIDAS DEL SISTEMA RAG")
    print("  Soluciones Edgar")
    print("=" * 60)

    if not os.path.exists(RAG_BRIDGE):
        print(f"\n  ERROR: No se encuentra {RAG_BRIDGE}")
        sys.exit(1)

    print(f"\n  Ejecutando {len(PREGUNTAS)} preguntas de prueba...\n")
    print("-" * 60)

    ok = 0
    fail = 0

    for i, pregunta in enumerate(PREGUNTAS, 1):
        print(f"  [{i}/{len(PREGUNTAS)}] {pregunta}")
        print(f"  {'-' * 40}")

        resultado = probar_pregunta(pregunta)

        if resultado["estado"] == "OK":
            ok += 1
            print(f"  RESULTADO: OK")
            print(f"  Respuesta: {resultado['respuesta']}")
        else:
            fail += 1
            print(f"  RESULTADO: FALLÓ")
            print(f"  Error: {resultado.get('detalle', 'Desconocido')}")

        print()

    print("-" * 60)
    print(f"  RESUMEN:")
    print(f"  Total preguntas: {len(PREGUNTAS)}")
    print(f"  Exitosas: {ok}")
    print(f"  Fallidas: {fail}")
    print(f"  Tasa de exito: {ok/len(PREGUNTAS)*100:.0f}%")
    print("-" * 60)

    if fail > 0:
        print("\n  NOTA: Algunas preguntas fallaron. Verifica:")
        print("  - Que Ollama este corriendo: ollama serve")
        print("  - Que el modelo este descargado: ollama pull llama3.2:1b")
        print("  - Que ChromaDB tenga datos: python verificar_chroma.py\n")


if __name__ == "__main__":
    main()
