"""
Script RAG — Sistema de consultas Soluciones Edgar
Version mejorada: prompt optimizado para modelos pequenos (1b)
"""

import chromadb
import requests
import os
import time

CHROMA_PATH = os.path.join(os.path.dirname(__file__), 'chroma_db')
COLECCION   = 'soluciones_edgar'
OLLAMA_URL  = 'http://localhost:11434/api/generate'
MODELO      = 'llama3.2:1b'
TOP_K       = 12

def conectar_chromadb():
    cliente   = chromadb.PersistentClient(path=CHROMA_PATH)
    coleccion = cliente.get_collection(COLECCION)
    return coleccion

def buscar_contexto(coleccion, pregunta, top_k=TOP_K):
    resultados = coleccion.query(query_texts=[pregunta], n_results=top_k)
    return resultados['documents'][0], resultados['metadatas'][0], resultados['distances'][0]

def construir_prompt(pregunta, fragmentos):
    contexto = "\n\n".join([f"- {frag}" for frag in fragmentos])
    return f"""Usa la siguiente informacion para responder la pregunta.

INFORMACION:
{contexto}

PREGUNTA: {pregunta}

RESPUESTA en espanol (basada solo en la informacion de arriba):"""

def consultar_ollama(prompt):
    try:
        payload = {
            "model" : MODELO,
            "prompt": prompt,
            "stream": False,
            "options": {"temperature": 0.1, "top_p": 0.9, "num_predict": 300}
        }
        r = requests.post(OLLAMA_URL, json=payload, timeout=120)
        return r.json().get('response', 'Sin respuesta').strip() if r.status_code == 200 else f"Error: {r.status_code}"
    except Exception as e:
        return f"Error de conexion: {e}"

def rag_query(pregunta, verbose=True):
    if verbose:
        print(f"\n{'='*55}")
        print(f"  CONSULTA RAG")
        print(f"{'='*55}")
        print(f"  Pregunta: {pregunta}")
        print(f"{'='*55}")

    coleccion = conectar_chromadb()

    t0 = time.time()
    fragmentos, metadatos, distancias = buscar_contexto(coleccion, pregunta)
    lat_ms = (time.time() - t0) * 1000

    if verbose:
        print(f"\n[Recuperacion vectorial]")
        print(f"  Fragmentos encontrados : {len(fragmentos)}")
        print(f"  Latencia de busqueda   : {lat_ms:.1f} ms")
        for i, dist in enumerate(distancias):
            print(f"  Fragmento {i+1}: similitud={1-dist:.3f}")

    prompt = construir_prompt(pregunta, fragmentos)

    if verbose:
        print(f"\n[Generando respuesta con Ollama ({MODELO})...]")

    t1 = time.time()
    respuesta = consultar_ollama(prompt)
    lat_llm = time.time() - t1

    if verbose:
        print(f"  Tiempo de generacion: {lat_llm:.1f}s")
        print(f"\n{'='*55}")
        print(f"  RESPUESTA:")
        print(f"{'='*55}")
        print(f"\n{respuesta}\n")
        print(f"{'='*55}\n")

    return {'pregunta': pregunta, 'respuesta': respuesta, 'latencia_busqueda_ms': round(lat_ms,1), 'latencia_llm_s': round(lat_llm,1)}

if __name__ == '__main__':
    print("\n" + "="*55)
    print("  SISTEMA RAG - SOLUCIONES EDGAR")
    print("  Chat con base de conocimiento")
    print("="*55)
    print("  Escribe tu pregunta o 'salir' para terminar.\n")

    try:
        col = conectar_chromadb()
        print(f"  Base de datos cargada: {col.count()} fragmentos listos.\n")
    except Exception as e:
        print(f"  ERROR: Ejecuta primero python ingestar.py\n  {e}")
        exit(1)

    while True:
        try:
            pregunta = input("Tu pregunta: ").strip()
        except (EOFError, KeyboardInterrupt):
            print("\n  Sesion terminada.")
            break
        if pregunta.lower() in ['salir', 'exit', 'quit', 'q']:
            print("\n  Hasta luego.\n")
            break
        if pregunta:
            rag_query(pregunta)