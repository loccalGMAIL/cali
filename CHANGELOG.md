# Changelog

Todos los cambios importantes de Cali se registran en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el versionado
es [semántico](https://semver.org/lang/es/): `MAJOR.MINOR.PATCH`. Mientras la versión sea `0.x`
el sistema está en desarrollo y puede haber cambios que rompan la compatibilidad; la `1.0.0`
llegará con la salida a producción.

La versión vigente también está en `config/cali.php` (`version`) y se muestra en el pie del
panel. Al publicar una versión nueva hay que actualizar las tres cosas: este archivo, esa
configuración y el tag de git (`vX.Y.Z`).

## [Sin publicar]

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

[Sin publicar]: https://github.com/loccalGMAIL/cali/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/loccalGMAIL/cali/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/loccalGMAIL/cali/releases/tag/v0.1.0
