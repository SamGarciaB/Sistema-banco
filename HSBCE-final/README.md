# HSBCE-final

Este es un prototipo de portafolio: una página temática con un formulario adaptable y un backend de ejemplo en PHP y MySQL. Sirve para recorrer la experiencia y ver cómo se conectan el formulario, la API, Turnstile y la base de datos.

No es un producto de HSBC ni de FIFA. Los textos, beneficios y datos son ilustrativos; las marcas pertenecen a sus titulares.

## Tecnologías

- PHP 8.1+
- MySQL 8 o compatible
- HTML, CSS y JavaScript
- Cloudflare Turnstile
- Apache con `mod_rewrite` y `mod_headers`

## Carpetas

- `.env.example`: plantilla para la configuración local.
- `lib/`: conexión a la base, configuración y utilidades del servidor.
- `public/`: página, estilos, JavaScript y endpoints de la API.

## Probarlo en local

Necesitas Apache, PHP con PDO MySQL, cURL y mbstring, una base MySQL y un widget de prueba de Turnstile.

1. Configura Apache para que publique la carpeta `public/`.
2. Crea una base de desarrollo con las tablas `solicitudes` y `nonces`. El esquema SQL no viene incluido en este repositorio.
3. Copia `.env.example` como `.env` en la raíz del proyecto y completa los valores para tu entorno local.
4. Genera `HMAC_KEY` con `openssl rand -hex 32` y usa las claves de prueba de Turnstile para `localhost`.
5. Inicia Apache y abre la dirección local configurada en `APP_URL`.

El archivo `.env.example` trae solo valores de muestra y se puede compartir. El archivo `.env` contiene la configuración de tu entorno: mantenlo fuera de Git.

## Qué hace la API

- `GET /api/token.php` entrega un nonce y una firma temporal para el formulario.
- `POST /api/save.php` valida los campos y la respuesta de Turnstile antes de guardar la solicitud.
- También aplica límites de frecuencia, idempotencia y consultas preparadas.

## Demo de portafolio

[Ver la demo pública](https://samgarciab.github.io/Sistema-banco/)

Esta versión estática sirve para probar el formulario y ver su diseño. La confirmación es simulada: no llama a la API PHP, no necesita una base de datos y no envía ni guarda lo que escribas. Usa solo datos ficticios.

La demo es pública y cualquiera con el enlace puede abrirla o compartirla. No es un acceso privado para reclutadores. La carpeta `docs/` contiene los archivos que publica GitHub Pages; el backend está en `HSBCE-final/public/api/`.

## Sobre los datos y el uso

El backend guarda en MySQL los campos que recibe y no los cifra desde la aplicación. Para las pruebas, usa datos ficticios y no lo conectes a solicitudes reales. Un uso real también necesitaría definir cómo se obtienen el consentimiento, el acceso, el almacenamiento, la retención y la eliminación de los datos.

El proyecto no está listo para ofrecer servicios financieros ni representa a HSBC o FIFA. Antes de cualquier uso fuera de una prueba local, habría que revisar la configuración del servidor, la seguridad, la privacidad y los permisos de marca.
