# Changelog

Todos los cambios importantes de Cali se registran en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el versionado
es [semántico](https://semver.org/lang/es/): `MAJOR.MINOR.PATCH`. Mientras la versión sea `0.x`
el sistema está en desarrollo y puede haber cambios que rompan la compatibilidad; la `1.0.0`
llegará con la salida a producción.

La versión vigente también está en `config/cali.php` (`version`) y se muestra en la barra
superior del panel y en el login. Al publicar una versión nueva hay que actualizar las tres cosas: este archivo, esa
configuración y el tag de git (`vX.Y.Z`).

## [Sin publicar]

## [0.5.0] - 2026-11-12

No requiere migración. Las ventas y cuotas ya creadas no cambian.

### Cambiado
- **Todas las cuotas vencen el día 30** de cada mes (en febrero, el último día: 28 o 29).
- En la venta, la primera cuota se elige por **mes** (lista de vencimientos de los próximos 24 meses,
  por defecto el mes siguiente) en lugar de una fecha libre; las siguientes vencen el día 30 de cada
  mes consecutivo.

## [0.4.0] - 2026-11-12

Requiere correr `php artisan migrate` (agrega `freight` a `products`, `unit_freight` a `sale_items`
y `total_freight` a `sales`, todas con 0 por defecto: las ventas existentes no cambian).

### Agregado
- **Flete** en los productos (opcional, por unidad). Se ve en la tabla de productos.
- Venta: cada ítem tiene **flete unitario**, que se completa con el flete del producto al elegirlo
  (se puede cambiar). Precio unitario = costo + flete + margen.
- El resumen de la venta y su ficha muestran el flete por separado del costo y del margen.

- **Código automático** de producto: si al crear uno no se carga código, el sistema le asigna el
  siguiente correlativo `PROD-00001`, `PROD-00002`, … (los códigos cargados a mano se respetan).

### Cambiado
- La **versión del sistema** se muestra en la barra superior (antes en el pie) y una sola vez en
  el login (aparecía duplicada); se quitó la palabra «Cali».
- Se quitó el texto de ayuda del campo «Proveedor habitual» en el formulario de productos.
- **Cuotas**: (costo + flete + margen − anticipo) ÷ cantidad de cuotas. El flete integra el valor
  del producto y se divide en las cuotas junto con el resto.
- La porción de costo de cada cuota (lo que no es ganancia) incluye el flete.

## [0.3.0] - 2026-11-10

Requiere correr `php artisan migrate` (agrega la columna `is_default_seller` a `users`).

### Agregado
- **Borrar usuarios** desde la tabla y desde la edición. No se puede borrar a uno mismo ni a un
  usuario con ventas, cobros u operaciones de compra registradas (se avisa que hay que desactivarlo).
- **Vendedor por defecto**: se marca desde la tabla de usuarios o en su ficha (solo puede haber
  uno). Es el que se propone al cargar una venta; un vendedor sigue cargando siempre a su nombre.
- Dashboard: acceso directo **Nueva Venta**.
- Venta: botón **+** al lado del selector de producto para dar de alta uno nuevo sin salir de la
  venta (solo para quien puede crear productos).

### Cambiado
- Formulario de venta reordenado: primero fecha, cliente y vendedor; después los productos; y en
  **Pago** el plan de financiación, la fecha de la primera cuota, el anticipo y su medio de pago.

## [0.2.0] - 2026-11-09

Cambia el circuito de compras: el negocio no maneja stock. Primero se vende y después se le
compra al proveedor lo vendido. **Requiere recrear la base** (`php artisan migrate:fresh --seed`):
se editaron las migraciones originales y se pierden los datos cargados.

### Agregado
- **Pendientes de compra**: todo lo vendido queda pendiente hasta que se incluye en una orden de
  compra. Lista con filtro por proveedor habitual y acción para armar una orden con lo elegido.
- **Órdenes de compra** por proveedor: se arman eligiendo qué ítems vendidos comprar (o con
  "Agregar pendientes de este proveedor"), se editan mientras no tengan factura, se emiten, se
  imprimen para llevar al proveedor y se anulan (lo pedido vuelve a pendiente).
- Proveedor habitual en cada producto.
- Dashboard: "Pendiente de compra", "Órdenes sin recibir" y tabla de pendientes de compra.

### Cambiado
- **Venta**: cada ítem se carga con costo y margen; precio = costo + margen. El costo se sugiere
  con el de la última factura de compra y se puede cambiar.
- **Cuotas**: (costo + margen − anticipo) ÷ cantidad de cuotas. Los planes de financiación ya no
  tienen recargo: solo definen la cantidad de cuotas.
- **Factura de compra**: se carga desde una orden de compra con lo pedido precargado. Se corrige lo
  que llegó y el faltante vuelve a pendiente (se completan primero las ventas más antiguas). El
  costo de la factura queda como último costo del producto; las ventas ya hechas no cambian.
- Anular una factura deja la orden emitida otra vez. Anular una venta la saca de pendientes (lo que
  ya estaba en una orden queda en la orden).
- El menú "Stock" pasa a llamarse "Compras".
- Datos de demostración armados con el circuito nuevo: órdenes semanales por proveedor, una factura
  con faltante, una orden emitida sin factura y otra en borrador.

### Quitado
- Stock, stock mínimo, movimientos de stock y el widget de stock bajo.
- Margen %, modo de precio y precio de venta del producto.
- Recargo de los planes de financiación.

## [0.1.1] - 2026-11-09

### Agregado
- `UserSeeder`: crea (o actualiza) los usuarios administradores Claudio y Carlos Casaretto. Lo ejecuta
  `DatabaseSeeder`, por lo que quedan en cualquier instalación.
- El seeder de demostración (`DemoSeeder`) ahora también puede ejecutarse en producción para que el
  cliente vea el sistema con datos (`php artisan db:seed --class=DemoSeeder --force`). Sigue sin hacer
  nada si ya hay ventas cargadas.
- Soporte para correr detrás de un proxy con SSL (Traefik): la aplicación confía en las cabeceras `X-Forwarded-*`
  (`TRUSTED_PROXIES` en el `.env`, por defecto `*`), así los enlaces, las redirecciones y los assets
  salen con `https://`. El `.env.example` documenta los valores de producción para
  `app.cali.pez.com.ar` y `cali.pez.com.ar`.

### Cambiado
- Login: el título pasa de "Entre a su cuenta" a "Ingrese a su cuenta".

## [0.1.0] - 2026-11-09

Primera versión: el panel web de administración completo (etapa 1).

### Agregado

#### Base
- Aplicación Laravel 13 con Filament 5 y MySQL, en español (`es_AR`), zona horaria
  `America/Argentina/Buenos_Aires` y moneda en pesos (`$ 1.234,56`).
- Dos dominios sobre un mismo proyecto, configurables por `.env` (`LANDING_DOMAIN`, `PANEL_DOMAIN`):
  una landing pública y el panel. Cualquier otro host devuelve 404.
- Usuarios con rol administrador o vendedor, usuario administrador inicial por seeder y edición
  del propio perfil.
- Logo "cali." en el login y en el panel, menú ordenado (Ventas, Stock, Configuración) y tablas
  compactas en todo el panel.

#### Maestros
- Clientes, proveedores, productos y planes de financiación, con alta rápida de proveedor,
  producto y cliente desde un modal en los formularios de compra y de venta.
- Productos con precio automático (último costo más margen) o manual, y stock mínimo.
- Planes de financiación (cantidad de cuotas y recargo), ordenados por cantidad de cuotas.

#### Compras y stock
- Compras con ítems: suman stock, actualizan el último costo y recalculan el precio automático.
  No admiten un número de factura repetido para un mismo proveedor.
- Todo cambio de stock queda en un historial de movimientos.

#### Ventas en cuotas
- Cálculo de la venta: recargo del plan, cuotas redondeadas hacia abajo (la última absorbe la
  diferencia), vencimientos mensuales y porción de costo de cada cuota.
- Anticipo opcional (se cobra al vender y reduce lo que se financia).
- Formulario con vista previa en vivo del total, la cuota y el cronograma.
- La venta copia el plan y guarda el costo de cada producto al momento de venderlo, así los
  cambios posteriores no la afectan. Bloquea si no hay stock.

#### Cobros y cuenta corriente
- Cobros imputados a las cuotas de vencimiento más viejo, con pagos parciales y adelantados.
  No se puede cobrar más que la deuda.
- Elección de las cuotas a imputar: el monto se calcula con la suma de sus saldos, con un botón
  para elegir las vencidas.
- Cuenta corriente por cliente con el saldo acumulado de cada movimiento.
- Ficha del cliente: saldo, deuda, cuotas vencidas, pestañas de cuotas y de cuenta corriente, y
  acceso directo a cobrar.

#### Anulaciones
- Anulación de cobros, ventas y compras, con motivo obligatorio. Lo anulado no se borra: queda en
  el historial y se revierte su efecto en stock, cuotas, costos y cuenta corriente.
- Una venta con cobros imputados exige anular antes esos cobros. Una compra no se puede anular si
  parte de la mercadería ya se vendió.

#### Dashboard
- Total por cobrar, ganancia futura, cuotas vencidas y cobrado del mes contra el anterior.
- Gráfico de ingresos proyectados a 12 meses (monto y ganancia), tabla de cuotas vencidas con
  acción de cobrar, valor del stock, compras del mes, productos con stock bajo y últimas compras.
- Accesos directos a Cobrar, Cliente y Compra.

#### Registro de actividad
- Historial inmutable, solo para el administrador, de ingresos, salidas e intentos fallidos, de
  altas, modificaciones y bajas de los maestros (con valores antes y después) y de ventas,
  compras y cobros con sus anulaciones. Nunca guarda contraseñas.
- Filtros por usuario, evento y período.

#### Desarrollo
- Datos de demostración (`composer demo`) armados con las mismas acciones del sistema.
- Pest (suite de pruebas), Larastan (nivel 5), Pint y Laravel Boost.

### Fuera de alcance de esta versión
- App del vendedor (PWA) con login de Google y cobro en la calle.
- Portal de clientes.
- Factura electrónica.
- Devoluciones y notas de crédito.
- Intereses punitorios por mora.
- Modificar el recargo en una venta puntual.
- Importación de datos desde el Excel actual.

[Sin publicar]: https://github.com/loccalGMAIL/cali/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/loccalGMAIL/cali/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/loccalGMAIL/cali/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/loccalGMAIL/cali/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/loccalGMAIL/cali/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/loccalGMAIL/cali/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/loccalGMAIL/cali/releases/tag/v0.1.0
