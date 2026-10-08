#!/usr/bin/env python3
"""Exports the iOS String Catalog to the Android app's translation tables.

The Android app uses the same French source strings as keys (like SwiftUI), so the catalog stays the single
source of truth: re-run this after adding strings on iOS, or add missing keys to the Android overrides file.
Output: app/src/main/assets/l10n/<lang>.json  ({ "French source": "translation" }).
"""
import json, pathlib, sys

root = pathlib.Path(__file__).resolve().parents[2]
catalog = json.loads((root / "ios/AssurPlus/Resources/Localizable.xcstrings").read_text())
out_dir = root / "android/app/src/main/assets/l10n"
overrides_path = root / "android/tools/strings-overrides.json"
overrides = json.loads(overrides_path.read_text()) if overrides_path.exists() else {}

langs = {"en"}
tables = {lang: {} for lang in langs}
for key, entry in catalog["strings"].items():
    for lang in langs:
        unit = entry.get("localizations", {}).get(lang, {}).get("stringUnit")
        if unit and unit.get("value"):
            tables[lang][key] = unit["value"]
for lang, extra in overrides.items():
    tables.setdefault(lang, {}).update(extra)

out_dir.mkdir(parents=True, exist_ok=True)
for lang, table in tables.items():
    (out_dir / f"{lang}.json").write_text(json.dumps(dict(sorted(table.items())), ensure_ascii=False, indent=1))
    print(f"{lang}: {len(table)} strings", file=sys.stderr)
