#!/usr/bin/env python3
"""Extract golden fixtures for every recipe source from the game XML pack.

Usage:
    python3 extract_sources.py <xml_root> <out_dir>

Produces one JSON per source plus a summary.
"""
import json
import os
import re
import sys
from xml.etree import ElementTree as ET


def read(path):
    with open(path, encoding="utf-8", errors="replace") as fh:
        return fh.read()


def costs_from(elem, tag, name_attr="name", count_attr="count"):
    out = []
    for c in elem.iter():
        if c.tag.lower() == tag.lower():
            n = c.get(name_attr)
            v = c.get(count_attr) or c.get("amount")
            if n is not None and v is not None:
                out.append({"resource": n, "count": int(v)})
    return out


def extract_skillpoints(path):
    root = ET.fromstring(read(path))
    out = []
    for sp in root.iter("skillPoint"):
        levels = []
        for pl in sp.findall("productionLevel"):
            levels.append({
                "amount_produced_from": int(pl.get("amountProduced", 0)),
                "production_time": int(pl.get("productionTime", 0)),
                "costs": [{"resource": c.get("name"), "count": int(c.get("count"))}
                          for c in pl.findall("cost")],
            })
        out.append({
            "id": sp.get("id"),
            "instant_finish_cost": int(sp.get("instantFinishCost", 0)),
            "reset_cost": int(sp.get("resetCost", 0)),
            "production_levels": levels,
        })
    return out


def extract_collections(path):
    root = ET.fromstring(read(path))
    out = []
    for col in root.iter("collection"):
        out.append({
            "name": col.get("name"),
            "out_buff_name": col.get("outBuffName"),
            "out_buff_resource_name": col.get("outBuffResourceName"),
            "out_buff_resource_amount": col.get("outBuffResourceAmount"),
            "production_time": int(col.get("productionTime") or 0),
            "instant_build_costs": int(col.get("InstantBuildCosts") or 0),
            "min_level": col.get("minLevel") or col.get("pLvl"),
            "max_level": col.get("maxLevel"),
            "requires_event": col.get("requiresEvent"),
            "requires_quest": col.get("requiresQuest"),
            "costs": [{"resource": r.get("name"), "count": int(r.get("amount"))}
                      for r in col.findall("resource")],
        })
    return out


def extract_military(globals_path):
    data = read(globals_path)
    out = []
    for m in re.finditer(r"<MilitaryUnit ([^>]*?)(/?)>", data):
        attrs = dict(re.findall(r'(\w+)="([^"]*)"', m.group(1)))
        if attrs.get("produceable") != "true":
            continue
        costs = []
        if m.group(2) != "/":
            end = data.find("</MilitaryUnit>", m.end())
            inner = data[m.end():end]
            costs = [{"resource": a, "count": int(b)}
                     for a, b in re.findall(r'<Cost name="([^"]+)" count="(\d+)"', inner)]
        out.append({
            "id": int(attrs.get("id", 0)),
            "type": attrs.get("type"),
            "is_elite": attrs.get("isElite") == "true",
            "production_time_seconds": int(attrs.get("productionTimeSeconds") or 0),
            "instant_build_costs": int(attrs.get("instantBuildCosts") or 0),
            "costs": costs,
            "target_production_type": 8 if attrs.get("isElite") == "true" else 0,
        })
    return out


def extract_buff_pool(globals_path):
    data = read(globals_path)
    out = []
    for m in re.finditer(r"<Buff ([^>]*?)(/?)>", data):
        attrs = dict(re.findall(r'(\w+)="([^"]*)"', m.group(1)))
        if attrs.get("produceable") != "true":
            continue
        costs = []
        if m.group(2) != "/":
            end = data.find("</Buff>", m.end())
            inner = data[m.end():end] if end > 0 else ""
            costs = [{"resource": a, "count": int(b)}
                     for a, b in re.findall(r'<Cost name="([^"]+)" count="(\d+)"', inner)]
        out.append({
            "name": attrs.get("name"),
            "group": int(attrs.get("group") or -1),
            "buff_type": attrs.get("buffType"),
            "production_time": int(attrs.get("productionTime") or 0),
            "instant_build_costs": int(attrs.get("instantBuildCosts") or 0),
            "requires_quest": attrs.get("requiresQuest"),
            "requires_event": attrs.get("requiresEvent"),
            "costs": costs,
            "costs_known": bool(costs),
        })
    return out


def extract_lists(globals_path):
    data = read(globals_path)
    out = {}
    for m in re.finditer(r'<TimedProductionList id="(\d+)"([^>]*)>', data):
        tid = int(m.group(1))
        list_type = (re.search(r'type="([^"]+)"', m.group(2)) or [None, None])
        list_type = list_type.group(1) if hasattr(list_type, "group") else None
        end = data.find("</TimedProductionList>", m.end())
        inner = data[m.end():end]
        recipes = []
        for tp in re.finditer(r"<TimedProduction ([^>]*?)(/?)>", inner):
            a = dict(re.findall(r'(\w+)="([^"]*)"', tp.group(1)))
            costs = []
            if tp.group(2) != "/":
                e2 = inner.find("</TimedProduction>", tp.end())
                body = inner[tp.end():e2] if e2 > 0 else ""
                costs = [{"resource": x, "count": int(y)}
                         for x, y in re.findall(r'<Cost name="([^"]+)" count="(\d+)"', body)]
            recipes.append({
                "name": a.get("name"),
                "group": int(a.get("group") or -1),
                "duration": int(a.get("duration") or 0),
                "instant_finish_cost": int(a.get("instantFinishCost") or 0),
                "requires_event": a.get("requiresEvent"),
                "requires_quest": a.get("requiresQuest"),
                "requires_upgrade_level_min": int(a.get("requiresUpgradeLevelMin") or 0),
                "requires_upgrade_level_max": int(a.get("requiresUpgradeLevelMax") or 99),
                "costs": costs,
            })
        out[tid] = {"list_type": list_type, "recipes": recipes}
    return out


def extract_producers(icons_path):
    data = read(icons_path)
    out = {}
    for m in re.finditer(r"<Building ([^>]*productionType=\"\d+\"[^>]*)>", data):
        a = dict(re.findall(r'(\w+)="([^"]*)"', m.group(1)))
        out[a.get("name")] = {
            "production_type": int(a["productionType"]),
            "ui": a.get("ui"),
            "ui_content": a.get("uicontent"),
            "nof_upgrades": a.get("nofUpgrades"),
        }
    return out


def main():
    xml_root, out_dir = sys.argv[1], sys.argv[2]
    os.makedirs(out_dir, exist_ok=True)
    g = os.path.join(xml_root, "gfx", "gfx_settings_a742ef6a.xml")
    i = os.path.join(xml_root, "gfx", "gfx_settings_256c6645.xml")
    sp = os.path.join(xml_root, "skill", "science_system_skillPoints.xml")
    co = os.path.join(xml_root, "collections", "collectibles_and_loot.xml")

    data = {
        "skillpoints": extract_skillpoints(sp),
        "collections": extract_collections(co),
        "military_units": extract_military(g),
        "buff_pool": extract_buff_pool(g),
        "producers": extract_producers(i),
    }
    lists = extract_lists(g)

    for key, value in data.items():
        with open(os.path.join(out_dir, key + ".json"), "w", encoding="utf-8") as fh:
            json.dump(value, fh, ensure_ascii=False, indent=2)
    with open(os.path.join(out_dir, "timed_production_lists.json"), "w", encoding="utf-8") as fh:
        json.dump(lists, fh, ensure_ascii=False, indent=2)

    summary = {
        "skillpoints": [s["id"] for s in data["skillpoints"]],
        "collections_count": len(data["collections"]),
        "military_units_count": len(data["military_units"]),
        "military_units_regular": [u["type"] for u in data["military_units"] if not u["is_elite"]],
        "military_units_elite": [u["type"] for u in data["military_units"] if u["is_elite"]],
        "buff_pool_count": len(data["buff_pool"]),
        "buff_pool_with_costs": sum(1 for b in data["buff_pool"] if b["costs_known"]),
        "buff_group_5": sum(1 for b in data["buff_pool"] if b["group"] == 5),
        "buff_group_11": sum(1 for b in data["buff_pool"] if b["group"] == 11),
        "producers_count": len(data["producers"]),
        "lists_total": len(lists),
        "lists_empty": sorted(t for t, v in lists.items() if not v["recipes"]),
        "lists_nonempty_counts": {t: len(v["recipes"]) for t, v in sorted(lists.items()) if v["recipes"]},
    }
    with open(os.path.join(out_dir, "summary.json"), "w", encoding="utf-8") as fh:
        json.dump(summary, fh, ensure_ascii=False, indent=2)
    print(json.dumps(summary, ensure_ascii=False, indent=2)[:4000])


if __name__ == "__main__":
    main()
