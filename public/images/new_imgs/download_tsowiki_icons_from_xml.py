#!/usr/bin/env python3
"""Скачивает иконки из XML в папки по разделам tsowiki.

Использует значения filename, iconfilename и buffIcon для поиска на tsowiki,
но сохраняет найденный файл как <атрибут name в lowercase>.webp.
Папка other не используется.

Пример:
<Adventure name="TheValiantLittleTailor" buffIcon="the_valiant_little_tailor.png"/>
будет скачан как the_valiant_little_tailor.webp и сохранён как
adventures/thevaliantlittletailor.webp.

Запуск:
    python download_tsowiki_icons_from_xml.py icons.xml adventures.xml
"""

from __future__ import annotations

import argparse
import shutil
import sys
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from collections import defaultdict
from pathlib import Path

CATEGORIES = (
    "buffs", "buildings", "resources", "adventures", "units", "merchant",
    "specialists", "achievements", "custom", "custom2", "events",
)
ICON_ATTRIBUTES = ("filename", "iconfilename", "buffIcon")
TIMEOUT_SECONDS = 20


def collect_icons_by_name(xml_files: list[Path]) -> dict[str, list[str]]:
    """Возвращает {name_lowercase: [имена файлов для поиска без расширения]}."""
    result: dict[str, list[str]] = defaultdict(list)

    for xml_file in xml_files:
        try:
            root = ET.parse(xml_file).getroot()
        except (ET.ParseError, OSError) as error:
            print(f"Предупреждение: XML пропущен — {xml_file} ({error})")
            continue

        for element in root.iter():
            name = element.get("name")
            if not name:
                continue
            output_stem = name.casefold()

            for attribute in ICON_ATTRIBUTES:
                value = element.get(attribute)
                if not value:
                    continue
                icon_stem = Path(value).stem
                if icon_stem and icon_stem not in result[output_stem]:
                    result[output_stem].append(icon_stem)

    return dict(result)


def download(url: str, target: Path) -> tuple[bool, str]:
    """Скачивает изображение; недокачанный файл при ошибке удаляется."""
    request = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    try:
        with urllib.request.urlopen(request, timeout=TIMEOUT_SECONDS) as response:
            content_type = response.headers.get_content_type()
            if response.status != 200:
                return False, f"HTTP {response.status}"
            if not content_type.startswith("image/"):
                return False, f"не изображение: {content_type}"
            with target.open("wb") as output:
                shutil.copyfileobj(response, output)

        if target.stat().st_size == 0:
            target.unlink(missing_ok=True)
            return False, "пустой файл"
        return True, ""
    except urllib.error.HTTPError as error:
        return False, f"HTTP {error.code}"
    except urllib.error.URLError as error:
        return False, f"ошибка сети: {error.reason}"
    except TimeoutError:
        return False, "тайм-аут"
    except OSError as error:
        return False, f"ошибка файла: {error}"


def main() -> int:
    parser = argparse.ArgumentParser(description="Скачать иконки tsowiki, перечисленные в XML")
    parser.add_argument("xml_files", nargs="+", type=Path, help="Один или несколько XML-файлов")
    parser.add_argument(
        "--base-dir", type=Path, default=Path(__file__).resolve().parent,
        help="Куда создавать папки категорий; по умолчанию — папка скрипта",
    )
    parser.add_argument(
        "--overwrite", action="store_true",
        help="Скачивать заново, если файл уже существует",
    )
    args = parser.parse_args()

    icons_by_name = collect_icons_by_name(
        [path.expanduser().resolve() for path in args.xml_files]
    )
    if not icons_by_name:
        print("Ошибка: в XML не найдены записи с name и filename/iconfilename/buffIcon.")
        return 1

    base_dir = args.base_dir.expanduser().resolve()
    downloaded = existing = missing = 0

    for output_stem, icon_stems in icons_by_name.items():
        output_name = f"{output_stem}.webp"
        found = False

        for icon_stem in icon_stems:
            encoded_stem = urllib.parse.quote(icon_stem)
            for category in CATEGORIES:
                target = base_dir / category / output_name
                if target.exists() and not args.overwrite:
                    print(f"Уже есть: {category}/{output_name}")
                    existing += 1
                    found = True
                    break

                target.parent.mkdir(parents=True, exist_ok=True)
                url = "https://tsowiki.eu/icons/" + category + "/" + encoded_stem + ".webp"
                success, _detail = download(url, target)
                if success:
                    print(f"Скачано: {url} -> {category}/{output_name}")
                    downloaded += 1
                    found = True
                    break
                target.unlink(missing_ok=True)
            if found:
                break

        if not found:
            print(f"Не найдено на tsowiki: {output_name} (варианты: {', '.join(icon_stems)})")
            missing += 1

    print(f"\nГотово. Скачано: {downloaded}; уже было: {existing}; не найдено: {missing}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
