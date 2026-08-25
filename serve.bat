@echo off
REM Levanta el proyecto SIEMPRE en el puerto 8088 (el que espera .env).
REM No usar "php spark serve" a secas: por defecto usa el 8080, que en esta
REM maquina lo pelean PhpStorm (Deus Cake) y otros procesos spark huerfanos,
REM y el sitio carga sin estilos o da 404.
cd /d "%~dp0"
php spark serve --host 127.0.0.1 --port 8088
