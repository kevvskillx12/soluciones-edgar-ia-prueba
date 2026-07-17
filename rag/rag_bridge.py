"""
RAG Bridge — Soluciones Edgar
Script que Laravel llama para obtener respuesta RAG.
Uso: python rag_bridge.py "tu pregunta aqui"
"""

import sys
import os
import json
import re
import chromadb
import requests

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

CHROMA_PATH = os.path.join(os.path.dirname(__file__), "chroma_db")
COLECCION = "soluciones_edgar"
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://127.0.0.1:11434/api/generate")
MODELO = "llama3.2:1b"

# Recupera más fragmentos para darle más contexto al modelo.
TOP_K = 10
TOP_RERANKED = 3
KEYWORD_POOL = 25


def normalizar_texto(texto):
    return (
        texto.lower()
        .replace("á", "a")
        .replace("é", "e")
        .replace("í", "i")
        .replace("ó", "o")
        .replace("ú", "u")
        .replace("ü", "u")
        .replace("ñ", "n")
        .strip()
    )


def tokenizar(texto):
    texto = normalizar_texto(texto)
    return re.findall(r"[a-z0-9]{3,}", texto)


def keyword_score(query_tokens, documento):
    doc_tokens = tokenizar(documento)
    if not query_tokens or not doc_tokens:
        return 0.0

    doc_set = set(doc_tokens)
    overlap = sum(1 for token in query_tokens if token in doc_set)
    phrase_bonus = 0.0
    doc_normalizado = normalizar_texto(documento)

    for token in query_tokens:
        if token in doc_normalizado:
            phrase_bonus += 0.05

    length_penalty = 1.0 + (len(doc_tokens) / 600.0)
    return (overlap + phrase_bonus) / length_penalty


def keyword_search(coleccion, pregunta, limite=KEYWORD_POOL):
    try:
        datos = coleccion.get(include=["documents"])
    except Exception:
        return []

    documentos = datos.get("documents") or []
    ids = datos.get("ids") or [f"kw-{i}" for i in range(len(documentos))]
    query_tokens = tokenizar(pregunta)
    scored = []

    for idx, documento in enumerate(documentos):
        if not documento:
            continue
        score = keyword_score(query_tokens, documento)
        if score > 0:
            scored.append({
                "id": ids[idx] if idx < len(ids) else f"kw-{idx}",
                "document": documento,
                "score": score,
                "source": "keyword",
            })

    scored.sort(key=lambda item: item["score"], reverse=True)
    return scored[:limite]


def semantic_search(coleccion, pregunta, limite=TOP_K):
    res = coleccion.query(
        query_texts=[pregunta],
        n_results=limite
    )

    documentos = res.get("documents", [[]])[0]
    ids = res.get("ids", [[]])[0] if res.get("ids") else []
    distances = res.get("distances", [[]])[0] if res.get("distances") else []
    resultados = []

    for idx, documento in enumerate(documentos):
        if not documento:
            continue
        distance = distances[idx] if idx < len(distances) else 1.0
        resultados.append({
            "id": ids[idx] if idx < len(ids) else f"vec-{idx}",
            "document": documento,
            "score": 1.0 / (1.0 + float(distance or 0.0)),
            "source": "semantic",
        })

    return resultados


def reciprocal_rank_fusion(result_sets, k=60):
    fused = {}

    for resultados in result_sets:
        for rank, item in enumerate(resultados, start=1):
            item_id = item["id"]
            if item_id not in fused:
                fused[item_id] = {
                    "id": item_id,
                    "document": item["document"],
                    "score": 0.0,
                    "sources": set(),
                }
            fused[item_id]["score"] += 1.0 / (k + rank)
            fused[item_id]["sources"].add(item.get("source", "unknown"))

    ordenados = list(fused.values())
    ordenados.sort(key=lambda item: item["score"], reverse=True)
    return ordenados


def rerank_local(pregunta, candidatos, limite=TOP_RERANKED):
    query_tokens = tokenizar(pregunta)
    pregunta_normalizada = normalizar_texto(pregunta)
    reranked = []

    for item in candidatos:
        documento = item["document"]
        score_textual = keyword_score(query_tokens, documento)
        documento_normalizado = normalizar_texto(documento)
        phrase_score = 0.25 if pregunta_normalizada and pregunta_normalizada in documento_normalizado else 0.0
        source_bonus = 0.08 if len(item.get("sources", [])) > 1 else 0.0
        final_score = item["score"] + score_textual + phrase_score + source_bonus
        reranked.append({**item, "rerank_score": final_score})

    reranked.sort(key=lambda item: item["rerank_score"], reverse=True)
    return reranked[:limite]


def respuesta_capacidades(pregunta):
    p = normalizar_texto(pregunta)

    if not any(pattern in p for pattern in [
        "en que me puedes ayudar",
        "como me puedes ayudar",
        "que puedes hacer",
        "que sabes hacer",
        "que tramites manejas",
        "que servicios tienes",
        "con que me ayudas",
    ]):
        return None

    return (
        "Puedo ayudarte a consultar tramites, capturar sus datos, crear solicitudes "
        "y revisar el estado o folio de una solicitud anterior. Algunos servicios "
        "disponibles son: CURP, acta de nacimiento, RFC, NSS y constancia fiscal. "
        "Dime que tramite necesitas realizar."
    )


def respuesta_memoria_conversacional(pregunta):
    pregunta_actual = pregunta.rsplit("PREGUNTA ACTUAL:", 1)[-1].strip()
    p = pregunta_actual.lower()
    if not any(pattern in p for pattern in [
        "cómo me llamo",
        "como me llamo",
        "qué trámite necesito",
        "que tramite necesito",
        "qué información tienes sobre mí",
        "que informacion tienes sobre mi",
        "qué te dije",
        "que te dije",
        "recuerdas",
    ]):
        return None

    historial = pregunta.rsplit("PREGUNTA ACTUAL:", 1)[0]
    mensajes_usuario = re.findall(
        r"USER:\s*(.*?)(?=\n\n(?:USER|ASSISTANT|SYSTEM|TOOL):|\Z)",
        historial,
        flags=re.IGNORECASE | re.DOTALL,
    )
    texto_usuario = "\n".join(mensajes_usuario)

    nombre = None
    patrones_nombre = [
        r"\bmi nombre es\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]{2,80})",
        r"\bme llamo\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]{2,80})",
        r"\b(?:trámite|tramite)\s+de\s+\w+\s+para\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]{2,80})",
    ]
    for patron in patrones_nombre:
        coincidencia = re.search(patron, texto_usuario, flags=re.IGNORECASE)
        if coincidencia:
            nombre = coincidencia.group(1).strip(" .,:;¿?")
            break

    tramite = None
    for etiqueta in [
        "CURP", "acta de nacimiento", "RFC", "NSS",
        "constancia de situación fiscal", "constancia fiscal",
    ]:
        if etiqueta.lower() in texto_usuario.lower():
            tramite = etiqueta
            break

    if nombre and tramite:
        return f"La persona indicada es {nombre} y el trámite es {tramite}."
    if nombre:
        return f"La persona indicada es {nombre}. Aún necesito que confirmes el trámite."
    if tramite:
        return f"El trámite indicado es {tramite}. Aún necesito que confirmes el nombre de la persona."

    return "No encuentro todavía el nombre ni el trámite en esta conversación."


def respuesta_tramite_seguro(pregunta):
    """
    Orientación segura y determinista para trámites administrativos comunes.
    No genera documentos, identificadores oficiales ni datos personales.
    """
    p = pregunta.lower()
    if any(pattern in p for pattern in [
        "cómo me llamo",
        "como me llamo",
        "qué trámite necesito",
        "que tramite necesito",
        "qué información tienes sobre mí",
        "que informacion tienes sobre mi",
        "qué te dije",
        "que te dije",
        "recuerdas",
    ]):
        return None

    menciona_tramite = any(term in p for term in [
        "trámite", "tramite", "curp", "acta de nacimiento", "rfc",
        "nss", "constancia fiscal", "constancia de situación fiscal",
    ])

    if not menciona_tramite:
        return None

    if not any(term in p for term in [
        "curp", "acta de nacimiento", "rfc", "nss",
        "constancia fiscal", "constancia de situación fiscal",
    ]):
        return (
            "Claro, puedo orientarte con un trámite administrativo. "
            "Indícame cuál necesitas, por ejemplo CURP, acta de nacimiento, RFC, NSS "
            "o constancia fiscal. No compartas datos sensibles completos por este chat."
        )

    nombre = None
    marcadores_nombre = [" para ", " de "]
    for marcador in marcadores_nombre:
        if marcador in p:
            candidato = pregunta[p.rfind(marcador) + len(marcador):].strip(" .,:;")
            if candidato and len(candidato.split()) <= 6:
                nombre = candidato
                break

    if "curp" in p:
        destinatario = f" de {nombre}" if nombre else ""
        return (
            f"Claro, puedo ayudarte con orientación para el trámite de CURP{destinatario}. "
            "Normalmente se requiere nombre completo, fecha de nacimiento, sexo y entidad "
            "de nacimiento; para aclaraciones o correcciones también puede solicitarse acta "
            "de nacimiento e identificación. No compartas datos sensibles completos si no "
            "es necesario. El siguiente paso es verificar la CURP en el portal oficial de "
            "gob.mx o solicitar apoyo de un asesor. ¿Ya cuentas con el acta de nacimiento?"
        )

    if "acta de nacimiento" in p:
        return (
            "Claro, puedo orientarte con el acta de nacimiento. Normalmente se solicitan "
            "nombre completo, fecha y entidad de nacimiento, nombres de los padres y, si "
            "se conoce, datos del registro civil. Verifica requisitos y costos en el portal "
            "oficial o con un asesor. No compartas datos sensibles innecesarios por este chat. "
            "¿Necesitas una copia certificada o corregir un dato?"
        )

    if "rfc" in p or "constancia fiscal" in p or "constancia de situación fiscal" in p:
        return (
            "Puedo orientarte con RFC o constancia de situación fiscal. Generalmente se "
            "requieren CURP, identificación y acceso a los medios de autenticación del SAT, "
            "según el trámite. Confirma los requisitos en el portal oficial del SAT y evita "
            "compartir contraseñas, e.firma o datos sensibles por este chat. ¿Buscas inscripción, "
            "consulta de RFC o descarga de constancia?"
        )

    if "nss" in p:
        return (
            "Puedo orientarte con la consulta o asignación del NSS. Normalmente se requiere "
            "CURP, correo electrónico y datos personales básicos. Realiza la verificación en "
            "el portal oficial del IMSS o solicita apoyo de un asesor. No compartas información "
            "sensible completa por este chat. ¿Necesitas localizar un NSS existente o solicitarlo?"
        )

    return None


def buscar_contexto(pregunta):
    cliente = chromadb.PersistentClient(path=CHROMA_PATH)
    coleccion = cliente.get_collection(COLECCION)

    p = pregunta.lower()

    extra = " Soluciones Edgar sistema proyecto Laravel Filament usuarios pedidos servicios saldo depositos"

    if "tecnologia" in p or "tecnologías" in p or "tecnologias" in p:
        extra += " tecnologias del sistema Laravel PHP Eloquent migrations seeders Filament Livewire Volt S3 Ollama"

    if "depositrequestresource" in p or "deposit request resource" in p or "deposito" in p or "depósito" in p or "depositos" in p or "depósitos" in p:
        extra += " DETALLE COMPLETO DE DEPOSITREQUESTRESOURCE solicitudes de saldo deposit_requests banco clave rastreo monto comprobante aprobar rechazar"

    if "serviceresource" in p or "service resource" in p or "catalogo" in p or "catálogo" in p:
        extra += " DETALLE COMPLETO DE SERVICERESOURCE catalogo servicios categorias precios costos formularios personalizados solicitar"

    if "orderresource" in p or "order resource" in p or "orden" in p or "pedido" in p:
        extra += " DETALLE COMPLETO DE ORDERRESOURCE gestion tramites pedidos estados subir resultado descargar PDF"

    pregunta_busqueda = pregunta + extra

    semanticos = semantic_search(coleccion, pregunta_busqueda, TOP_K)
    textuales = keyword_search(coleccion, pregunta_busqueda, KEYWORD_POOL)
    fusionados = reciprocal_rank_fusion([semanticos, textuales])
    rerankeados = rerank_local(pregunta_busqueda, fusionados[: max(TOP_K, KEYWORD_POOL)])

    if rerankeados:
        return [item["document"] for item in rerankeados]

    return [item["document"] for item in semanticos[:TOP_RERANKED]]


def respuesta_directa_si_aplica(pregunta):
    """
    Respuestas directas para preguntas muy comunes.
    Esto evita que el modelo pequeño invente cuando la pregunta es general.
    """
    p = pregunta.lower()

    if (
        "servicios ofrece" in p
        or "servicios tiene" in p
        or "qué servicios" in p
        or "que servicios" in p
        or "tramites puedo solicitar" in p
        or "trámites puedo solicitar" in p
        or "categorias de servicios" in p
        or "categorías de servicios" in p
    ):
        return (
            "Soluciones Edgar ofrece servicios de actas, SAT, IMSS, servicios generales, "
            "Infonavit y trámites vehiculares. Entre los servicios registrados están: "
            "acta de nacimiento, acta de defunción, acta de divorcio, acta de matrimonio, "
            "CSF con CURP, CSF con RFC e IDCIF, localizar IDCIF, constancia de vigencia "
            "de derechos NSS, localizar NSS, semanas cotizadas, localizar AFORE, CURP "
            "actualizada, recibo CFE, antecedentes no penales, servicios de Infonavit, "
            "formatos de pago de tenencia CDMX y EDOMEX, y hoja REPUVE."
        )

    if (
        "estados de un pedido" in p
        or "estados tiene un pedido" in p
        or "estados posibles de un pedido" in p
        or "estados de una orden" in p
        or "estado de pedido" in p
    ):
        return (
            "Los estados de un pedido en Soluciones Edgar son: pending, processing, "
            "completed y rejected. Pending significa pendiente, processing significa "
            "en proceso, completed significa completado y rejected significa rechazado."
        )

    if (
        "saldo del usuario" in p
        or "funciona el saldo" in p
        or "qué es balance" in p
        or "que es balance" in p
        or "saldo insuficiente" in p
    ):
        return (
            "El saldo del usuario se guarda en el campo balance de la tabla users. "
            "Por defecto, el saldo inicial es 0. El sistema puede agregar saldo con "
            "los métodos credit o addBalance del modelo User, y puede descontarlo con "
            "subtractBalance. Cuando se descuenta saldo, se registra una transacción "
            "de tipo purchase. Si el usuario no es administrador y no tiene saldo "
            "suficiente, el sistema detiene la operación y lanza el error Saldo insuficiente."
        )

    if (
        "administrador" in p
        and ("identifica" in p or "sabe" in p or "admin" in p)
    ):
        return (
            "Un administrador se identifica mediante el campo is_admin en la tabla users. "
            "Cuando is_admin es true, el usuario puede acceder al panel administrativo. "
            "Si is_admin es false, el usuario se trata como cliente normal."
        )

    if (
        "tecnologias usa" in p
        or "tecnologías usa" in p
        or "tecnologias del sistema" in p
        or "tecnologías del sistema" in p
        or "que tecnologias" in p
        or "qué tecnologías" in p
        or "tecnologia usa" in p
        or "tecnología usa" in p
    ):
        return (
            "El sistema Soluciones Edgar usa Laravel y PHP. También usa Eloquent ORM "
            "para manejar modelos y relaciones, migrations para crear y modificar tablas, "
            "seeders para cargar datos iniciales, Filament para construir paneles de administración, "
            "Livewire Volt para páginas de autenticación, notificaciones de Laravel, relaciones "
            "hasMany y belongsTo, relaciones polimórficas, almacenamiento compatible con S3 "
            "para archivos de resultados y Ollama con el modelo llama3.2:1b para la integración "
            "de inteligencia artificial."
        )

    if (
        "depositrequestresource" in p
        or "deposit request resource" in p
        or "solicitudes de saldo" in p
        or "solicitudes de deposito" in p
        or "solicitudes de depósito" in p
        or "recurso de depositos" in p
        or "recurso de depósitos" in p
    ):
        return (
            "DepositRequestResource es el recurso de Filament encargado de administrar "
            "solicitudes de saldo o depósitos. Usa el modelo DepositRequest y aparece en "
            "el grupo de navegación Finanzas como Solicitudes de Saldo. Permite capturar "
            "banco emisor, clave de rastreo, monto y comprobante de pago. El monto mínimo "
            "es de 300 pesos. También permite que el administrador vea notas, apruebe o "
            "rechace solicitudes. Los estados de una solicitud de depósito son pending, "
            "approved y rejected. Los clientes solo ven sus propias solicitudes, mientras "
            "que los administradores pueden ver todas."
        )

    if (
        "serviceresource" in p
        or "service resource" in p
        or "catálogo de servicios" in p
        or "catalogo de servicios" in p
        or "recurso de servicios" in p
    ):
        return (
            "ServiceResource es el recurso de Filament encargado de administrar el catálogo "
            "de servicios. Usa el modelo Service y aparece como Catálogo de Servicios dentro "
            "del grupo Operaciones. Permite crear, editar y eliminar servicios, capturar código, "
            "nombre, categoría, tipo legacy, descripción, precio, costo, tiempo de procesamiento, "
            "horario activo, imagen y estado activo. También permite configurar form_schema "
            "mediante campos personalizados para que cada servicio tenga su propio formulario. "
            "En la tabla puede mostrar servicios por categorías y tiene una acción llamada SOLICITAR."
        )

    if (
        "orderresource" in p
        or "order resource" in p
        or "recurso de pedidos" in p
        or "recurso de ordenes" in p
        or "recurso de órdenes" in p
        or "gestión de trámites" in p
        or "gestion de tramites" in p
    ):
        return (
            "OrderResource es el recurso de Filament encargado de administrar pedidos o trámites. "
            "Usa el modelo Order y aparece como Gestión de Trámites dentro del grupo Operaciones. "
            "Permite seleccionar usuario y servicio, generar campos dinámicos usando form_schema, "
            "cambiar el estado del pedido, subir un resultado PDF, agregar notas administrativas, "
            "descargar el PDF cuando el pedido está completado y exportar reportes. Los estados "
            "que maneja son pending, processing, completed y rejected."
        )

    return None


def consultar_ollama(pregunta, fragmentos):
    pregunta_actual = pregunta.rsplit("PREGUNTA ACTUAL:", 1)[-1].strip()
    pregunta_actual_lower = pregunta_actual.lower()
    es_pregunta_memoria = any(pattern in pregunta_actual_lower for pattern in [
        "mi nombre es",
        "me llamo",
        "necesito tramitar",
        "cómo me llamo",
        "como me llamo",
        "qué trámite necesito",
        "que tramite necesito",
        "qué información tienes sobre mí",
        "que informacion tienes sobre mi",
        "qué te dije",
        "que te dije",
        "recuerdas",
    ])

    if not fragmentos and not es_pregunta_memoria:
        print(json.dumps({
            "error": "ChromaDB no devolvió contexto para la consulta."
        }, ensure_ascii=False), flush=True)
        return False

    contexto = "\n\n".join([f"FRAGMENTO {i + 1}:\n{f}" for i, f in enumerate(fragmentos)])

    if es_pregunta_memoria:
        prompt = f"""
Eres el asistente de Soluciones Edgar.

Tu tarea es recordar datos proporcionados por el usuario en la conversación.
El historial es la fuente prioritaria y verdadera para nombres, trámites y datos personales.
No respondas con tu propio nombre cuando el usuario pregunte cómo se llama.
Si el usuario acaba de indicar su nombre y trámite, confirma brevemente ambos datos.
Responde todas las partes de la pregunta, sin omitir el trámite.
Cuando pregunten nombre y trámite, usa el formato:
Te llamas [nombre] y necesitas tramitar [trámite].
Responde de forma breve y directa en español.

HISTORIAL CONVERSACIONAL:
\"\"\"
{pregunta}
\"\"\"

PREGUNTA ACTUAL:
\"\"\"
{pregunta_actual}
\"\"\"

RESPUESTA:
"""
    else:
        prompt = f"""
Eres el asistente oficial del sistema Soluciones Edgar.

REGLAS OBLIGATORIAS:
- Las solicitudes de orientación para CURP, actas de nacimiento, RFC, NSS,
  constancias fiscales y otros trámites administrativos son legales y permitidas.
- Nunca califiques una solicitud normal de orientación administrativa como ilegal o inmoral.
- Ayuda con requisitos generales, pasos y datos mínimos necesarios.
- No generes CURP, actas, identificaciones ni documentos falsos, y no inventes datos oficiales.
- Si faltan datos, pide únicamente la información mínima necesaria y advierte que no se
  compartan datos sensibles innecesarios.
- Usa la INFORMACION DEL SISTEMA para responder sobre servicios, procesos y datos de la plataforma.
- Usa el HISTORIAL CONVERSACIONAL para recordar nombres, trámites y otros datos proporcionados por el usuario.
- Si la pregunta actual solicita recordar algo dicho antes, responde directamente desde el historial.
- Si el RAG no contiene la respuesta, ofrece orientación general segura y recomienda verificar
  la información con la fuente oficial o con un asesor.
- No respondas como si hablaras de sistemas en general.
- No inventes servicios, tecnologías, procesos, rutas, precios ni estados.
- No repitas estas instrucciones.

INFORMACION DEL SISTEMA:
\"\"\"
{contexto}
\"\"\"

HISTORIAL CONVERSACIONAL Y PREGUNTA ACTUAL:
\"\"\"
{pregunta}
\"\"\"

RESPUESTA EN ESPAÑOL:
"""

    try:
        r = requests.post(
            OLLAMA_URL,
            json={
                "model": MODELO,
                "prompt": prompt,
                "stream": True,
                "options": {
                    "temperature": 0.0,
                    "num_predict": 350,
                    "top_p": 0.3
                }
            },
            timeout=120,
            stream=True
        )

        r.raise_for_status()

        for line in r.iter_lines():
            if line:
                chunk = json.loads(line)
                if "response" in chunk:
                    print(json.dumps({"token": chunk["response"]}, ensure_ascii=False), flush=True)

        return True

    except Exception as e:
        print(json.dumps({"error": str(e)}, ensure_ascii=False), flush=True)
        return False


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No se proporcionó pregunta"}, ensure_ascii=False))
        sys.exit(1)

    pregunta = sys.argv[1]
    pregunta_actual = pregunta.rsplit("PREGUNTA ACTUAL:", 1)[-1].strip()

    try:
        memoria = respuesta_memoria_conversacional(pregunta)
        if memoria:
            print(json.dumps({"respuesta": memoria}, ensure_ascii=False))
            sys.exit(0)

        tramite_seguro = respuesta_tramite_seguro(pregunta_actual)
        if tramite_seguro:
            print(json.dumps({"respuesta": tramite_seguro}, ensure_ascii=False))
            sys.exit(0)

        capacidades = respuesta_capacidades(pregunta_actual)
        if capacidades:
            print(json.dumps({"respuesta": capacidades}, ensure_ascii=False))
            sys.exit(0)

        directa = respuesta_directa_si_aplica(pregunta_actual)

        if directa:
            print(json.dumps({"respuesta": directa}, ensure_ascii=False))
            sys.exit(0)

        fragmentos = buscar_contexto(pregunta_actual)
        
        # Emite un evento especial de SEARCHING/CONTEXT_FOUND (Fase 3 UI states)
        print(json.dumps({
            "status": "SEARCHING",
            "context_found": len(fragmentos) > 0,
            "rag_pipeline": "hybrid_rrf_rerank",
            "top_k_final": len(fragmentos)
        }), flush=True)

        if not consultar_ollama(pregunta, fragmentos):
            sys.exit(1)

        print(json.dumps({"status": "COMPLETED"}), flush=True)

    except Exception as e:
        print(json.dumps({
            "error": str(e)
        }, ensure_ascii=False), flush=True)
        sys.exit(1)


if __name__ == "__main__":
    main()
