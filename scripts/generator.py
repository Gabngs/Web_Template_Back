#!/usr/bin/env python3
"""
Laravel Module Generator
========================
Genera automáticamente todos los archivos de un módulo Laravel a partir
de un archivo de configuración JSON.

Uso:
    python scripts/generator.py scripts/examples/productos.json
    python scripts/generator.py --help

Genera por módulo:
    - database/migrations/YYYY_MM_DD_HHMMSS_create_{tabla}_table.php
    - app/Models/{Modelo}.php
    - app/Filters/{Modelo}Filter.php
    - app/Http/Requests/Base{Modelo}Request.php
    - app/Http/Requests/Store{Modelo}Request.php
    - app/Http/Requests/Update{Modelo}Request.php
    - app/Http/Resources/{Modelo}Resource.php
    - app/Services/{Modelo}Service.php
    - app/Http/Controllers/Api/{Modelo}Controller.php
    - routes/modules/{tabla}.php

Requisitos:
    pip install jinja2
"""

import argparse
import json
import os
import sys
from datetime import datetime
from pathlib import Path

try:
    from jinja2 import Environment, FileSystemLoader, select_autoescape
except ImportError:
    print("Error: Jinja2 no está instalado.")
    print("Instalar con: pip install jinja2")
    sys.exit(1)


# ─── Helpers ──────────────────────────────────────────────────────────────────

def to_pascal_case(name: str) -> str:
    """productos_categorias → ProductosCategorias"""
    return "".join(word.capitalize() for word in name.split("_"))


def to_camel_case(name: str) -> str:
    """productos_categorias → productosCategorias"""
    parts = name.split("_")
    return parts[0] + "".join(word.capitalize() for word in parts[1:])


def to_kebab_case(name: str) -> str:
    """productos_categorias → productos-categorias"""
    return name.replace("_", "-")


def to_plural(name: str) -> str:
    """Pluralización simple (inglés). Para casos especiales, editar el JSON."""
    if name.endswith("y") and not name[-2] in "aeiou":
        return name[:-1] + "ies"
    if name.endswith(("s", "x", "z", "ch", "sh")):
        return name + "es"
    return name + "s"


def php_migration_type(field: dict) -> str:
    """Convierte tipo de campo JSON a método Blueprint de Laravel."""
    type_map = {
        "string":    lambda f: f"$table->string('{f['name']}'{', ' + str(f.get('length', 255)) if f.get('length') else ''})",
        "text":      lambda f: f"$table->text('{f['name']}')",
        "longtext":  lambda f: f"$table->longText('{f['name']}')",
        "integer":   lambda f: f"$table->integer('{f['name']}')",
        "bigint":    lambda f: f"$table->unsignedBigInteger('{f['name']}')",
        "decimal":   lambda f: f"$table->decimal('{f['name']}', {f.get('precision', 10)}, {f.get('scale', 2)})",
        "float":     lambda f: f"$table->float('{f['name']}')",
        "boolean":   lambda f: f"$table->boolean('{f['name']}')",
        "date":      lambda f: f"$table->date('{f['name']}')",
        "datetime":  lambda f: f"$table->dateTime('{f['name']}')",
        "timestamp": lambda f: f"$table->timestamp('{f['name']}')",
        "json":      lambda f: f"$table->json('{f['name']}')",
        "uuid":      lambda f: f"$table->uuid('{f['name']}')",
        "foreignKey": lambda f: f"$table->unsignedBigInteger('{f['name']}')",
    }
    ftype = field.get("type", "string")
    builder = type_map.get(ftype, lambda f: f"$table->string('{f['name']}')")
    line = builder(field)
    if field.get("nullable", True):
        line += "->nullable()"
    if field.get("unique", False):
        line += "->unique()"
    if "default" in field:
        default = field["default"]
        if isinstance(default, bool):
            line += f"->default({'true' if default else 'false'})"
        elif isinstance(default, str):
            line += f"->default('{default}')"
        else:
            line += f"->default({default})"
    return line + ";"


def php_cast_type(field: dict) -> str | None:
    """Retorna el cast PHP para el campo, o None si no aplica."""
    type_map = {
        "boolean":  "'boolean'",
        "integer":  "'integer'",
        "bigint":   "'integer'",
        "decimal":  "'decimal:2'",
        "float":    "'float'",
        "date":     "'date'",
        "datetime": "'datetime'",
        "json":     "'array'",
    }
    return type_map.get(field.get("type", ""), None)


def php_validation_rules(field: dict) -> str:
    """Genera reglas de validación Laravel para un campo."""
    rules = []
    ftype = field.get("type", "string")
    nullable = field.get("nullable", True)

    if not nullable:
        rules.append("'required'")
    else:
        rules.append("'nullable'")

    type_rules = {
        "string":    "'string'",
        "text":      "'string'",
        "longtext":  "'string'",
        "integer":   "'integer'",
        "bigint":    "'integer'",
        "decimal":   "'numeric'",
        "float":     "'numeric'",
        "boolean":   "'boolean'",
        "date":      "'date'",
        "datetime":  "'date'",
        "timestamp": "'date'",
        "json":      "'array'",
        "foreignKey": "'integer'",
    }
    if ftype in type_rules:
        rules.append(type_rules[ftype])

    if field.get("length"):
        rules.append(f"'max:{field['length']}'")

    if field.get("unique", False):
        rules.append(f"'unique:{field.get(\"table\", \"tabla\")},{field[\"name\"]}'")

    return "[" + ", ".join(rules) + "]"


# ─── Generator ────────────────────────────────────────────────────────────────

class LaravelModuleGenerator:
    def __init__(self, config: dict, project_root: str = "."):
        self.config = config
        self.root = Path(project_root)
        self.templates_dir = Path(__file__).parent / "templates"

        self.env = Environment(
            loader=FileSystemLoader(str(self.templates_dir)),
            autoescape=select_autoescape([]),
            trim_blocks=True,
            lstrip_blocks=True,
        )

        # Registrar helpers en el entorno Jinja2
        self.env.filters["pascal"]     = to_pascal_case
        self.env.filters["camel"]      = to_camel_case
        self.env.filters["kebab"]      = to_kebab_case
        self.env.filters["plural"]     = to_plural
        self.env.filters["mig_type"]   = php_migration_type
        self.env.filters["cast_type"]  = php_cast_type
        self.env.filters["val_rules"]  = php_validation_rules

        # Nombre derivados
        table_name      = config["tableName"]
        model_name      = config.get("modelName", to_pascal_case(table_name))
        self.ctx = {
            **config,
            "modelName":      model_name,
            "modelNameLower": to_camel_case(table_name),
            "routeName":      to_kebab_case(table_name),
            "timestamp":      datetime.now().strftime("%Y_%m_%d_%H%M%S"),
            "now":            datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        }

    def render(self, template_name: str) -> str:
        tpl = self.env.get_template(template_name)
        return tpl.render(**self.ctx)

    def write(self, rel_path: str, content: str) -> None:
        full_path = self.root / rel_path
        full_path.parent.mkdir(parents=True, exist_ok=True)
        full_path.write_text(content, encoding="utf-8")
        print(f"  ✓ {rel_path}")

    def generate_all(self) -> None:
        table   = self.ctx["tableName"]
        model   = self.ctx["modelName"]
        ts      = self.ctx["timestamp"]

        print(f"\nGenerando módulo: {model} (tabla: {table})\n")

        files = [
            (f"database/migrations/{ts}_create_{table}_table.php", "migration.jinja2"),
            (f"app/Models/{model}.php",                             "model.jinja2"),
            (f"app/Filters/{model}Filter.php",                      "filter.jinja2"),
            (f"app/Http/Requests/Base{model}Request.php",           "base_request.jinja2"),
            (f"app/Http/Requests/Store{model}Request.php",          "store_request.jinja2"),
            (f"app/Http/Requests/Update{model}Request.php",         "update_request.jinja2"),
            (f"app/Http/Resources/{model}Resource.php",             "resource.jinja2"),
            (f"app/Services/{model}Service.php",                    "service.jinja2"),
            (f"app/Http/Controllers/Api/{model}Controller.php",     "controller.jinja2"),
            (f"routes/modules/{table}.php",                         "route.jinja2"),
        ]

        for rel_path, template in files:
            content = self.render(template)
            self.write(rel_path, content)

        print(f"\n✓ Módulo {model} generado. Siguiente paso:")
        print(f"  php artisan migrate")
        print(f"  php artisan l5-swagger:generate\n")


# ─── CLI ──────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="Genera un módulo Laravel completo desde un JSON de configuración.",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Ejemplos:
  python scripts/generator.py scripts/examples/productos.json
  python scripts/generator.py scripts/examples/categorias.json --root /ruta/al/proyecto
        """,
    )
    parser.add_argument("config", help="Ruta al archivo JSON de configuración del módulo")
    parser.add_argument(
        "--root",
        default=".",
        help="Raíz del proyecto Laravel (default: directorio actual)",
    )
    args = parser.parse_args()

    config_path = Path(args.config)
    if not config_path.exists():
        print(f"Error: No se encontró el archivo de configuración: {config_path}")
        sys.exit(1)

    with open(config_path, encoding="utf-8") as f:
        config = json.load(f)

    required = ["tableName", "fields"]
    for key in required:
        if key not in config:
            print(f"Error: El JSON debe tener el campo '{key}'")
            sys.exit(1)

    generator = LaravelModuleGenerator(config, project_root=args.root)
    generator.generate_all()


if __name__ == "__main__":
    main()
