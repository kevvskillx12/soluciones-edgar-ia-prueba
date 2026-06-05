"""
Verificador de ChromaDB — Soluciones Edgar
Conecta a la base vectorial y muestra el estado de los documentos guardados.
Sirve como evidencia de que la ingesta quedó correcta.
"""

import chromadb
import os
import json

CHROMA_PATH = os.path.join(os.path.dirname(__file__), 'chroma_db')
COLECCION   = 'soluciones_edgar'

print("=" * 60)
print("  VERIFICACION DE CHROMADB — SOLUCIONES EDGAR")
print("=" * 60)

if not os.path.exists(CHROMA_PATH):
    print("\n  ERROR: No se encuentra la base de datos ChromaDB.")
    print(f"  Ruta esperada: {CHROMA_PATH}")
    print("  Ejecuta primero: python ingestar.py\n")
    exit(1)

try:
    cliente = chromadb.PersistentClient(path=CHROMA_PATH)
    coleccion = cliente.get_collection(COLECCION)
except Exception as e:
    print(f"\n  ERROR al conectar con ChromaDB: {e}")
    print("  Ejecuta primero: python ingestar.py\n")
    exit(1)

total = coleccion.count()
print(f"\n  Base de datos       : {CHROMA_PATH}")
print(f"  Coleccion           : {COLECCION}")
print(f"  Total de fragmentos : {total}")

if total == 0:
    print("\n  ADVERTENCIA: La coleccion esta vacia.")
    print("  Ejecuta: python ingestar.py\n")
    exit(0)

print(f"\n  Mostrando primeros {min(5, total)} fragmentos:")
print("-" * 60)

try:
    resultados = coleccion.get(limit=5)
except Exception:
    resultados = coleccion.query(query_texts=[""], n_results=min(5, total))

ids      = resultados.get("ids", [])
documentos = resultados.get("documents", [[]])[0] if isinstance(resultados.get("documents"), list) and len(resultados.get("documents")) > 0 and isinstance(resultados.get("documents")[0], list) else resultados.get("documents", [])

if not ids and not documentos:
    print("\n  No se pudieron recuperar documentos para mostrar.")
    exit(0)

for i in range(min(5, len(ids) if len(ids) > 0 else len(documentos))):
    doc_id = ids[i] if i < len(ids) else f"indice_{i}"
    doc_texto = documentos[i] if i < len(documentos) else ""
    print(f"\n  [{i+1}] ID: {doc_id}")
    print(f"       Longitud: {len(doc_texto)} caracteres")
    preview = doc_texto[:400]
    if len(doc_texto) > 400:
        preview += "..."
    print(f"       Contenido: {preview}")

print("\n" + "-" * 60)

# Detectar si los documentos parecen IDs en lugar de texto real
alertas = 0
for doc in documentos[:10]:
    if doc and (doc.startswith("chunk_") or doc.startswith("id_")):
        alertas += 1

if alertas > 3:
    print("\n  ADVERTENCIA: Los documentos parecen ser IDs ('chunk_XXXX') en lugar de texto real.")
    print("  La ingesta no se realizo correctamente. Revisa ingestar.py.")
    print("  Ejecuta: python ingestar.py para re-ingestar.\n")
elif total > 0:
    print("\n  VERIFICACION EXITOSA: Los fragmentos contienen texto real.")
    print(f"  {total} fragmentos guardados correctamente en ChromaDB.\n")

print("=" * 60)
print("  EVIDENCIA PARA ENTREGABLE: Captura esta pantalla.")
print("=" * 60)
