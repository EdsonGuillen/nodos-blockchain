# Nodo Blockchain — Laravel

## IP ZeroTier: 10.158.86.29
## Puerto: 8004

## Endpoints
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | /chain | Cadena completa |
| POST | /mine | Minar bloque |
| POST | /transactions | Nueva transacción |
| POST | /transactions/receive | Recibir transacción |
| POST | /blocks/receive | Recibir bloque |
| POST | /block | Recibir bloque (alias) |
| POST | /nodes/register | Registrar nodo (acepta {url} o {nodes:[]}) |
| GET | /nodes/resolve | Consenso |

## Registrar este nodo en otros
POST /nodes/register
{"nodes": ["http://10.158.86.29:8004"]}

## Levantar servidor
php artisan serve --host=0.0.0.0 --port=8004
