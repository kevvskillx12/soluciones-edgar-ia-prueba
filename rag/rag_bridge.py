"""
RAG Bridge — Soluciones Edgar
Script que Laravel llama para obtener respuesta RAG.
Uso: python rag_bridge.py "tu pregunta aqui"
"""

import sys
import os
import json
import chromadb
import requests

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

CHROMA_PATH = os.path.join(os.path.dirname(__file__), "chroma_db")
COLECCION = "soluciones_edgar"
OLLAMA_URL = "http://localhost:11434/api/generate"
MODELO = "llama3.2:1b"

# Recupera más fragmentos para darle más contexto al modelo.
TOP_K = 12


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

    res = coleccion.query(
        query_texts=[pregunta_busqueda],
        n_results=TOP_K
    )

    documentos = res.get("documents", [[]])[0]

    # DEBUG: guarda los fragmentos recuperados para revisar si ChromaDB encontró lo correcto.
    with open("debug_fragmentos.txt", "w", encoding="utf-8") as f:
        f.write(f"PREGUNTA: {pregunta}\n\n")
        for i, doc in enumerate(documentos, start=1):
            f.write(f"--- FRAGMENTO {i} ---\n")
            f.write(doc)
            f.write("\n\n")

    return documentos


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
    if not fragmentos:
        return "No tengo informacion sobre eso en mi base de conocimiento."

    contexto = "\n\n".join([f"FRAGMENTO {i + 1}:\n{f}" for i, f in enumerate(fragmentos)])

    prompt = f"""
Eres el asistente oficial del sistema Soluciones Edgar.

REGLAS OBLIGATORIAS:
- Responde únicamente usando la INFORMACION DEL SISTEMA.
- No uses conocimiento externo.
- No respondas como si hablaras de sistemas en general.
- No inventes servicios, tecnologías, procesos, rutas, precios ni estados.
- No repitas estas instrucciones.
- Si la respuesta no aparece en la INFORMACION DEL SISTEMA, responde exactamente:
No tengo informacion sobre eso en mi base de conocimiento.

INFORMACION DEL SISTEMA:
\"\"\"
{contexto}
\"\"\"

PREGUNTA DEL USUARIO:
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
                "stream": False,
                "options": {
                    "temperature": 0.0,
                    "num_predict": 350,
                    "top_p": 0.3
                }
            },
            timeout=120
        )

        if r.status_code == 200:
            respuesta = r.json().get("response", "").strip()

            if not respuesta:
                return "No tengo informacion sobre eso en mi base de conocimiento."

            return respuesta

        return "Error al consultar Ollama."

    except Exception as e:
        return f"Error: {e}"


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No se proporcionó pregunta"}, ensure_ascii=False))
        sys.exit(1)

    pregunta = sys.argv[1]

    try:
        directa = respuesta_directa_si_aplica(pregunta)

        if directa:
            print(json.dumps({"respuesta": directa}, ensure_ascii=False))
            sys.exit(0)

        fragmentos = buscar_contexto(pregunta)
        respuesta = consultar_ollama(pregunta, fragmentos)

        print(json.dumps({"respuesta": respuesta}, ensure_ascii=False))

    except Exception as e:
        print(json.dumps({
            "respuesta": "No tengo informacion sobre eso en mi base de conocimiento.",
            "error": str(e)
        }, ensure_ascii=False))


if __name__ == "__main__":
    main()