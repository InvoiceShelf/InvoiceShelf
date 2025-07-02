#!/bin/bash

# Script para trabajar con la documentación de InvoiceShelf
# Uso: ./docs.sh [comando]

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para mostrar ayuda
show_help() {
    echo -e "${BLUE}InvoiceShelf Documentation Helper${NC}"
    echo ""
    echo "Comandos disponibles:"
    echo "  install     Instalar dependencias de MkDocs"
    echo "  serve       Ejecutar servidor de desarrollo"
    echo "  build       Construir documentación estática"
    echo "  deploy      Desplegar a GitHub Pages"
    echo "  clean       Limpiar archivos generados"
    echo "  help        Mostrar esta ayuda"
    echo ""
    echo "Ejemplos:"
    echo "  ./docs.sh install"
    echo "  ./docs.sh serve"
    echo "  ./docs.sh build"
}

# Función para instalar dependencias
install_deps() {
    echo -e "${YELLOW}Instalando dependencias de MkDocs...${NC}"
    
    # Verificar si Python está instalado
    if ! command -v python3 &> /dev/null; then
        echo -e "${RED}Error: Python 3 no está instalado${NC}"
        exit 1
    fi
    
    # Instalar dependencias
    pip3 install -r docs-requirements.txt
    
    echo -e "${GREEN}✓ Dependencias instaladas correctamente${NC}"
}

# Función para servir documentación
serve_docs() {
    echo -e "${YELLOW}Iniciando servidor de desarrollo...${NC}"
    echo -e "${BLUE}La documentación estará disponible en: http://127.0.0.1:8000${NC}"
    echo ""
    mkdocs serve
}

# Función para construir documentación
build_docs() {
    echo -e "${YELLOW}Construyendo documentación estática...${NC}"
    mkdocs build
    echo -e "${GREEN}✓ Documentación construida en ./site/${NC}"
}

# Función para desplegar
deploy_docs() {
    echo -e "${YELLOW}Desplegando a GitHub Pages...${NC}"
    mkdocs gh-deploy
    echo -e "${GREEN}✓ Documentación desplegada${NC}"
}

# Función para limpiar
clean_docs() {
    echo -e "${YELLOW}Limpiando archivos generados...${NC}"
    rm -rf site/
    echo -e "${GREEN}✓ Archivos limpiados${NC}"
}

# Verificar argumentos
if [ $# -eq 0 ]; then
    show_help
    exit 1
fi

# Procesar comandos
case $1 in
    install)
        install_deps
        ;;
    serve)
        serve_docs
        ;;
    build)
        build_docs
        ;;
    deploy)
        deploy_docs
        ;;
    clean)
        clean_docs
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo -e "${RED}Error: Comando desconocido '$1'${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac
