"""
Script de Ingesta — Sistema RAG Soluciones Edgar
Paso 1: Lee el documento, lo fragmenta y lo guarda en ChromaDB
"""

import chromadb
import os
import re

# =============================================
# CONFIGURACION
# =============================================
DOCUMENTO_PATH = os.path.join(os.path.dirname(__file__), '..', 'conocimiento_soluciones_edgar.txt')
CHROMA_PATH    = os.path.join(os.path.dirname(__file__), 'chroma_db')
COLECCION      = 'soluciones_edgar'
CHUNK_SIZE     = 1200   # caracteres por fragmento
CHUNK_OVERLAP  = 200    # solapamiento entre fragmentos

# =============================================
# PASO 1: LEER DOCUMENTO
# =============================================
print("=" * 55)
print("  SISTEMA RAG - SOLUCIONES EDGAR")
print("  Fase 1: Ingesta de documentos")
print("=" * 55)

print(f"\n[1/4] Leyendo documento: {DOCUMENTO_PATH}")

with open(DOCUMENTO_PATH, 'r', encoding='utf-8') as f:
    texto_completo = f.read()

print(f"      Documento cargado: {len(texto_completo)} caracteres")

# =============================================
# PASO 2: LIMPIEZA DEL TEXTO
# =============================================
print("\n[2/4] Limpiando texto...")

# Quitar lineas de separacion y comentarios del archivo
lineas = texto_completo.split('\n')
lineas_limpias = []

for linea in lineas:
    linea = linea.strip()
    # Saltar lineas vacias consecutivas y lineas de separacion
    if linea.startswith('#') and '===' not in linea and 'Script' not in linea:
        continue
    if linea == '---':
        continue
    if linea == '':
        continue
    lineas_limpias.append(linea)

texto_limpio = '\n'.join(lineas_limpias)
print(f"      Texto limpio: {len(texto_limpio)} caracteres")

# =============================================
# PASO 3: CHUNKING (FRAGMENTACION)
# =============================================
print(f"\n[3/4] Fragmentando texto (chunk={CHUNK_SIZE}, overlap={CHUNK_OVERLAP})...")

# Dividir por secciones primero (## encabezados)
secciones = re.split(r'\n(## .+)\n', texto_limpio)

chunks    = []
chunk_ids = []
metadatos = []

chunk_index = 0

for i, seccion in enumerate(secciones):
    if not seccion.strip():
        continue

    # Si es un encabezado, lo usamos como contexto para la siguiente seccion
    if seccion.startswith('## '):
        titulo_actual = seccion.strip()
        continue

    # Fragmentar el contenido de la seccion en chunks con overlap
    texto_seccion = seccion.strip()

    if len(texto_seccion) == 0:
        continue

    inicio = 0
    while inicio < len(texto_seccion):
        fin   = inicio + CHUNK_SIZE
        chunk = texto_seccion[inicio:fin]

        # Asegurarse de no cortar en medio de una palabra
        if fin < len(texto_seccion):
            ultimo_espacio = chunk.rfind(' ')
            if ultimo_espacio > CHUNK_SIZE * 0.7:
                chunk = chunk[:ultimo_espacio]
                fin   = inicio + ultimo_espacio

        chunk = chunk.strip()

        if len(chunk) > 50:  # ignorar chunks muy pequenos
            chunk_id = f"chunk_{chunk_index:04d}"
            chunks.append(chunk)
            chunk_ids.append(chunk_id)
            metadatos.append({
                'seccion': titulo_actual if 'titulo_actual' in dir() else 'General',
                'indice' : chunk_index,
                'largo'  : len(chunk)
            })
            chunk_index += 1

        inicio = fin - CHUNK_OVERLAP  # overlap
        if inicio >= len(texto_seccion):
            break

print(f"      Total de fragmentos generados: {len(chunks)}")
print(f"      Ejemplo fragmento 0:\n      '{chunks[0][:120]}...'")

# =============================================
# PASO 4: GUARDAR EN CHROMADB
# =============================================
print(f"\n[4/4] Guardando en ChromaDB ({CHROMA_PATH})...")

# Crear cliente persistente
cliente   = chromadb.PersistentClient(path=CHROMA_PATH)

# Eliminar coleccion si ya existe (para re-ingestar limpio)
try:
    cliente.delete_collection(COLECCION)
    print("      Coleccion anterior eliminada.")
except:
    pass

# Crear coleccion nueva
coleccion = cliente.get_or_create_collection(
    name=COLECCION,
    metadata={"hnsw:space": "cosine"}  # similitud del coseno
)

# Insertar en lotes de 50
LOTE = 50
for i in range(0, len(chunks), LOTE):
    lote_chunks    = chunks[i:i+LOTE]
    lote_ids       = chunk_ids[i:i+LOTE]
    lote_metadatos = metadatos[i:i+LOTE]

    coleccion.add(
        documents=lote_ids,
        ids=lote_ids,
        metadatas=lote_metadatos
    )

    # Guardar texto real por separado usando upsert con embeddings
    coleccion.upsert(
        ids=lote_ids,
        documents=lote_chunks,
        metadatas=lote_metadatos
    )

    print(f"      Lote {i//LOTE + 1}: {len(lote_chunks)} fragmentos insertados")

# Verificar
total = coleccion.count()
print(f"\n      Total en ChromaDB: {total} fragmentos")

# =============================================
# RESUMEN FINAL
# =============================================
print("\n" + "=" * 55)
print("  INGESTA COMPLETADA EXITOSAMENTE")
print("=" * 55)
print(f"  Documento procesado : conocimiento_soluciones_edgar.txt")
print(f"  Fragmentos totales  : {len(chunks)}")
print(f"  Tamano de chunk     : {CHUNK_SIZE} caracteres")
print(f"  Solapamiento        : {CHUNK_OVERLAP} caracteres")
print(f"  Base de datos       : {CHROMA_PATH}")
print(f"  Coleccion           : {COLECCION}")
print(f"  Metrica de distancia: coseno (cosine similarity)")
print("=" * 55)
print("\n  Siguiente paso: ejecutar rag_query.py")
print("  para hacer consultas al sistema RAG.\n")
