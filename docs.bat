@echo off
REM Script para trabajar con la documentación de InvoiceShelf (Windows)
REM Uso: docs.bat [comando]

setlocal enabledelayedexpansion

if "%1"=="" (
    call :show_help
    exit /b 1
)

if "%1"=="install" (
    call :install_deps
) else if "%1"=="serve" (
    call :serve_docs
) else if "%1"=="build" (
    call :build_docs
) else if "%1"=="deploy" (
    call :deploy_docs
) else if "%1"=="clean" (
    call :clean_docs
) else if "%1"=="help" (
    call :show_help
) else (
    echo Error: Comando desconocido '%1'
    echo.
    call :show_help
    exit /b 1
)

exit /b 0

:show_help
echo InvoiceShelf Documentation Helper (Windows)
echo.
echo Comandos disponibles:
echo   install     Instalar dependencias de MkDocs
echo   serve       Ejecutar servidor de desarrollo
echo   build       Construir documentación estática
echo   deploy      Desplegar a GitHub Pages
echo   clean       Limpiar archivos generados
echo   help        Mostrar esta ayuda
echo.
echo Ejemplos:
echo   docs.bat install
echo   docs.bat serve
echo   docs.bat build
exit /b 0

:install_deps
echo Instalando dependencias de MkDocs...
python -m pip install -r docs-requirements.txt
if %errorlevel% neq 0 (
    echo Error: No se pudieron instalar las dependencias
    exit /b 1
)
echo Dependencias instaladas correctamente
exit /b 0

:serve_docs
echo Iniciando servidor de desarrollo...
echo La documentación estará disponible en: http://127.0.0.1:8000
echo.
mkdocs serve
exit /b 0

:build_docs
echo Construyendo documentación estática...
mkdocs build
echo Documentación construida en ./site/
exit /b 0

:deploy_docs
echo Desplegando a GitHub Pages...
mkdocs gh-deploy
echo Documentación desplegada
exit /b 0

:clean_docs
echo Limpiando archivos generados...
if exist site rmdir /s /q site
echo Archivos limpiados
exit /b 0
