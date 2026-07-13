# Roni — Referencia de clasificación y extracción de pedidos por WhatsApp

**Agente:** Roni (OpenClaw) · **Negocio:** Carnes Don David / Don David POS (expendio de carnes, Bogotá)
**Fuente:** Análisis de 20 chats reales exportados de WhatsApp con clientes frecuentes (abr 2024 – jun 2026, ~1 MB de texto). Todos los ejemplos de este documento son reales, anonimizados con `[CUSTOMER_NAME]`, `[PHONE]`, `[ADDRESS]`, `[BUSINESS_NAME]`, `[DROP_POINT]`.

---

## 1. Propósito

Roni recibe mensajes de WhatsApp de clientes y debe:

1. Identificar si el mensaje (o grupo de mensajes) es un **pedido real**.
2. Si es pedido, **extraer** productos, cantidades, unidades, notas, entrega, hora y pago.
3. Producir un **JSON** estructurado (esquema en §9).
4. Generar el **texto para impresora térmica** (formato en §10).
5. **Imprimir automáticamente** solo cuando se cumplan las reglas de §8.

Principio rector: en los chats reales la carnicería casi nunca rechaza un pedido por información faltante — pregunta o resuelve por contexto. Roni imita eso: **ante duda razonable imprime con alerta visible; solo bloquea (revisión manual) lo verdaderamente ininteligible o riesgoso.**

---

## 2. Arquetipos de cliente (cómo escriben de verdad)

| # | Arquetipo | Cómo pide | Ejemplo real (anonimizado) |
|---|-----------|-----------|---------------------------|
| 1 | **Restaurante de rutina diaria** | Saludo + "Para hoy" + lista, repartido en 3–6 mensajes seguidos. Sin dirección, sin pago, unidades implícitas. | "Buen día" / "Para hoy" / "6 asar" / "2 molida" / "Gracias" |
| 2 | **Restaurante semanal** | Un solo mensaje largo en la noche para el día siguiente. | "Buenas noches… para mañana 24 libras de goulash, 5x5 de molida, 3y3 de hueso, 30 porciones de cerdo por favor. Muchas gracias" |
| 3 | **Cliente con domicilio ad-hoc** | Mezcla pagos y pedidos en el mismo mensaje; manda pines de Maps y fotos de dónde está. | "Mira te envío 540.000, mi mamá te da el resto en efectivo y me alistas 10 kilos de molida por favor" |
| 4 | **Grupo B2B estructurado** | Formato formal `*PEDIDO*` / `*RECIBO*` con fecha; pagos semanales. | "*PEDIDO* 26/06/2026 • 40 kilos de carne para moler" |
| 5 | **Institucional** | Lista con viñetas ⚠️, gramaje + porciones. También pide favores de facturación (→ §7). | "⚠️ carne Murillo de 100 gramos 250 porciones" |

---

## 3. Regla de agrupación multi-mensaje (OBLIGATORIA antes de clasificar)

Los pedidos reales llegan **partidos en varios mensajes consecutivos**. Clasificar mensaje por mensaje fragmenta pedidos y genera tickets basura.

**Regla:** acumular todos los mensajes consecutivos del mismo cliente hasta que haya una **ventana de silencio de 90–120 segundos**, y clasificar el bloque completo como una sola unidad.

Ejemplo real (5 mensajes en 60 segundos → UN pedido):

```
[CUSTOMER_NAME]: Buen día
[CUSTOMER_NAME]: Me regala para hoy
[CUSTOMER_NAME]: 6 asar
[CUSTOMER_NAME]: 2 molida
[CUSTOMER_NAME]: Gracias
```

- El saludo y el "Gracias" pertenecen al bloque; no son mensajes independientes.
- Si tras imprimir llega una **adición** ("Y 4 patas por favor", "Me agrega 8 libras de costilla"), aplicar §7.3 (reimpresión del pedido completo).

---

## 4. Clasificación

### 4.1 ES pedido (`is_order = true`)

Frases disparadoras reales (con producto identificable en el bloque):

- "me alista / me alistan / me alistas esto por favor"
- "me regala / me regalan para hoy…"
- "me puede despachar por favor…"
- "porfa me pueden ir preparando…"
- "por favor me envía / me envían / me puedes enviar…"
- "necesito por favor…"
- "pedido" / "Pedido para mañana" / "*PEDIDO* dd/mm/yyyy"
- "le encargo / te puedo encargar…"
- "me manda / me puedes mandar [DROP_POINT]…"
- "será que podrás traer…"
- "me ayudas para mañana con…"
- "me colabora con este pedido"
- "para hoy / para mañana" + lista de productos
- "quiero…", "me separa…"
- Lista de productos sin verbo introductorio (muy común): "5 kilos de espaldilla / 5 kilos de muchacho / 20 chorizos"
- "Me agrega X" / "Y también X" / "adicional X" → **adición** a pedido abierto (§7.3)

**Regla estructural:** si el bloque contiene ≥1 línea con patrón `cantidad + producto` o `producto + cantidad`, es casi seguro un pedido aunque no haya verbo ("Espaldilla 5kg / Muchacho 10kg / Chorizo 30").

### 4.2 NO es pedido (`is_order = false`, nunca imprime)

| Tipo | Ejemplos reales |
|------|-----------------|
| Solo saludo | "Hola buen día", "Cómo estás" |
| Pregunta de precio | "¿A cómo está el churrasco?", "me ayudas con el precio del kilo de…", "¿en cuánto me deja el kilo?" |
| Pregunta de disponibilidad (sola) | "¿Hoy tienes disponible sobrebarriga?" |
| **Confirmación de pago** | Foto de comprobante + "le envío soporte del pago", "ya pagué", "envío comprobante de la semana del…", "te envié $310.000 en efectivo" |
| Pregunta de saldo/cuenta | "¿Cuánto te debo?", "me envías el valor que te debo", "¿me ayudas con la factura de hoy?" |
| Solicitud de factura / admin | "no me mandaste la factura", "me regalas el RUT", "factura electrónica al correo…" |
| Estado de entrega | "¿A qué hora llega el pedido?", "¿Ya salió el pedido?", "Quedo pendiente de la entrega" |
| Aviso de llegada / recogida | "Estoy aquí" + foto/pin, "En 20 minutos paso", "Ya llegó", "ubicación: https://maps…" |
| Agradecimiento | "Muchas gracias", "Listo gracias amigo" |
| Queja / calidad (sola) | "la carne salió dañada", "no me diste la sobrebarriga ayer" |
| Confirmación de recibido B2B | "*RECIBO* 26/06/2026 • 40 kilos de carne" |
| Horario | "¿Hasta qué hora están hoy?" |
| Multimedia solo | "<Multimedia omitido>" sin texto de pedido |

**Nota:** las confirmaciones de pago y preguntas de precio **nunca** generan ticket (decisión confirmada del dueño).

### 4.3 Mensajes MIXTOS (muy frecuentes — no descartarlos)

Si el bloque contiene contenido de no-pedido **y** líneas de pedido, **es pedido**; el resto se registra en `customer_notes`:

| Caso real | Tratamiento |
|-----------|-------------|
| "Buenos días, mira te envío 540.000 y me alistas 10 kilos de molida por favor" | Pedido (10 kg molida) + nota "cliente reporta pago $540.000" |
| "30 porciones de carne blandita — la última me salió dura" | Pedido + nota de queja/calidad en el ítem |
| "¿Tienes sobrebarriga? … Por favor 55 porciones" (2 mensajes) | La pregunta de disponibilidad se convierte en pedido dentro de la misma ventana |
| "Por favor 4 libras de costilla y me envías cuanto es con lo de la semana pasada" | Pedido + nota "solicita saldo pendiente" |

### 4.4 Correcciones y mensajes editados/eliminados

- "creo que escribí mal, era carne para desmechar y también carne porcionada" → corrección de pedido abierto: actualizar la sesión y reimprimir (§7.3).
- Mensajes "Se eliminó este mensaje" → ignorar.
- `<Se editó este mensaje.>` → usar el texto final recibido.

---

## 5. Extracción

### 5.1 Catálogo de productos (visto en los chats, con variantes ortográficas)

**Res:** carne para asar ("asar", "blandita"), molida (especial / corriente / "la más económica" / 2da / milanesa / cogote molido), goulash ("gulas", "guolash", "goulas"; entero o molido; res o cerdo), doble molida, cadera, bola, pecho, espaldilla, muchacho, murillo, milanesa, sobrebarriga ("sobre barriga", "sobrerriga"; gruesa / para desmechar / para tajar), costilla de res / costilla para caldo / delantero de costilla, churrasco / churrasquitos, lomo fino, posta, cola, rabo, bofe, hueso (sopa / caldo / agujas), callo (picado), librillo (picado), cuajo, menudo (revuelto picado), chinchurria ("chinchorria"), mondongo, pata (de res, picada / en rodajas), lengua, hígado, carne para sudar, carne para desmechar, "especiales" (porción propia del cliente), palomilla, carne picada para arroz.
**Cerdo:** lomo de cerdo, pierna de cerdo ("pierna, no brazo"), chuleta, costilla de cerdo, tocino carnudo, pezuña (partida / bien cortada), panceta, chicharrón (delgado), hueso de cerdo.
**Otros:** carnero (brazo/pierna/cuello, en bloque o rodajas), chorizo (unidades / paquetes grande-pequeño / coctel), pechuga / pollo (deshuesada), arepas paisa (paquetes), queso.

Reglas:
- Conservar SIEMPRE `raw_text` del ítem tal como lo escribió el cliente.
- Normalizar `product_name` al término del catálogo más cercano; si no existe, copiar el texto del cliente tal cual (no inventar).
- Tolerar ortografía fonética: "guolash", "sobrerriga", "chinchorria", "X faboor", "Aq hora m la mandan".

### 5.2 Cantidades y unidades

| Patrón | Ejemplos reales | Extracción |
|--------|-----------------|------------|
| Kilos | "5 kilos", "5kg", "10k", "3 kl", "2 kilos" | `unit: "kg"` |
| Libras | "8 libras", "2 lb", "38 libras" | `unit: "lb"` |
| Unidades | "20 chorizo", "4 patas", "6 churrasco", "1 lomo entero" | `unit: "unit"` |
| Paquetes | "2 paquetes de chorizo grandes", "5 paquete de chorizo pequeño" | `unit: "package"` |
| Porciones + gramaje | "20 porciones de cerdo 110gr", "churrasco x 200 gramos", "porcionada a 125 gramos de 80 pedazos", "30 churrascos x 200" | `unit: "unit"`, gramaje en `notes` (ej. "porción de 110 g") |
| **Bolsas / shorthand** | "molida por 5" (bolsas de 5 lb), "5x5 de molida" (5 bolsas de 5 lb), "3y3 de hueso" (2 bolsas de 3 lb), "6 de hueso 3 y 3", "empacar en bolsas de 10 kilos" | `unit: "package"`, detalle en `notes` (ej. "5 bolsas de 5 libras") |
| **Plata como cantidad** | "120.000 de sobrebarriga", "150 de costilla", "$120gr" (typo) | `unit: "pesos"`, `quantity: "120000"`. Heurística: número ≥ 10.000, o con puntos de miles o `$`, junto a un producto = pesos. "150 de costilla" en contexto de cliente que pide por plata = $150.000 → si ambiguo, imprimir con alerta. |
| Número pelado (unidad implícita) | "6 asar", "4 molida", "40 de goulash molido", "12 gulas", "10 especiales" | `unit: "unknown"`, cantidad extraída, `raw_text` visible en ticket. NO bloquea impresión. |
| Fracciones | "3 1/2 molida", "1 1/2 callo", "2,5 kilos" | `quantity: "3.5"` |
| Sin cantidad | "me manda costilla por favor" | `quantity: ""`, `missing_info: ["quantity"]`, **imprime con alerta ⚠ CANTIDAD FALTANTE** |

### 5.3 Notas de preparación (van en `items[].notes`)

blandita, bien picada / picado pequeño, bien cortada / bien cortaditos, gruesa / delgada, para asar / para sudar / para desmechar / para tajar / para caldo / para sopa / para moler, sin grasa / "que no esté gorda", bien fresca, sin madurar, bien bonita / carnuda, empacada al vacío, en bolsas separadas ("20 y 20 aparte", "me las divide en dos bolsas", "15 y 15 aparte"), pierna no brazo, deshuesada, con hueso / "que lleve hueso", en bloque / entera, en rodajas, corte más grande / más pequeño, "como la vez pasada" / "como las que siempre me manda".

### 5.4 Entrega / recogida (`order_type`)

- **delivery:** "me envía", "me manda", "me lo manda", "para domicilio", dirección escrita, pin de Maps, punto de entrega con nombre ("donde los [DROP_POINT]", "donde don [DROP_POINT]", "a [BUSINESS_NAME]", "al fruver de la esquina"), "me podrías traer".
- **pickup:** "paso en 20 minutos", "ya pasan a recoger", "paso por eso", "lo recojo", "voy en camino", "a las 8 paso", "me pueden ir preparando" (cliente que recoge), "ahora paso".
- **unknown:** ninguno de los anteriores → NO bloquea; el negocio conoce la rutina del cliente. Ticket muestra "Entrega: habitual".
- **Multi-destino:** un mismo bloque puede traer dos pedidos: "para [BUSINESS_NAME_1]: …" y "para [BUSINESS_NAME_2]: …" → generar **dos pedidos/tickets**, uno por destino, con `business_name` correspondiente.

### 5.5 Dirección

- Si viene explícita ("Carrera XX # XX-XX en [BARRIO]", "Calle XX n X-XX …") → `delivery_address` literal.
- Si es punto conocido ("donde los [DROP_POINT]", "donde don [DROP_POINT]") → `delivery_address` = ese texto literal.
- Si no viene → `delivery_address: ""`, ticket imprime `ENTREGA: dirección habitual / cliente conocido`. **Nunca** enviar a revisión manual por falta de dirección (decisión confirmada).

### 5.6 Fecha y hora solicitada

- "para hoy", "para mañana", "para el sábado", "*PEDIDO* para el 27/03/2026", "para mañana viernes 13 feb" → `requested_time` con lo literal + fecha resuelta si es inequívoca.
- Horas: "a las 10 de la mañana", "antes del medio día", "temprano por favor", "lo más temprano posible", "después de las 9", "sobre las 11 30".
- Sin mención → `requested_time: ""` (no bloquea; en la operación real "para hoy" es el default).

### 5.7 Pago (`payment_method`)

- Mencionan: Nequi, Daviplata, transferencia / TransfiYa, efectivo, "pago contra entrega", "le envío comprobante", pagos parciales ("te envío 300 y el resto en efectivo"), factura semanal.
- Si el pedido no menciona pago → `payment_method: "unknown"` (normal: la mayoría paga por factura o al recibir). No bloquea.
- Si menciona pago dentro del pedido → registrar y añadir nota ("cliente anuncia pago por Nequi").

---

## 6. Información faltante — política por campo

| Campo faltante | Acción | ¿Bloquea impresión? |
|----------------|--------|---------------------|
| Cantidad | `missing_info +["quantity"]`, ticket con **⚠ CANTIDAD FALTANTE** junto al ítem | No |
| Unidad | `unit:"unknown"`, mostrar `raw_text` del ítem | No |
| Gramaje de porciones | nota "gramaje no indicado" (la carnicería suele preguntar) | No |
| Entrega/recogida | `order_type:"unknown"` → "Entrega: habitual" | No |
| Dirección | "dirección habitual / cliente conocido" | No |
| Hora | vacío (default operativo: hoy / próximo recorrido) | No |
| Método de pago | `"unknown"` | No |
| Producto ininteligible | Ese ítem con `product_name:""` + alerta; si TODO el pedido es ininteligible → revisión manual | Solo si todo el bloque es ininteligible |

---

## 7. Ambigüedad, confianza y revisión manual

### 7.1 Escala de confianza

- **0.95–1.0** — Lista clara con cantidades y productos del catálogo (formato *PEDIDO*, listas "5 kilos de X").
- **0.80–0.94** — Pedido claro con unidades implícitas o jerga conocida ("6 asar", "5x5 de molida", "120.000 de sobrebarriga").
- **0.75–0.79** — Pedido probable con 1–2 ítems dudosos o cantidad faltante → imprime con alertas.
- **< 0.75** — No auto-imprimir → revisión manual.

### 7.2 Casos que imprimen CON ALERTA (no bloquean)

| Caso | Alerta en ticket |
|------|------------------|
| Producto sin cantidad | `⚠ CANTIDAD FALTANTE` |
| "Lo mismo de siempre" / "el mismo favor de hace 8 días" | `⚠ PEDIDO DE SIEMPRE — VERIFICAR HISTORIAL` (ítem único: `raw_text` = la frase, `product_name: "REFERENCIA A PEDIDO ANTERIOR"`) |
| Plata como cantidad ambigua ("150 de costilla") | `⚠ VERIFICAR: interpretado como $150.000` |
| Unidad ambigua ("chorizo 30" — ¿unidades o paquetes?) | `⚠ UNIDAD SIN CONFIRMAR` |
| Gramaje faltante en porciones | `⚠ GRAMAJE NO INDICADO` |

### 7.3 Sesión de pedido y adiciones (decisión confirmada: reimprimir completo)

- Roni mantiene **estado del pedido abierto** por cliente: misma sesión = mismo cliente + mismo día (o hasta que el pedido se marque despachado/cerrado en el POS).
- Si llega una **adición** ("Me agrega 8 libras de costilla", "Y 4 patas por favor", "Y me puede traer 6 churrascos de 250 g") o una **corrección** ("era carne para desmechar, no porcionada"):
  1. Fusionar con los ítems de la sesión abierta.
  2. Reimprimir el pedido **completo actualizado** con encabezado `🔄 PEDIDO ACTUALIZADO — REEMPLAZA EL ANTERIOR` y hora del pedido original.
  3. `is_addendum: true` en el JSON del evento.
- Si no hay sesión abierta ese día, tratar "me agrega X" como pedido nuevo con alerta `⚠ POSIBLE ADICIÓN — no se encontró pedido previo hoy`.

### 7.4 Revisión manual obligatoria (`requires_manual_review = true`, NO imprime)

- Confianza < 0.75 o todo el bloque ininteligible.
- **Solicitudes de favores de facturación** (visto en chats: "me regala una factura por este valor y estos productos con fecha de hoy", "facturar 500 pesos todos los productos") → NUNCA procesar automático; `reason: "solicitud de factura especial — atención humana"`.
- Negociaciones de precio/crédito ("¿puedo abonar la mitad y la otra parte el lunes?").
- Pedidos condicionados sin resolución ("si trabajan mañana le encargo…" cuando no se sabe si se trabaja).
- Solicitudes de productos que el negocio no maneja (pescado, insumos de terceros) como pedido principal.
- Mensajes que piden coordinar con terceros (pollo de otro proveedor, transportador externo).

---

## 8. Reglas de auto-impresión

Imprimir automáticamente **solo si TODO esto se cumple**:

```
is_order == true
AND confidence >= 0.75
AND items.length > 0
AND requires_manual_review == false
```

Nunca imprimir cuando el bloque es únicamente: saludo, pregunta de precio, pregunta de disponibilidad sin pedido, confirmación de pago, pregunta de saldo, solicitud de factura, estado de entrega, aviso de llegada, agradecimiento, queja sin re-pedido, `*RECIBO*`, o mensaje confuso.

Recordatorios:
- Cantidad faltante, unidad desconocida, dirección faltante y pago desconocido **no** bajan `requires_manual_review`; imprimen con alerta (§7.2).
- Las adiciones reimprimen el pedido completo (§7.3), no un ticket parcial.
- Mensajes mixtos pago+pedido imprimen (solo la parte de pedido).

---

## 9. Esquema JSON

```json
{
  "is_order": true,
  "confidence": 0.0,
  "customer_name": "",
  "customer_phone": "",
  "business_name": "",
  "order_type": "delivery | pickup | unknown",
  "payment_method": "cash | nequi | daviplata | card | transfer | unknown",
  "delivery_address": "",
  "requested_date": "",
  "requested_time": "",
  "items": [
    {
      "raw_text": "",
      "product_name": "",
      "quantity": "",
      "unit": "kg | lb | unit | package | pesos | unknown",
      "notes": ""
    }
  ],
  "customer_notes": "",
  "missing_info": [],
  "is_addendum": false,
  "mixed_content": [],
  "requires_manual_review": false,
  "reason": "",
  "print_text": ""
}
```

Campos añadidos sobre el esquema base y por qué:

- `business_name` — clientes piden para negocios/destinos con nombre ("para [BUSINESS_NAME_1] … y para [BUSINESS_NAME_2] …"); es el dato de enrutamiento más usado en los chats.
- `requested_date` — separado de la hora; "para mañana" es el patrón más común.
- `unit: "pesos"` — cantidad expresada en plata ("120.000 de sobrebarriga").
- `is_addendum` — marca las adiciones/correcciones que disparan reimpresión completa (§7.3).
- `mixed_content` — etiquetas de contenido extra en el bloque: `["payment_report", "complaint", "balance_request", "invoice_request", "greeting"]`.
- `missing_info` — valores posibles: `"quantity"`, `"unit"`, `"gramaje"`, `"address"`, `"order_type"`, `"requested_time"`, `"payment_method"`, `"product"`.

---

## 10. Formato de ticket térmico

Texto plano, ancho 32 caracteres (impresora 58 mm; usar 42 si es de 80 mm). Sin tildes problemáticas si la impresora no soporta CP-850 (configurable).

```
================================
     CARNES DON DAVID
       PEDIDO WHATSAPP
================================
Fecha: 27/06/2026  Hora: 07:41
Cliente: [CUSTOMER_NAME]
Tel: [PHONE]
Negocio: [BUSINESS_NAME]
--------------------------------
PRODUCTOS
--------------------------------
5 kg    Espaldilla
5 kg    Muchacho
5 kg    Panceta
20 und  Chorizo
        ⚠ UNIDAD SIN CONFIRMAR
$120.000 Sobrebarriga
1 ??    Costilla
        ⚠ CANTIDAD FALTANTE
--------------------------------
NOTAS: carne bien blandita,
en dos bolsas aparte
--------------------------------
ENTREGA: domicilio
DIR: donde los [DROP_POINT]
PARA: manana / temprano
PAGO: nequi (anuncia envio)
--------------------------------
FALTA: cantidad (costilla)
CONFIANZA: 0.86
--------------------------------
MENSAJE ORIGINAL:
"Buenos dias por favor me
despache para [BUSINESS_NAME]
5 kilos de espaldilla..."
================================
```

Reglas del ticket:

1. Encabezado con fecha/hora de recepción, cliente, teléfono, negocio (si aplica).
2. Un renglón por ítem: `cantidad + unidad + producto`; alertas ⚠ debajo del ítem afectado.
3. Bloque ENTREGA: tipo, dirección (o `direccion habitual / cliente conocido`), fecha/hora pedida.
4. Bloque PAGO solo si el cliente lo mencionó; si no, omitir o `PAGO: --`.
5. Bloque FALTA con los `missing_info`.
6. **Siempre** cerrar con `MENSAJE ORIGINAL:` y el texto completo del bloque (decisión confirmada) — permite al personal verificar la interpretación de jerga como "5x5 de molida".
7. Pedidos actualizados: primera línea `>>> PEDIDO ACTUALIZADO <<<` + `REEMPLAZA TICKET DE LAS hh:mm`.
8. Multi-destino: un ticket por destino.

---

## 11. Casos de prueba (reales, anonimizados)

### A. Pedidos claros → imprimir

**A1 — rutina diaria, unidades implícitas** (bloque de 5 mensajes)
```
Buen día / Para hoy / 6 asar / 2 molida / Gracias
```
→ `is_order:true`, 2 ítems (`unit:"unknown"`), `order_type:"unknown"`, confianza ~0.85, imprime.

**A2 — semanal, jerga de bolsas**
```
Buenas noches doña [CUSTOMER_NAME]...para mañana 24 libras de goulash,
5x5 de molida, 3y3 de hueso, 4 churrascos, 30 porciones de cerdo por favor
```
→ 5 ítems; "5x5 de molida" = `package`, notes "5 bolsas de 5 libras"; `requested_date: mañana`; imprime.

**A3 — formato B2B**
```
*PEDIDO* 26/06/2026
• 40 kilos de carne para moler
*Por favor empacar en bolsas en 10 kilos*
```
→ 1 ítem 40 kg, notes "empacar en bolsas de 10 kilos"; confianza 0.98; imprime.

**A4 — porciones con gramaje**
```
Buenos días / 10 churrascos 270gr / 8 libras costilla caldo /
25 porciones sobre barriga 120gr / Me alistas esto por favor
```
→ 3 ítems, gramaje en notes; probable pickup (cliente que "pasa"); imprime.

**A5 — plata como cantidad**
```
Para hoy / 5 Asar / 120.000 de sobre barriga / Gracias
```
→ ítem 2: `quantity:"120000"`, `unit:"pesos"`; imprime (sin alerta: patrón claro).

**A6 — mixto pago + pedido**
```
Buenos días, mira te envío 540.000 mi mamá te da el resto en efectivo
y te puedo pedir favor si me alistas 10 kilos de molida por favor
```
→ `is_order:true`, 1 ítem 10 kg molida; `mixed_content:["payment_report"]`; nota "cliente reporta pago $540.000 + resto en efectivo"; imprime.

**A7 — queja + pedido**
```
Por favor 30 porciones de carne para asar. BLANDITA POR FAVOR
la última me salió dura 🙏🏽
```
→ 1 ítem con notes "blandita; queja: la última salió dura"; `mixed_content:["complaint"]`; imprime.

**A8 — multi-destino**
```
Negra: 5 kilos de espaldilla / 5 kilos de muchacho / 20 chorizos
Sitio: 3 kilos de muchacho
```
→ **2 pedidos**: `business_name:"[BUSINESS_NAME_1]"` (3 ítems) y `business_name:"[BUSINESS_NAME_2]"` (1 ítem); 2 tickets.

**A9 — domicilio explícito con hora**
```
Me podría ayudar a programar un domicilio para el día de mañana porfa.
Necesito porciones de 200 gramos cada una de churrasco bien bonitos,
necesito 30 porciones. Y carne molida (milanesa o cadera) 4 kilos.
El domicilio sería para la [ADDRESS] cerca de la estación [LANDMARK]
```
→ delivery, `delivery_address:"[ADDRESS]"`, `requested_date:"mañana"`; 2 ítems; imprime.

### B. Con alerta → imprimir marcado

**B1 — sin cantidad**
```
Por favor me puedes enviar costilla donde don [DROP_POINT]
```
→ 1 ítem costilla `quantity:""`, `missing_info:["quantity"]`; ticket con ⚠ CANTIDAD FALTANTE; imprime.

**B2 — lo de siempre**
```
Doña [CUSTOMER_NAME] buenas tardes...para mañana el mismo favor de hace 8 días
```
→ ítem único `product_name:"REFERENCIA A PEDIDO ANTERIOR"`; ⚠ PEDIDO DE SIEMPRE — VERIFICAR HISTORIAL; imprime.

**B3 — unidad ambigua**
```
Chorizo 30
```
(dentro de una lista) → `unit:"unknown"` + ⚠ UNIDAD SIN CONFIRMAR (en los chats la vendedora tuvo que preguntar "¿30 unidades o 30 paquetes?").

**B4 — adición con sesión abierta** (pedido A8 impreso hace 20 min)
```
Y me puede traer 6 churrasco de 250 g
```
→ `is_addendum:true`; fusionar y reimprimir pedido completo con `>>> PEDIDO ACTUALIZADO <<<`.

### C. No-pedidos → NO imprimir

**C1** `Hola buenas tardes cómo estás` → saludo.
**C2** `¿A cómo está el churrasco?` → precio.
**C3** `<Multimedia omitido> / Buenas tardes está el pago de esta factura` → confirmación de pago.
**C4** `¿Cuánto te debo? me envías el valor por fa` → saldo.
**C5** `*RECIBO* 25/03/2026 • 20 kilos de carne` → confirmación de recibido (¡contiene cantidades y producto — NO confundir con pedido! La palabra RECIBO/RECIBIDO manda).
**C6** `Estoy aquí` + pin de ubicación → aviso de llegada.
**C7** `¿A qué horas llegan?` → estado de entrega.
**C8** `Muchas gracias…!!` → agradecimiento.
**C9** `Hoy tienes disponible sobrebarriga?` (sin seguimiento en la ventana) → disponibilidad; si en la misma ventana llega "55 porciones por favor", el bloque completo SÍ es pedido.

### D. Revisión manual → NO imprimir

**D1 — favor de facturación**
```
Necesito que me colabore con una factura: carne cerdo 50 kilos 12.500 = 1.250.000 …
Total factura = 2.170.000
```
→ `requires_manual_review:true`, `reason:"solicitud de factura especial — requiere atención humana"`. Tiene forma de lista de productos pero NO es pedido de mercancía.

**D2 — negociación de crédito**
```
¿Le puedo realizar un pedido el día de mañana, abonar la mitad y la otra parte el lunes?
```
→ pregunta condicionada de pago, sin lista aún; manual (`reason:"negociación de pago"`).

**D3 — ininteligible / incompleto**
```
Hola / Será que / <Multimedia omitido>
```
→ confianza < 0.75; manual o descartar como no-pedido según contenido.

---

## 12. Privacidad

- Nunca copiar a logs o ejemplos: nombres reales, teléfonos, direcciones exactas, correos, números de cuenta/Nequi.
- En tickets impresos (uso interno) sí van el nombre y teléfono reales del cliente — el ticket es operativo, no público.
- En cualquier material de entrenamiento/pruebas usar los placeholders `[CUSTOMER_NAME]`, `[PHONE]`, `[ADDRESS]`, `[BUSINESS_NAME]`, `[DROP_POINT]`.
